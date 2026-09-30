<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;

/**
 * DB-13 — migrations are written against PostgreSQL, with nothing that only MySQL understands.
 *
 * Flags the Blueprint's MySQL-only options — `->engine()`, `->charset()`, `->after()`, a
 * `utf8mb4` collation, and the same set as property assignments — and MySQL syntax in raw SQL:
 * backtick quoting, `ENGINE=` and `AUTO_INCREMENT`.
 */
final class PostgresMigrations extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const MYSQL_OPTIONS = ['engine', 'charset', 'after'];

    private const RAW_SQL_METHODS = ['statement', 'unprepared', 'raw', 'select', 'selectone', 'insert', 'update', 'delete', 'affectingstatement'];

    private const MYSQL_SQL = ['/`/' => 'backtick quoting', '/\bENGINE\s*=/i' => '`ENGINE=`', '/\bAUTO_INCREMENT\b/i' => '`AUTO_INCREMENT`'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Migrations are written against PostgreSQL, with no MySQL-only syntax (DB-13)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->filesUnder(['database/migrations']) as $path) {
            foreach ($this->findIn($path, fn (Node $node): bool => $this->mysqlism($node) !== null) as $node) {
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d uses %s, which only MySQL understands. Every environment runs '
                        . 'PostgreSQL; drop it or write the PostgreSQL equivalent (DB-13).',
                        $node->getStartLine(),
                        $this->mysqlism($node)
                    ));
            }
        }

        return $details;
    }

    private function mysqlism(Node $node): ?string
    {
        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $method = $node->name->toString();

            if (in_array($method, self::MYSQL_OPTIONS, true)) {
                return sprintf('`->%s()`', $method);
            }

            if ($method === 'collation' && $this->startsWithUtf8mb4($node->getArgs()[0] ?? null)) {
                return 'a `utf8mb4` collation';
            }
        }

        if ($node instanceof Assign && $node->var instanceof PropertyFetch && $node->var->name instanceof Identifier
            && in_array($node->var->name->toString(), ['engine', 'charset', 'collation'], true)) {
            return sprintf('`$table->%s`', $node->var->name->toString());
        }

        if (($node instanceof MethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier) {
            $method = strtolower($node->name->toString());

            if (in_array($method, self::RAW_SQL_METHODS, true) || str_ends_with($method, 'raw')) {
                foreach ($node->getArgs() as $argument) {
                    $found = $this->mysqlSql($argument->value);

                    if ($found !== null) {
                        return $found . ' in raw SQL';
                    }
                }
            }
        }

        return null;
    }

    private function mysqlSql(Node $value): ?string
    {
        $sql = match (true) {
            $value instanceof String_ => $value->value,
            $value instanceof InterpolatedString => implode(' ', array_map(
                static fn (Node $part): string => $part instanceof InterpolatedStringPart ? $part->value : '?',
                $value->parts
            )),
            default => null,
        };

        if ($sql === null) {
            return null;
        }

        foreach (self::MYSQL_SQL as $pattern => $label) {
            if (preg_match($pattern, $sql) === 1) {
                return $label;
            }
        }

        return null;
    }

    private function startsWithUtf8mb4(?Arg $argument): bool
    {
        return $argument !== null
            && $argument->value instanceof String_
            && str_starts_with(strtolower($argument->value->value), 'utf8mb4');
    }
}

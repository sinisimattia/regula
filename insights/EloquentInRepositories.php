<?php

declare(strict_types=1);

namespace Insights;

use Illuminate\Support\Facades\DB;
use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * DB-01 / DB-02 — a repository is the only place that touches Eloquent, and it hands back entities.
 *
 * Flags a public repository method returning a model or an Eloquent builder or collection, and a
 * query started on a model or the `DB` facade in a domain outside `Repositories/` and `Models/`, or
 * in any controller. A model merely named as a type is not a query and is left alone.
 */
final class EloquentInRepositories extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const QUERY_STARTERS = '/^(query|where\w*|find\w*|first\w*|create|forceCreate|all|firstOrCreate|updateOrCreate|destroy|insert\w*|upsert)$/i';

    private const DB_METHODS = ['table', 'select', 'selectone', 'insert', 'update', 'delete', 'statement', 'unprepared', 'raw'];

    private const ELOQUENT_NAMESPACE = 'Illuminate\\Database\\Eloquent\\';

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Only repositories touch Eloquent, and they return entities, never models (DB-01, DB-02)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->isGrandfathered($path)) {
                continue;
            }

            $layer = $this->layerOf($path);

            if ($layer === 'Repositories') {
                $details = array_merge($details, $this->modelReturns($path));
            }

            if ($this->mustNotQuery($path, $layer)) {
                $details = array_merge($details, $this->queries($path));
            }
        }

        return $details;
    }

    private function mustNotQuery(string $path, ?string $layer): bool
    {
        if (str_contains($this->relative($path), '/Http/Controllers/')) {
            return true;
        }

        return $this->domainOf($path) !== null && ! in_array($layer, ['Repositories', 'Models'], true);
    }

    /**
     * @return Details[]
     */
    private function modelReturns(string $path): array
    {
        $details = [];

        foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof ClassMethod && $node->isPublic()) as $method) {
            /** @var ClassMethod $method */
            $returned = $this->typeNames($method->returnType);
            $doc = $method->getDocComment()?->getText() ?? '';

            if (preg_match('/@return\s+([^\s*]+)/', $doc, $match) === 1) {
                $returned = array_merge($returned, $this->docTypeNames($path, $match[1]));
            }

            foreach (array_unique($returned) as $type) {
                if (! $this->isEloquentType($type)) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d: `%s()` returns `%s`. A repository maps the model to an entity with '
                        . '`toEntity()` before returning it (DB-02).',
                        $method->getStartLine(),
                        $method->name->toString(),
                        $type
                    ));
            }
        }

        return $details;
    }

    private function isEloquentType(string $type): bool
    {
        if (str_starts_with($type, self::ELOQUENT_NAMESPACE) || str_contains('\\' . $type, '\\Models\\')) {
            return true;
        }

        return str_contains($type, '\\') && $this->isEloquentModel($type);
    }

    /**
     * @return Details[]
     */
    private function queries(string $path): array
    {
        $details = [];

        foreach ($this->findIn($path, fn (Node $node): bool => $this->queryTarget($node) !== null) as $node) {
            /** @var StaticCall $node */
            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    'Line %d queries through `%s::%s()`. Only the domain\'s repository touches Eloquent; '
                    . 'call a repository method from a service instead (DB-01).',
                    $node->getStartLine(),
                    $this->queryTarget($node),
                    $node->name instanceof Identifier ? $node->name->toString() : '?'
                ));
        }

        return $details;
    }

    private function queryTarget(Node $node): ?string
    {
        if (! $node instanceof StaticCall || ! $node->class instanceof Name || ! $node->name instanceof Identifier) {
            return null;
        }

        $class = $node->class->toString();
        $method = $node->name->toString();

        if (in_array(strtolower($class), ['self', 'static', 'parent'], true)) {
            return null;
        }

        if ($class === DB::class) {
            return in_array(strtolower($method), self::DB_METHODS, true) ? 'DB' : null;
        }

        return preg_match(self::QUERY_STARTERS, $method) === 1 && $this->isEloquentModel($class)
            ? $node->class->getLast()
            : null;
    }
}

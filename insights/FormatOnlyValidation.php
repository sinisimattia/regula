<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\NodeFinder;

/**
 * HTTP-10 and HTTP-11 — a Form Request checks format and type; existence, uniqueness and enum
 * membership are the service's to decide.
 *
 * Flags `exists:` / `unique:` and their `Rule::` and `new` forms, and `Rule::enum()` / `new Enum()` or
 * an `in:` list — literal or built from `::cases()` — that repeats a backed enum's values.
 */
final class FormatOnlyValidation extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const FORM_REQUEST = 'Illuminate\Foundation\Http\FormRequest';

    private const RULE_FACADE = 'Illuminate\Validation\Rule';

    private const EXISTS = 'Illuminate\Validation\Rules\Exists';

    private const UNIQUE = 'Illuminate\Validation\Rules\Unique';

    private const ENUM = 'Illuminate\Validation\Rules\Enum';

    private const IN = 'Illuminate\Validation\Rules\In';

    /**
     * Methods whose strings are not rules: `'email.exists' => '...'` is a message key.
     */
    private const NOT_RULES = ['messages', 'attributes'];

    /**
     * @var array<string, string>|null  sorted value set => enum name
     */
    private ?array $enumValueSets = null;

    public function getTitle(): string
    {
        return 'Existence, uniqueness and enum membership are checked by the service, not the rules (HTTP-10, HTTP-11)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->isGrandfathered($path) || ! $this->isHttpGoverned($path)) {
                continue;
            }

            $isRule = str_contains('/' . $this->relative($path), '/Rules/');

            foreach ($this->classesIn($path) as $class) {
                if (! $isRule && ! $this->descendsFrom($class, self::FORM_REQUEST)) {
                    continue;
                }

                foreach ($class->getMethods() as $method) {
                    if (in_array($method->name->toLowerString(), self::NOT_RULES, true)) {
                        continue;
                    }

                    foreach ($this->violationsIn($method) as [$line, $message]) {
                        $details[] = $this->detail($path, sprintf('Line %d %s', $line, $message));
                    }
                }
            }
        }

        return $details;
    }

    /**
     * @return list<array{int, string}>
     */
    private function violationsIn(ClassMethod $method): array
    {
        $violations = [];
        $exists = 'queries the database in the rules. Validate the format only and let the service throw '
            . 'its domain exception, which the controller maps to the right status (HTTP-10).';
        $enum = 'repeats an enum in the rules. Validate as `string` and convert with `tryFrom()` in the '
            . 'service, which throws on an invalid value (HTTP-11).';

        foreach ((new NodeFinder())->find($method->stmts ?? [], fn (Node $node): bool => true) as $node) {
            $line = $node->getStartLine();

            if ($node instanceof String_) {
                foreach (explode('|', $node->value) as $segment) {
                    if (str_starts_with($segment, 'exists:') || str_starts_with($segment, 'unique:')) {
                        $violations[] = [$line, "uses `{$segment}` and " . $exists];
                    } elseif (str_starts_with($segment, 'in:') && ($name = $this->enumWithValues(explode(',', substr($segment, 3)))) !== null) {
                        $violations[] = [$line, "lists {$name}'s values in `{$segment}` and " . $enum];
                    }
                }
            } elseif ($node instanceof Concat && $this->leftmostString($node) !== null
                && preg_match('/(^|\|)in:/', (string) $this->leftmostString($node)) === 1 && $this->callsCases($node)) {
                $violations[] = [$line, 'builds an `in:` list from `::cases()` and ' . $enum];
            } elseif ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Identifier
                && $node->class->toString() === self::RULE_FACADE) {
                $violations = [...$violations, ...$this->ruleFactoryViolation($node, $line, $exists, $enum)];
            } elseif ($node instanceof New_ && $node->class instanceof Name) {
                $violations = [...$violations, ...$this->ruleObjectViolation($node, $line, $exists, $enum)];
            }
        }

        // A chain of concatenations is one Concat per operator; report the rule once.
        return array_values(array_unique($violations, SORT_REGULAR));
    }

    /**
     * @return list<array{int, string}>
     */
    private function ruleFactoryViolation(StaticCall $node, int $line, string $exists, string $enum): array
    {
        return match ($node->name instanceof Identifier ? $node->name->toLowerString() : '') {
            'exists' => [[$line, 'uses `Rule::exists()` and ' . $exists]],
            'unique' => [[$line, 'uses `Rule::unique()` and ' . $exists]],
            'enum' => [[$line, 'uses `Rule::enum()` and ' . $enum]],
            'in' => $this->inRepeatsEnum($node->getArgs()[0]->value ?? null) ? [[$line, 'uses `Rule::in()` over an enum\'s values and ' . $enum]] : [],
            default => [],
        };
    }

    /**
     * @return list<array{int, string}>
     */
    private function ruleObjectViolation(New_ $node, int $line, string $exists, string $enum): array
    {
        /** @var Name $class */
        $class = $node->class;

        return match ($class->toString()) {
            self::EXISTS => [[$line, 'uses `new Exists()` and ' . $exists]],
            self::UNIQUE => [[$line, 'uses `new Unique()` and ' . $exists]],
            self::ENUM => [[$line, 'uses `new Enum()` and ' . $enum]],
            self::IN => $this->inRepeatsEnum($node->getArgs()[0]->value ?? null) ? [[$line, 'uses `new In()` over an enum\'s values and ' . $enum]] : [],
            default => [],
        };
    }

    private function inRepeatsEnum(?Expr $values): bool
    {
        if ($values === null) {
            return false;
        }

        if ($this->callsCases($values)) {
            return true;
        }

        if (! $values instanceof Array_) {
            return false;
        }

        $literals = [];

        foreach ($values->items as $item) {
            if (! $item->value instanceof String_ && ! $item->value instanceof Int_) {
                return false;
            }

            $literals[] = (string) $item->value->value;
        }

        return $this->enumWithValues($literals) !== null;
    }

    private function callsCases(Node $node): bool
    {
        return (new NodeFinder())->findFirst($node, fn (Node $inner): bool => $inner instanceof StaticCall
            && $inner->name instanceof Identifier && $inner->name->toLowerString() === 'cases') !== null;
    }

    private function leftmostString(Concat $node): ?string
    {
        $left = $node->left;

        while ($left instanceof Concat) {
            $left = $left->left;
        }

        return $left instanceof String_ ? $left->value : null;
    }

    /**
     * The backed enum under app/ whose value set equals the given values, if any.
     *
     * @param  string[]  $values
     */
    private function enumWithValues(array $values): ?string
    {
        $values = array_values(array_unique(array_map('trim', $values)));
        sort($values);

        return $this->enumValueSets()[implode("\0", $values)] ?? null;
    }

    /**
     * Read from source rather than by reflection, so the check needs no autoloading.
     *
     * @return array<string, string>
     */
    private function enumValueSets(): array
    {
        if ($this->enumValueSets !== null) {
            return $this->enumValueSets;
        }

        $this->enumValueSets = [];

        foreach ($this->analysedFiles() as $path) {
            foreach ($this->findIn($path, fn (Node $node): bool => $node instanceof Enum_ && $node->scalarType !== null) as $enum) {
                /** @var Enum_ $enum */
                $values = [];

                foreach ($enum->stmts as $statement) {
                    if ($statement instanceof Node\Stmt\EnumCase && ($statement->expr instanceof String_ || $statement->expr instanceof Int_)) {
                        $values[] = (string) $statement->expr->value;
                    }
                }

                if ($values !== []) {
                    sort($values);
                    $this->enumValueSets[implode("\0", $values)] = (string) $enum->name;
                }
            }
        }

        return $this->enumValueSets;
    }
}

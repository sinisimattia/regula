<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;

/**
 * DB-05 / DB-06 / DB-07 — every model maps itself with `toEntity()`, documents its columns with
 * `@property`, and declares casts in `protected $casts`.
 *
 * A column is taken to be anything named in `$casts`, `$fillable` or `$hidden`; each must carry an
 * `@property`, `@property-read` or `@property-write` tag. Abstract models are skipped.
 */
final class ModelShape extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const COLUMN_LISTS = ['casts' => true, 'fillable' => false, 'hidden' => false];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Models carry toEntity(), @property tags for their columns, and a $casts property (DB-05, DB-06, DB-07)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->classIndex() as $fqcn => ['path' => $path, 'node' => $class]) {
            if (! $class instanceof Class_ || $class->isAbstract() || ! $this->isEloquentModel($fqcn) || $this->isGrandfathered($path)) {
                continue;
            }

            $name = (string) $class->name;
            $report = static function (string $message) use (&$details, $path): void {
                $details[] = Details::make()->setFile($path)->setMessage($message);
            };

            if (! $this->hasMethod($fqcn, 'toEntity')) {
                $report(sprintf('`%s` has no `toEntity()`. It is the one mapping from the table to the domain (DB-05).', $name));
            }

            if ($class->getMethod('casts') !== null) {
                $report(sprintf(
                    '`%s` declares a `casts()` method (line %d). Use `protected $casts = [...]`, as every '
                    . 'other model does (DB-07).',
                    $name,
                    $class->getMethod('casts')->getStartLine()
                ));
            }

            $documented = $this->documentedProperties($class->getDocComment()?->getText() ?? '');

            if ($documented === []) {
                $report(sprintf('`%s` has no `@property` tags. Document its columns in the class docblock (DB-06).', $name));

                continue;
            }

            foreach ($this->declaredColumns($class) as $column => $list) {
                if (! in_array($column, $documented, true)) {
                    $report(sprintf(
                        '`%s` lists `%s` in `$%s` but has no `@property` tag for it (DB-06).',
                        $name,
                        $column,
                        $list
                    ));
                }
            }
        }

        return $details;
    }

    /**
     * Whether the class or an ancestor declared under app/ defines the method.
     */
    private function hasMethod(string $fqcn, string $method): bool
    {
        $index = $this->classIndex();
        $visited = [];

        while (isset($index[$fqcn]) && ! isset($visited[$fqcn])) {
            $visited[$fqcn] = true;
            $node = $index[$fqcn]['node'];

            if ($node->getMethod($method) !== null) {
                return true;
            }

            if (! $node instanceof Class_ || $node->extends === null) {
                return false;
            }

            $fqcn = $node->extends->toString();
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function documentedProperties(string $docblock): array
    {
        preg_match_all('/@property(?:-read|-write)?\s+[^$\n]*\$(\w+)/', $docblock, $matches);

        return $matches[1];
    }

    /**
     * Column names the model lists, mapped to the list that names them.
     *
     * @return array<string, string>
     */
    private function declaredColumns(Class_ $class): array
    {
        $columns = [];

        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $prop) {
                $list = $prop->name->toString();

                if (! array_key_exists($list, self::COLUMN_LISTS) || ! $prop->default instanceof Array_) {
                    continue;
                }

                foreach ($prop->default->items as $item) {
                    $column = self::COLUMN_LISTS[$list] ? $item?->key : $item?->value;

                    if ($column instanceof String_) {
                        $columns[$column->value] ??= $list;
                    }
                }
            }
        }

        return $columns;
    }
}

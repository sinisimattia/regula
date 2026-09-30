<?php

declare(strict_types=1);

namespace Insights;

use App\Domains\Core\Contracts\Identification;
use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * ENT-05 — an entity ID is its own type, extending `Identification`, and travels as that type.
 *
 * Two checks. A class in `Entities/` named `…Id` extends `Identification`. And in a domain's
 * `Services/`, `Repositories/` and `Entities/`, an entity ID parameter is typed — never `int`,
 * `string`, `mixed` or nothing. `$fooId` is one where a `FooId` entity exists. A bare `$id` is one
 * only in entity `Foo` when `FooId` exists, or in a service or repository `get`, `get…ById` or
 * `delete…` method; elsewhere it may be a third party's ID, which is not an entity ID.
 */
final class TypedIds extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const LAYERS = ['Services', 'Repositories', 'Entities'];

    private const PRIMITIVES = ['int', 'string', 'mixed'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Entity IDs extend Identification and travel as their own type (ENT-05)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];
        $idClasses = $this->idClasses();

        foreach ($this->analysedFiles() as $path) {
            $layer = $this->layerOf($path);

            if (! in_array($layer, self::LAYERS, true) || $this->isGrandfathered($path)) {
                continue;
            }

            if ($layer === 'Entities') {
                $details = array_merge($details, $this->unrootedIds($path));
            }

            foreach ($this->classesIn($path) as $class) {
                foreach ($class->getMethods() as $method) {
                    $details = array_merge($details, $this->untypedIds($path, $layer, (string) $class->name, $method, $idClasses));
                }
            }
        }

        return $details;
    }

    /**
     * @param  array<string, true>  $idClasses  short names of the typed IDs that exist
     * @return Details[]
     */
    private function untypedIds(string $path, string $layer, string $className, ClassMethod $method, array $idClasses): array
    {
        $details = [];

        foreach ($method->params as $param) {
            if (! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $name = $param->var->name;

            if (! $this->isEntityId($name, $layer, $className, $method->name->toString(), $idClasses)) {
                continue;
            }

            $types = $this->typeNames($param->type);
            $primitive = array_values(array_intersect($types, self::PRIMITIVES));

            if ($types !== [] && $primitive === []) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    'Line %d: `$%s` is %s. An entity ID travels as its typed ID (`%s`), which the '
                    . 'receiver trusts without re-validating (ENT-05).',
                    $param->getStartLine(),
                    $name,
                    $types === [] ? 'untyped' : 'typed `' . $primitive[0] . '`',
                    $name === 'id' ? '{Entity}Id' : ucfirst($name)
                ));
        }

        return $details;
    }

    /**
     * @param  array<string, true>  $idClasses  short names of the typed IDs that exist
     */
    private function isEntityId(string $name, string $layer, string $className, string $methodName, array $idClasses): bool
    {
        if ($name !== 'id') {
            return str_ends_with($name, 'Id') && isset($idClasses[ucfirst($name)]);
        }

        if ($layer === 'Entities') {
            return isset($idClasses[$className . 'Id']);
        }

        return $methodName === 'get' || str_starts_with($methodName, 'delete')
            || (str_starts_with($methodName, 'get') && str_ends_with($methodName, 'ById'));
    }

    /**
     * @return Details[]
     */
    private function unrootedIds(string $path): array
    {
        $details = [];

        foreach ($this->classesIn($path) as $node) {
            $fqcn = $this->fqcnOf($node);

            if (! $node instanceof Class_ || $fqcn === null || preg_match('/(^|[a-z0-9])Id$/', (string) $node->name) !== 1) {
                continue;
            }

            if ($this->descendsFrom($fqcn, Identification::class)) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    '`%s` looks like a typed ID but does not extend `%s`. Every entity ID does (ENT-05).',
                    $node->name,
                    Identification::class
                ));
        }

        return $details;
    }

    /**
     * Short names of the typed IDs declared in any domain's `Entities/`.
     *
     * @return array<string, true>
     */
    private function idClasses(): array
    {
        $names = [];

        foreach ($this->classIndex() as $fqcn => $entry) {
            if ($this->layerOf($entry['path']) === 'Entities' && str_ends_with($fqcn, 'Id')) {
                $names[$entry['node']->name?->toString() ?? ''] = true;
            }
        }

        return $names;
    }
}

<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;

/**
 * ENT-02 / DB-03 — a service method whose whole body is one repository call carries that call's name.
 *
 * A repository injected into two or more services is exempt, because DB-03 gives it the shared
 * vocabulary instead.
 */
final class RepositoryDelegationNaming extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'A service method that only delegates carries its repository method\'s name (ENT-02, DB-03)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $services = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->layerOf($path) !== 'Services') {
                continue;
            }

            foreach ($this->classesIn($path) as $class) {
                if ($class instanceof Class_) {
                    $services[] = ['path' => $path, 'class' => $class, 'repositories' => $this->repositoryProperties($class)];
                }
            }
        }

        $servicesPerRepository = [];

        foreach ($services as $service) {
            foreach (array_unique($service['repositories']) as $repository) {
                $servicesPerRepository[$repository] = ($servicesPerRepository[$repository] ?? 0) + 1;
            }
        }

        $details = [];

        foreach ($services as ['path' => $path, 'class' => $class, 'repositories' => $repositories]) {
            if ($this->isGrandfathered($path)) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $call = $this->soleRepositoryCall($method, $repositories);

                if ($call === null) {
                    continue;
                }

                [$propertyName, $calledMethod] = $call;

                if ($calledMethod === $method->name->toString() || $servicesPerRepository[$repositories[$propertyName]] > 1) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d: `%s()` only calls `%s::%s()`. One operation has one name: rename one '
                        . 'of them so the service and the repository match (ENT-02, DB-03).',
                        $method->getStartLine(),
                        $method->name->toString(),
                        $this->shortName($repositories[$propertyName]),
                        $calledMethod
                    ));
            }
        }

        return $details;
    }

    /**
     * Repository-typed properties, promoted or declared, by property name, each normalised to the
     * repository it names so a class and its interface count as one.
     *
     * @return array<string, string>
     */
    private function repositoryProperties(Class_ $class): array
    {
        $properties = [];

        foreach ($class->getProperties() as $property) {
            foreach ($this->repositoryTypes($property->type) as $repository) {
                foreach ($property->props as $item) {
                    $properties[$item->name->toString()] = $repository;
                }
            }
        }

        foreach ($class->getMethod('__construct')?->params ?? [] as $param) {
            if ($param->flags !== 0 && $param->var instanceof Variable && is_string($param->var->name)) {
                foreach ($this->repositoryTypes($param->type) as $repository) {
                    $properties[$param->var->name] = $repository;
                }
            }
        }

        return $properties;
    }

    /**
     * @return string[]
     */
    private function repositoryTypes(?Node $type): array
    {
        $repositories = [];

        foreach ($this->typeNames($type) as $name) {
            if (str_ends_with($name, 'RepositoryInterface')) {
                $repositories[] = substr($name, 0, -strlen('Interface'));
            } elseif (str_ends_with($name, 'Repository')) {
                $repositories[] = $name;
            }
        }

        return $repositories;
    }

    /**
     * The repository property and method a public method's single statement calls, or null when
     * the body does anything more than that one call.
     *
     * @param  array<string, string>  $repositories
     * @return array{0: string, 1: string}|null
     */
    private function soleRepositoryCall(ClassMethod $method, array $repositories): ?array
    {
        if (! $method->isPublic() || $method->stmts === null || count($method->stmts) !== 1) {
            return null;
        }

        $statement = $method->stmts[0];
        $call = $statement instanceof Return_ || $statement instanceof Expression ? $statement->expr : null;

        if (! $call instanceof MethodCall
            || ! $call->name instanceof Identifier
            || ! $call->var instanceof PropertyFetch
            || ! $call->var->var instanceof Variable
            || $call->var->var->name !== 'this'
            || ! $call->var->name instanceof Identifier) {
            return null;
        }

        $propertyName = $call->var->name->toString();

        return isset($repositories[$propertyName]) ? [$propertyName, $call->name->toString()] : null;
    }

    private function shortName(string $fqcn): string
    {
        return substr((string) strrchr('\\' . $fqcn, '\\'), 1);
    }
}

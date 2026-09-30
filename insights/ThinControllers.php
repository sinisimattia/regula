<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * HTTP-03 — a controller action calls one service method.
 *
 * Counts calls on `$this->{property}` where the property is typed as a service (a type ending in
 * `Service` or `ServiceInterface`), and on the action's own parameters typed the same way, and
 * flags an action with more than one. Two call sites count
 * as two even when they sit in different branches: choosing between services is orchestration.
 * Repository and model access in controllers is DA-04's and DB-01's to catch.
 */
final class ThinControllers extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    public function getTitle(): string
    {
        return 'A controller action calls one service method (HTTP-03)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->isGrandfathered($path) || ! $this->isController($path) || ! $this->isHttpGoverned($path)) {
                continue;
            }

            foreach ($this->classesIn($path) as $class) {
                $services = $this->serviceProperties($class);

                foreach ($this->publicActions($class) as $action) {
                    $calls = $this->serviceCalls($action, $services, $this->serviceParameters($action));

                    if (count($calls) > 1) {
                        $details[] = $this->detail($path, sprintf(
                            'Line %d: %s() makes %d service calls (%s). Give the service one method that does '
                            . 'the whole job, and call only that (HTTP-03).',
                            $action->getStartLine(),
                            $action->name,
                            count($calls),
                            implode(', ', array_unique($calls)),
                        ));
                    }
                }
            }
        }

        return $details;
    }

    /**
     * Properties typed as services, from declarations and promoted constructor parameters.
     *
     * @return string[]
     */
    private function serviceProperties(Class_ $class): array
    {
        $names = [];

        foreach ($class->getProperties() as $property) {
            if ($this->isServiceType($property->type)) {
                foreach ($property->props as $item) {
                    $names[] = $item->name->toString();
                }
            }
        }

        foreach ($class->getMethod('__construct')?->params ?? [] as $parameter) {
            if ($parameter->flags !== 0 && $this->isServiceType($parameter->type)
                && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                $names[] = $parameter->var->name;
            }
        }

        return $names;
    }

    /**
     * The action's own parameters typed as services, injected by the container per call.
     *
     * @return string[]
     */
    private function serviceParameters(ClassMethod $action): array
    {
        $names = [];

        foreach ($action->params as $parameter) {
            if ($this->isServiceType($parameter->type) && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                $names[] = $parameter->var->name;
            }
        }

        return $names;
    }

    private function isServiceType(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        return $type instanceof Name
            && (str_ends_with($type->getLast(), 'Service') || str_ends_with($type->getLast(), 'ServiceInterface'));
    }

    /**
     * Each service call in the action, as `property->method` or `$parameter->method`.
     *
     * @param  string[]  $services  service properties of the controller
     * @param  string[]  $parameters  service parameters of the action
     * @return string[]
     */
    private function serviceCalls(ClassMethod $action, array $services, array $parameters): array
    {
        $calls = [];

        foreach ((new NodeFinder())->find($action->stmts ?? [], fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall) as $call) {
            /** @var MethodCall|NullsafeMethodCall $call */
            $fetch = $call->var;

            if ($fetch instanceof PropertyFetch && $fetch->var instanceof Variable && $fetch->var->name === 'this'
                && $fetch->name instanceof Identifier && in_array($fetch->name->toString(), $services, true)
                && $call->name instanceof Identifier) {
                $calls[] = $fetch->name->toString() . '->' . $call->name->toString();
            } elseif ($fetch instanceof Variable && is_string($fetch->name) && in_array($fetch->name, $parameters, true)
                && $call->name instanceof Identifier) {
                $calls[] = '$' . $fetch->name . '->' . $call->name->toString();
            }
        }

        return $calls;
    }
}

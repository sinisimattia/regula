<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * HTTP-12 — a controller hands the service a typed entity built by the Form Request, never the
 * request's input as an array.
 *
 * Flags `validated()`, `all()`, `only()`, `except()`, and `input()` or `collect()` with no key, and the same on
 * `safe()`, when the result is passed straight into a call. Reading one field by name is not an
 * array and is not flagged.
 */
final class EntityExtraction extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const WHOLE_INPUT = ['validated', 'all', 'only', 'except', 'input', 'collect'];

    public function getTitle(): string
    {
        return 'Controllers pass entities built by the Form Request, not request arrays (HTTP-12)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->isGrandfathered($path) || ! $this->isController($path)) {
                continue;
            }

            foreach ($this->classesIn($path) as $class) {
                foreach ($class->getMethods() as $method) {
                    $requests = $this->requestParameters($method);

                    foreach ((new NodeFinder())->find($method->stmts ?? [], fn (Node $node): bool => $node instanceof Arg
                        && $this->readsWholeInput($node->value, $requests)) as $argument) {
                        /** @var Arg $argument */
                        /** @var MethodCall|NullsafeMethodCall $call */
                        $call = $argument->value;
                        $details[] = $this->detail($path, sprintf(
                            'Line %d passes the request\'s input as an array with `%s()`. Add an extractEntity() (or '
                            . 'extractSteps(), etc.) to the Form Request and pass the typed entity it builds (HTTP-12).',
                            $argument->getStartLine(),
                            $call->name instanceof Identifier ? $call->name->toString() : 'input',
                        ));
                    }
                }
            }
        }

        return $details;
    }

    /**
     * Names of the method's parameters that are requests.
     *
     * @return string[]
     */
    private function requestParameters(ClassMethod $method): array
    {
        $names = [];

        foreach ($method->params as $parameter) {
            if ($parameter->type instanceof Name && str_ends_with($parameter->type->toString(), 'Request')
                && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                $names[] = $parameter->var->name;
            }
        }

        return $names;
    }

    /**
     * @param  string[]  $requests
     */
    private function readsWholeInput(Expr $value, array $requests): bool
    {
        if (! ($value instanceof MethodCall || $value instanceof NullsafeMethodCall) || ! $value->name instanceof Identifier) {
            return false;
        }

        $name = $value->name->toLowerString();

        if (! in_array($name, self::WHOLE_INPUT, true) || (in_array($name, ['input', 'collect'], true) && $value->getArgs() !== [])) {
            return false;
        }

        $receiver = $value->var;

        // $request->safe()->all()
        if ($receiver instanceof MethodCall && $receiver->name instanceof Identifier && $receiver->name->toLowerString() === 'safe') {
            $receiver = $receiver->var;
        }

        return ($receiver instanceof Variable && in_array($receiver->name, $requests, true))
            || ($receiver instanceof FuncCall && $receiver->name instanceof Name && strtolower($receiver->name->getLast()) === 'request');
    }
}

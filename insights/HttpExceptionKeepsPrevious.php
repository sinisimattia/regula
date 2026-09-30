<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

/**
 * HTTP-04 — an `HttpApplicationException` raised while handling another exception carries it as
 * `previous`, or the trace ends at the controller.
 *
 * Checks every `new HttpApplicationException(...)` inside a `catch` block. The position of
 * `previous` is read from the class's constructor, so reordering its parameters cannot fool it.
 */
final class HttpExceptionKeepsPrevious extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const EXCEPTION = 'App\Exceptions\HttpApplicationException';

    private const EXCEPTION_FILE = 'app/Exceptions/HttpApplicationException.php';

    public function getTitle(): string
    {
        return 'A wrapped domain exception is kept as previous (HTTP-04)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $slot = $this->previousSlot();
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->isGrandfathered($path)) {
                continue;
            }

            /** @var array<int, array{New_, Catch_}> $raised */
            $raised = [];

            // Outer catches come first in document order, so an inner catch overwrites them and each
            // construction is judged against the catch that most closely encloses it.
            foreach ($this->findIn($path, fn (Node $node): bool => $node instanceof Catch_) as $catch) {
                /** @var Catch_ $catch */
                foreach ((new NodeFinder())->find($catch->stmts, fn (Node $node): bool => $this->constructsException($node)) as $new) {
                    /** @var New_ $new */
                    $raised[spl_object_id($new)] = [$new, $catch];
                }
            }

            foreach ($raised as [$new, $catch]) {
                if (! $this->passesCaught($new, $catch, $slot)) {
                    $details[] = $this->detail($path, sprintf(
                        'Line %d wraps an exception in HttpApplicationException without keeping it. %s (HTTP-04).',
                        $new->getStartLine(),
                        $catch->var instanceof Variable && is_string($catch->var->name)
                            ? "Pass `previous: \${$catch->var->name}`"
                            : 'Name the exception in the catch and pass it as `previous:`',
                    ));
                }
            }
        }

        return $details;
    }

    private function constructsException(Node $node): bool
    {
        return $node instanceof New_ && $node->class instanceof Name && $node->class->toString() === self::EXCEPTION;
    }

    private function passesCaught(New_ $new, Catch_ $catch, ?int $slot): bool
    {
        if (! $catch->var instanceof Variable || ! is_string($catch->var->name)) {
            return false;
        }

        foreach ($new->getArgs() as $position => $argument) {
            if ($argument->unpack) {
                return true; // Spread arguments cannot be read statically.
            }

            $isPrevious = $argument->name !== null
                ? $argument->name->toString() === 'previous'
                : $position === $slot;

            if ($isPrevious) {
                return $argument->value instanceof Variable && $argument->value->name === $catch->var->name;
            }
        }

        return false;
    }

    /**
     * The zero-based position of the constructor's `previous` parameter, or null when it has none.
     */
    private function previousSlot(): ?int
    {
        $path = getcwd() . '/' . self::EXCEPTION_FILE;

        if (! is_file($path)) {
            return null;
        }

        foreach ($this->findIn($path, fn (Node $node): bool => $node instanceof Class_) as $class) {
            /** @var Class_ $class */
            foreach ($class->getMethod('__construct')?->params ?? [] as $position => $parameter) {
                if ($parameter->var instanceof Variable && $parameter->var->name === 'previous') {
                    return $position;
                }
            }
        }

        return null;
    }
}

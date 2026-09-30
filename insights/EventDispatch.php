<?php

declare(strict_types=1);

namespace Insights;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;

/**
 * DA-19 / HTTP-05 — a domain event is dispatched with `event()`, from inside a service method.
 *
 * Flags `SomeEvent::dispatch()` anywhere, any event dispatch from the HTTP layer, and a domain
 * event fired outside a domain's `Services/`. Framework events, such as `Verified`, are not domain
 * events and stay out of it.
 */
final class EventDispatch extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const STATIC_DISPATCH = ['dispatch', 'dispatchif', 'dispatchunless'];

    private const HELPERS = ['event', 'broadcast'];

    private const DISPATCHERS = [DispatcherContract::class, Dispatcher::class];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Domain events are dispatched with event(), from inside a service method (DA-19, HTTP-05)';
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

            $inHttp = str_starts_with($this->relative($path), 'app/Http/') || $this->layerOf($path) === 'Http';
            $inService = $this->layerOf($path) === 'Services';
            $dispatchers = $inHttp ? $this->dispatcherNames($path) : [];

            foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof FuncCall || $node instanceof StaticCall || $node instanceof MethodCall) as $node) {
                $problem = $this->staticDispatch($node)
                    ?? ($inHttp ? $this->httpDispatch($node, $dispatchers) : null)
                    ?? ($inService ? null : $this->domainEventOutsideService($node));

                if ($problem !== null) {
                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf('Line %d %s', $node->getStartLine(), $problem));
                }
            }
        }

        return $details;
    }

    private function staticDispatch(Node $node): ?string
    {
        if (! $node instanceof StaticCall || ! $node->class instanceof Name || ! $node->name instanceof Identifier) {
            return null;
        }

        $class = $node->class->toString();

        if (! str_contains('\\' . $class, '\\Events\\') || ! in_array($node->name->toLowerString(), self::STATIC_DISPATCH, true)) {
            return null;
        }

        return sprintf(
            'dispatches `%s` statically. Fire it with `event(new %s(...))` from inside the service '
            . 'method, so every caller produces it (DA-19).',
            $class,
            $node->class->getLast()
        );
    }

    /**
     * @param  string[]  $dispatchers
     */
    private function httpDispatch(Node $node, array $dispatchers): ?string
    {
        $fires = match (true) {
            $node instanceof FuncCall => $node->name instanceof Name && in_array($node->name->toLowerString(), self::HELPERS, true),
            $node instanceof StaticCall => $node->class instanceof Name
                && $node->class->toString() === Event::class
                && $node->name instanceof Identifier
                && in_array($node->name->toLowerString(), ['dispatch', 'until'], true),
            $node instanceof MethodCall => $node->name instanceof Identifier
                && in_array($node->name->toLowerString(), ['dispatch', 'until'], true)
                && $this->isDispatcher($node->var, $dispatchers),
            default => false,
        };

        return $fires
            ? 'fires an event from the HTTP layer. Move it into the service method the controller calls, '
                . 'or it will not fire for a job, a command or another service (DA-19, HTTP-05).'
            : null;
    }

    private function domainEventOutsideService(Node $node): ?string
    {
        $isEventHelper = $node instanceof FuncCall && $node->name instanceof Name && $node->name->toLowerString() === 'event';
        $isFacade = $node instanceof StaticCall && $node->class instanceof Name && $node->class->toString() === Event::class
            && $node->name instanceof Identifier && $node->name->toLowerString() === 'dispatch';

        if (! $isEventHelper && ! $isFacade) {
            return null;
        }

        $event = $node->getArgs()[0]->value ?? null;

        if (! $event instanceof New_ || ! $event->class instanceof Name) {
            return null;
        }

        $class = $event->class->toString();

        if (! str_starts_with($class, 'App\\Domains\\') || ! str_contains($class, '\\Events\\')) {
            return null;
        }

        return sprintf(
            'fires the domain event `%s` outside a service. Fire it inside the service method that '
            . 'performs the change, so every caller produces it (DA-19).',
            $class
        );
    }

    /**
     * Receivers that hold the events dispatcher: `app('events')`, `app(Dispatcher::class)`, and
     * variables or properties the file types as one.
     *
     * @param  string[]  $dispatchers
     */
    private function isDispatcher(Expr $receiver, array $dispatchers): bool
    {
        if ($receiver instanceof Variable && is_string($receiver->name)) {
            return in_array($receiver->name, $dispatchers, true);
        }

        if ($receiver instanceof PropertyFetch && $receiver->name instanceof Identifier) {
            return in_array($receiver->name->toString(), $dispatchers, true);
        }

        if ($receiver instanceof StaticCall && $receiver->class instanceof Name && $receiver->class->toString() === Event::class) {
            return true;
        }

        if ($receiver instanceof FuncCall && $receiver->name instanceof Name && in_array($receiver->name->toLowerString(), ['app', 'resolve'], true)) {
            $argument = $receiver->getArgs()[0]->value ?? null;

            return ($argument instanceof String_ && $argument->value === 'events')
                || ($argument instanceof ClassConstFetch && $argument->class instanceof Name
                    && in_array($argument->class->toString(), self::DISPATCHERS, true));
        }

        return false;
    }

    /**
     * Names of parameters and properties typed as the events dispatcher.
     *
     * @return string[]
     */
    private function dispatcherNames(string $path): array
    {
        $names = [];

        foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof Param || $node instanceof Property) as $node) {
            if (array_intersect($this->typeNames($node->type), self::DISPATCHERS) === []) {
                continue;
            }

            if ($node instanceof Param && $node->var instanceof Variable && is_string($node->var->name)) {
                $names[] = $node->var->name;
            }

            if ($node instanceof Property) {
                foreach ($node->props as $property) {
                    $names[] = $property->name->toString();
                }
            }
        }

        return $names;
    }
}

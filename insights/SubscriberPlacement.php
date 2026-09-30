<?php

declare(strict_types=1);

namespace Insights;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;
use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * DA-08 — a class that subscribes to many events lives in `Subscribers/`, never `Listeners/`.
 *
 * An event subscriber is recognised by its `subscribe()` method taking the events dispatcher, so an
 * unrelated `subscribe(string $email)` on a newsletter service is not mistaken for one.
 */
final class SubscriberPlacement extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const DISPATCHERS = [DispatcherContract::class, Dispatcher::class];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Event subscribers live in Subscribers/, single-event listeners in Listeners/ (DA-08)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->domainOf($path) === null || $this->isGrandfathered($path)) {
                continue;
            }

            $inSubscribers = str_contains($this->relative($path), '/Subscribers/');

            foreach ($this->classesIn($path) as $class) {
                $subscribe = $class->getMethod('subscribe');

                if ($subscribe === null || ! $this->isEventSubscription($subscribe) || $inSubscribers) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        '`%s` subscribes to events (line %d), so it is a subscriber: move it to the '
                        . 'domain\'s `Subscribers/` and name it `…Subscriber`. `Listeners/` holds '
                        . 'single-event handlers (DA-08).',
                        (string) $class->name,
                        $subscribe->getStartLine()
                    ));
            }
        }

        return $details;
    }

    private function isEventSubscription(ClassMethod $method): bool
    {
        if (count($method->params) !== 1) {
            return false;
        }

        $param = $method->params[0];

        if ($param->type === null) {
            return $param->var instanceof Variable && $param->var->name === 'events';
        }

        return array_intersect($this->typeNames($param->type), self::DISPATCHERS) !== [];
    }
}

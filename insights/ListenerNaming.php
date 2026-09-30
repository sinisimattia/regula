<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;

/**
 * PHP-17 for listeners: `{WhatItDoes}On{WhatHappened}Listener`, where `{WhatHappened}` is the
 * event its `handle()` takes. A listener whose `handle()` takes no single event class is skipped.
 */
final class ListenerNaming extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Listeners are named {WhatItDoes}On{WhatHappened}Listener (PHP-17)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(['app']) as $path) {
            if (! str_contains('/' . $this->relative($path), '/Listeners/')) {
                continue;
            }

            foreach ($this->classLikesIn($path) as $class) {
                $handle = $class instanceof Class_ && $class->name !== null ? $class->getMethod('handle') : null;
                $type = ($handle?->params[0] ?? null)?->type;

                if ($class->name === null || ! $type instanceof Name) {
                    continue;
                }

                $name = $class->name->toString();
                $event = $type->getLast();

                if (preg_match('/^[A-Z]\w*On' . preg_quote($event, '/') . 'Listener$/', $name) === 1) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        '`%s` handles `%s`, so it must be named `{WhatItDoes}On%sListener` (PHP-17).',
                        $name,
                        $event,
                        $event
                    ));
            }
        }

        return $details;
    }
}

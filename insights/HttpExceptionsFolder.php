<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;

/**
 * DA-21 — `Http/Exceptions/` must not exist anywhere.
 *
 * A domain's exceptions live in its own `Exceptions/` folder, in the domain's vocabulary. Errors
 * crossing into HTTP are wrapped by the controller in `HttpApplicationException` (HTTP-04). Between
 * them those two cover everything, so a third location is a sign that something has been put in the
 * wrong place — usually a class that translates domain exceptions into HTTP ones, which belongs in
 * the controller doing the wrapping.
 *
 * Checked by folder rather than by class, because the folder is the part that invites the next one.
 */
final class HttpExceptionsFolder extends DomainInsight implements HasDetails
{
    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Http/Exceptions/ must not exist — domain exceptions live in Exceptions/ (DA-21)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $relative = $this->relative($path);

            if (! str_contains($relative, '/Http/Exceptions/') || $this->isGrandfathered($path)) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    '`%s` sits in an Http/Exceptions/ folder. Domain exceptions belong in the '
                    . "domain's Exceptions/; anything translating them for HTTP belongs in the "
                    . 'controller that wraps them (DA-21, HTTP-04).',
                    $relative
                ));
        }

        return $details;
    }
}

<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;

/**
 * PHP-17 — a framework class carries the suffix its kind is known by.
 *
 * The folder already says what a class is; the suffix is what says it at the call site, in a stack
 * trace, and in a search for every job in the codebase. A `Jobs/CleanupOldFiles` reads as a helper
 * everywhere except the one directory listing that would have told you otherwise.
 *
 * Checked by folder, because the folder is the only mechanical signal of what kind a class is. Two
 * things follow from that:
 *
 *   - `Services/` is deliberately absent. DA-16 owns that folder through {@see ServiceNaming},
 *     which knows about the contract-implementation exception this check could not see.
 *   - Traits are skipped. PHP-17 leaves them unsuffixed — a trait reads as a capability where it
 *     is `use`d — and they sit in `Concerns/` and `Traits/` folders inside the kinds below.
 *
 * The kinds PHP-17 deliberately leaves unsuffixed — entities, models, enums, events, middleware,
 * contracts — are simply not in the map. Their folders are not checked at all, so nothing here can
 * push somebody into renaming a `User` to `UserEntity`.
 */
final class ClassNaming extends DomainInsight implements HasDetails
{
    /**
     * Folder fragment => the suffixes a class in it may end with.
     *
     * Order matters: the first match wins, so `/Http/Resources/` is tested before any shorter
     * fragment could swallow it.
     *
     * @var array<string, string[]>
     */
    private const KINDS = [
        '/Http/Controllers/' => ['Controller'],
        '/Http/Requests/' => ['Request'],
        // `{Noun}ResourceCollection` is the house spelling of Laravel's ResourceCollection, used
        // by every paginated response here. Both are legitimate; a bare `{Noun}Collection` is not.
        '/Http/Resources/' => ['Resource', 'ResourceCollection'],
        '/Repositories/' => ['Repository', 'RepositoryInterface'],
        '/Jobs/' => ['Job'],
        '/Listeners/' => ['Listener'],
        '/Subscribers/' => ['Subscriber'],
        '/Console/Commands/' => ['Command'],
        '/Notifications/' => ['Notification'],
        // Mailables are `{WhatHappened}Email` here rather than Laravel's unsuffixed default.
        '/Mail/' => ['Email'],
        '/Policies/' => ['Policy'],
        '/Observers/' => ['Observer'],
        '/Providers/' => ['Provider'],
        '/Exceptions/' => ['Exception'],
        '/Broadcasting/' => ['Channel'],
        '/Rules/' => ['Rule'],
        '/Casts/' => ['Cast'],
    ];

    /**
     * Framework classes whose name Laravel fixes for us. Renaming them is not an option, so they
     * are exempt rather than grandfathered — the list will not shrink.
     *
     * @var string[]
     */
    private const FRAMEWORK_FIXED = [
        'app/Exceptions/Handler.php',
    ];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Framework classes carry the suffix of their kind (PHP-17)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $relative = $this->relative($path);

            if (in_array($relative, self::FRAMEWORK_FIXED, true) || $this->isGrandfathered($path)) {
                continue;
            }

            $suffixes = $this->suffixesFor($relative);

            if ($suffixes === null) {
                continue;
            }

            $source = (string) file_get_contents($path);

            if ($this->isTrait($source)) {
                continue;
            }

            $name = $this->declaredName($source);

            if ($name === null || $this->endsWithAny($name, $suffixes)) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    '`%s` must be named `{Noun}%s`. Rename it, or move it out of that folder if it '
                    . 'is not one (PHP-17).',
                    $name,
                    $suffixes[0]
                ));
        }

        return $details;
    }

    /**
     * The suffixes a file's folder demands, or null when the folder names no framework kind.
     *
     * @return string[]|null
     */
    private function suffixesFor(string $relative): ?array
    {
        foreach (self::KINDS as $folder => $suffixes) {
            if (str_contains('/' . $relative, $folder)) {
                return $suffixes;
            }
        }

        return null;
    }

    private function isTrait(string $source): bool
    {
        return preg_match('/^\s*trait\s+\w+/m', $source) === 1;
    }

    /**
     * @param string[] $suffixes
     */
    private function endsWithAny(string $name, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }
}

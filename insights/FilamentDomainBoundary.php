<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;

/**
 * DA-22 — Filament reads domain models, and reaches everything else through interfaces.
 *
 * This is the half of DA-22 that {@see CrossDomainBoundary} cannot enforce. That rule treats any
 * reference to another domain's `Models/` as a crossing, which is right everywhere except here:
 * building a table or a form on an Eloquent model is what Filament *is*, and DA-22 blesses it
 * outright. So the admin panel needs its own rule with the model exemption built in, rather than a
 * blanket grandfather entry that would excuse the state changes too.
 *
 * What it protects is not boundary hygiene, it is having one implementation of each rule. A user
 * deleted through the admin panel must fire the same events, respect the same invariants and revoke
 * the same tokens as one deleted through the API. The moment the panel calls a concrete
 * service, there are two implementations of that rule and only one of them is tested.
 *
 * The model exemption follows the class, not the folder depth. `Billing\Integrations\Erp\Models\
 * Invoice` is an Eloquent model that happens to live four segments deep, and a relation manager reading it is doing exactly what this rule blesses — keying on the
 * first segment alone would report it as an `Integrations/` crossing and teach people that the
 * exemption is arbitrary.
 *
 * Detection is the tokenizer approach {@see CrossDomainBoundary::domainReferences()} explains: a
 * dependency is a dependency however it is spelled, and a `@see` in a docblock is not one.
 */
final class FilamentDomainBoundary extends DomainInsight implements HasDetails
{
    /**
     * Folders whose classes are part of a domain's public surface (DA-01), plus `Models`, which
     * only this rule adds — DA-22 permits the panel to read them.
     */
    private const READABLE = ['Contracts', 'Entities', 'Enums', 'Events', 'Exceptions', 'Models'];

    private const PREFIX = 'App\\Domains\\';

    private const ROOT = 'app/Filament/';

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Filament may read a domain\'s models, but triggers its behaviour through interfaces (DA-22)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $relative = $this->relative($path);

            if (! str_starts_with($relative, self::ROOT)) {
                continue;
            }

            $allowedHere = $this->allowedImportsFor($relative);

            foreach ($this->domainReferences((string) file_get_contents($path)) as $fqcn) {
                $segments = explode('\\', substr($fqcn, strlen(self::PREFIX)));
                array_shift($segments);
                $folder = $segments[0] ?? '';
                $class = end($segments);

                if ($this->isReadable($segments, $folder)) {
                    continue;
                }

                if ($folder === 'Services' && str_ends_with((string) $class, 'Interface')) {
                    continue;
                }

                // DA-20: a Resource is a shape, not behaviour. None exist here today; the
                // exemption is stated so that the rule and DA-20 cannot disagree later.
                if ($folder === 'Http' && ($segments[1] ?? '') === 'Resources') {
                    continue;
                }

                // Per import, not per file — excusing one crossing must never quietly excuse a
                // second one nobody has looked at.
                if (in_array($fqcn, $allowedHere, true)) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        '`%s` depends on `%s`. Filament may read a domain\'s models, but everything '
                        . 'else goes through a service interface (DA-22) — otherwise the panel is a '
                        . 'second implementation of a rule the API already owns.',
                        $relative,
                        $fqcn
                    ));
            }
        }

        return $details;
    }

    /**
     * @param  string[]  $segments
     */
    private function isReadable(array $segments, string $folder): bool
    {
        if (in_array($folder, self::READABLE, true)) {
            return true;
        }

        // A model nested under another folder is still a model — see the class docblock.
        return in_array('Models', $segments, true);
    }

    /**
     * Every distinct `App\Domains\…` name the file actually depends on.
     *
     * @return string[]
     */
    private function domainReferences(string $source): array
    {
        $found = [];

        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                continue;
            }

            if (! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $name = ltrim($token[1], '\\');

            if (str_starts_with($name, self::PREFIX)) {
                $found[$name] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * Imports excused for one file, keyed by its exact repository-relative path.
     *
     * The lookup is exact rather than the `str_contains` prefix match in
     * {@see DomainInsight::isGrandfathered()}, so a directory-prefix key silently matches nothing.
     *
     * @return string[]
     */
    private function allowedImportsFor(string $relative): array
    {
        /** @var array<string, string[]> $map */
        $map = $this->config['grandfathered'] ?? [];

        return $map[$relative] ?? [];
    }
}

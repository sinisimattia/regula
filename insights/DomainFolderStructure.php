<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;

/**
 * DA-05 / DA-06 / DA-18 — a domain is a Laravel application plus our own concepts, it carries a
 * README and Docs, and any folder outside that set is named in both, in code or in a heading.
 *
 * Domain-specific folders are legitimate. An *undocumented* one is not: the next person cannot tell
 * a deliberate structure from an accident, so they copy it.
 */
final class DomainFolderStructure extends DomainInsight implements HasDetails
{
    /** Standard Laravel application folders. */
    private const LARAVEL = [
        'Broadcasting', 'Casts', 'Console', 'Events', 'Exceptions', 'Facades', 'Http', 'Jobs',
        'Listeners', 'Mail', 'Models', 'Notifications', 'Observers', 'Policies', 'Providers',
        'Rules', 'Subscribers', 'Traits',
    ];

    /** Our own concepts, layered on top. */
    private const OURS = [
        'Contracts', 'Docs', 'Entities', 'Enums', 'Helpers', 'Integrations', 'Repositories',
        'Services',
    ];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Domain folders are standard Laravel plus our concepts; anything else is documented (DA-05, DA-06)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];
        $allowed = array_merge(self::LARAVEL, self::OURS);

        foreach ($this->analysedDomains() as $domain) {
            $root = self::DOMAIN_ROOT . $domain;

            $readme = is_file($root . '/README.md') ? (string) file_get_contents($root . '/README.md') : '';
            $docs = $this->docsContents($root);

            // The README/Docs requirement ratchets: required for new domains and for any domain
            // whose behaviour you change. Domains listed as exempt are debt, not permission — and
            // they are still held to every folder rule below.
            $documentationExempt = in_array($domain, $this->documentationExempt(), true);

            if ($readme === '' && ! $documentationExempt) {
                $details[] = Details::make()
                    ->setFile($root)
                    ->setMessage(sprintf('Domain `%s` has no README.md (DA-18).', $domain));
            }

            if ($docs === '' && ! $documentationExempt) {
                $details[] = Details::make()
                    ->setFile($root)
                    ->setMessage(sprintf(
                        'Domain `%s` has no Docs/ folder (DA-18). Required for new domains, and '
                        . 'for any domain whose behaviour you change.',
                        $domain
                    ));
            }

            foreach ((array) glob($root . '/*', GLOB_ONLYDIR) as $directory) {
                $folder = basename((string) $directory);

                if (in_array($folder, $allowed, true)) {
                    continue;
                }

                if ($this->names($readme, $folder) && $this->names($docs, $folder)) {
                    continue;
                }

                if ($this->isGrandfathered((string) $directory)) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile((string) $directory)
                    ->setMessage(sprintf(
                        '`%1$s/` is specific to this domain, which is allowed — but it must be '
                        . 'explained in the domain README *and* Docs/, naming it as `%1$s/` in code '
                        . 'or in a heading (DA-06).',
                        $folder
                    ));
            }
        }

        return $details;
    }

    /**
     * Domains not yet required to carry a README and Docs. Expected to shrink to nothing.
     *
     * @return string[]
     */
    private function documentationExempt(): array
    {
        /** @var string[] $domains */
        $domains = $this->config['documentation_exempt'] ?? [];

        return $domains;
    }

    /**
     * @return string[]
     */
    private function analysedDomains(): array
    {
        $domains = [];

        foreach ($this->analysedFiles() as $path) {
            $domain = $this->domainOf($path);

            if ($domain !== null) {
                $domains[$domain] = true;
            }
        }

        return array_keys($domains);
    }

    /**
     * Whether a Markdown text names the folder deliberately: as a code span — `Strategies/`,
     * `Strategies`, or a path ending in it — or in a heading. A passing mention of the word in
     * prose does not count, because it does not tell the reader the folder exists.
     */
    private function names(string $markdown, string $folder): bool
    {
        $quoted = preg_quote($folder, '/');

        return preg_match('/`(?:[^`\s]*\/)?' . $quoted . '\/?`/', $markdown) === 1
            || preg_match('/^#{1,6}\s.*\b' . $quoted . '\b/m', $markdown) === 1;
    }

    private function docsContents(string $root): string
    {
        $combined = '';

        foreach ((array) glob($root . '/Docs/*.md') as $doc) {
            $combined .= (string) file_get_contents((string) $doc);
        }

        return $combined;
    }
}

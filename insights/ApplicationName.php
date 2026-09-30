<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * PHP-22 — the application's name is written only as the vendor part of `name` in composer.json.
 * Every other file in the repository is searched for it, case-insensitively and without word
 * boundaries, except generated, installed and local-only files.
 */
final class ApplicationName extends DomainInsight implements HasDetails
{
    /**
     * Directories that are generated, installed or local-only, relative to the project root.
     */
    private const IGNORED_DIRECTORIES = [
        '.git', '.idea', '.phpunit.cache', 'vendor', 'node_modules', 'storage', 'coverage',
        'bootstrap/cache', 'cloud/aws/cdk.out', 'public/css/filament', 'public/js/filament',
        'public/fonts/filament',
    ];

    /**
     * Files that are generated or local-only, relative to the project root.
     */
    private const IGNORED_FILES = [
        'composer.json', 'composer.lock', 'cloud/aws/package-lock.json', '.env',
        '.phpunit.result.cache', '.php-cs-fixer.cache',
    ];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'The application name is written only in composer.json (PHP-22)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $root = (string) getcwd();
        $applicationName = $this->applicationName($root . '/composer.json');

        if ($applicationName === '') {
            return [];
        }

        // No word boundaries: `name_session`, `NameBackend` and `my-name` are all mentions.
        $pattern = '/' . preg_quote($applicationName, '/') . '/i';
        $details = [];

        foreach ($this->projectFiles($root) as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($file->getPathname())
                ->setMessage(sprintf(
                    'Line %d spells out the application name. Derive it instead: config(\'app.name\') in '
                    . 'PHP, composer.json in the CDK, `{app}` in documentation (PHP-22).',
                    substr_count(substr($contents, 0, $match[0][1]), "\n") + 1
                ));
        }

        return $details;
    }

    /**
     * The vendor part of the `name` in composer.json, or an empty string when there is none.
     */
    private function applicationName(string $manifestPath): string
    {
        if (! is_file($manifestPath)) {
            return '';
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), associative: true);

        return is_array($manifest) && is_string($manifest['name'] ?? null) ? explode('/', $manifest['name'])[0] : '';
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function projectFiles(string $root): iterable
    {
        $filtered = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            function (SplFileInfo $file) use ($root): bool {
                $relativePath = substr($file->getPathname(), strlen($root) + 1);

                if ($file->isDir()) {
                    return ! in_array($relativePath, self::IGNORED_DIRECTORIES, true)
                        && basename($relativePath) !== 'node_modules';
                }

                return ! in_array($relativePath, self::IGNORED_FILES, true) && ! $this->isCompiledInfrastructure($relativePath);
            },
        );

        return new RecursiveIteratorIterator($filtered);
    }

    /**
     * The CDK's TypeScript compiles in place to gitignored .js and .d.ts files next to its sources.
     */
    private function isCompiledInfrastructure(string $relativePath): bool
    {
        return str_starts_with($relativePath, 'cloud/aws/')
            && (str_ends_with($relativePath, '.d.ts') || (str_ends_with($relativePath, '.js') && ! str_ends_with($relativePath, 'config.js')));
    }
}

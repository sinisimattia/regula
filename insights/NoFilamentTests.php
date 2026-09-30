<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use SimpleXMLElement;

/**
 * TEST-05 — nothing under `app/Filament/` is tested, and it stays out of coverage.
 *
 * Flags a test file whose path mentions Filament, a test that names an `App\Filament\` class,
 * and a phpunit.xml whose `<source><exclude>` does not exclude `app/Filament`.
 */
final class NoFilamentTests extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const NAMESPACE = 'App\Filament\\';

    public function getTitle(): string
    {
        return 'Nothing under app/Filament is tested (TEST-05)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $root = (string) getcwd();
        $details = [];

        foreach ($this->filesUnder(['tests']) as $path) {
            if (str_contains($this->relative($path), 'Filament')) {
                $details[] = $this->detail($path, 'This test file is about the admin panel. Test the domain service '
                    . 'behind it instead; app/Filament is never tested (TEST-05).');

                continue;
            }

            $lines = array_unique(array_map(fn (Node $node): int => $node->getStartLine(), $this->findIn($path, fn (Node $node): bool => $this->namesFilament($node))));

            foreach ($lines as $line) {
                $details[] = $this->detail($path, sprintf(
                    'Line %d names an App\Filament class. Test the domain service behind it instead; '
                    . 'app/Filament is never tested (TEST-05).',
                    $line,
                ));
            }
        }

        if (! $this->excludesFilamentFromCoverage($root . '/phpunit.xml')) {
            $details[] = $this->detail($root . '/phpunit.xml', 'phpunit.xml does not exclude app/Filament in '
                . '<source><exclude>. Add `<directory suffix=".php">app/Filament</directory>` (TEST-05).');
        }

        return $details;
    }

    private function namesFilament(Node $node): bool
    {
        if ($node instanceof Name) {
            return str_starts_with(ltrim($node->toString(), '\\') . '\\', self::NAMESPACE);
        }

        return $node instanceof String_ && str_starts_with(ltrim($node->value, '\\'), self::NAMESPACE);
    }

    private function excludesFilamentFromCoverage(string $phpunit): bool
    {
        $xml = is_file($phpunit) ? @simplexml_load_file($phpunit) : false;

        if (! $xml instanceof SimpleXMLElement) {
            return false;
        }

        foreach ($xml->xpath('/phpunit/source/exclude/directory') ?: [] as $directory) {
            if (rtrim(trim((string) $directory), '/') === 'app/Filament') {
                return true;
            }
        }

        return false;
    }
}

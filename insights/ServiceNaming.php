<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;

/**
 * DA-16 — classes in a domain's Services/ folder are named {Noun}Service, and the ones other
 * domains call add {Noun}ServiceInterface.
 *
 * A bare noun in Services/ reads as a helper and gets used like one. The suffix is what tells a
 * reader, without opening the file, that this is the domain's entry point rather than a utility.
 */
final class ServiceNaming extends DomainInsight implements HasDetails
{
    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Classes in Services/ must be named {Noun}Service or {Noun}ServiceInterface (DA-16)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->domainOf($path) === null || ! str_contains($this->relative($path), '/Services/')) {
                continue;
            }

            if ($this->isGrandfathered($path)) {
                continue;
            }

            $source = (string) file_get_contents($path);
            $name = $this->declaredName($source);

            if ($name === null || str_ends_with($name, 'Service') || str_ends_with($name, 'ServiceInterface')) {
                continue;
            }

            // A contract implementation takes the name of the role it fills (DA-16). Adding
            // "Service" to it would bury the one word that tells the reader what it is.
            if ($this->implementsContract($source)) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    '`%s` sits in Services/ but is not named {Noun}Service. Rename it, or move it '
                    . 'if it is not a service.',
                    $name
                ));
        }

        return $details;
    }

    /**
     * Whether the class implements an interface declared in some domain's Contracts/ folder.
     */
    private function implementsContract(string $source): bool
    {
        if (preg_match('/\bimplements\s+([^{]+)/', $source, $match) !== 1) {
            return false;
        }

        foreach (explode(',', $match[1]) as $interface) {
            $short = basename(str_replace('\\', '/', trim($interface)));

            if ($short === '') {
                continue;
            }

            $matches = glob(self::DOMAIN_ROOT . '*/Contracts/' . $short . '.php');

            if (is_array($matches) && $matches !== []) {
                return true;
            }
        }

        return false;
    }
}

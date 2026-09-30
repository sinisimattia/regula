<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Stmt\Class_;

/**
 * DA-14 — the public surface of an exported service is exactly its interface.
 *
 * For every class in a domain's `Services/` that implements an interface, each public method it
 * declares must appear on one of its interfaces. Constructors and magic methods are exempt.
 * Methods inherited from a parent class or a trait are not inspected.
 */
final class ServicePublicSurface extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'A service\'s public methods are exactly those on its interface (DA-14)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->layerOf($path) !== 'Services' || $this->isGrandfathered($path)) {
                continue;
            }

            foreach ($this->classesIn($path) as $class) {
                if (! $class instanceof Class_ || $class->implements === []) {
                    continue;
                }

                $declared = [];

                foreach ($class->implements as $interface) {
                    $methods = $this->interfaceMethods($interface->toString());

                    // An interface nobody can find cannot be checked against; say nothing rather than guess.
                    if ($methods === null) {
                        continue 2;
                    }

                    $declared = array_merge($declared, $methods);
                }

                foreach ($class->getMethods() as $method) {
                    $name = $method->name->toString();

                    if (! $method->isPublic() || str_starts_with($name, '__') || in_array(strtolower($name), $declared, true)) {
                        continue;
                    }

                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d: `%s::%s()` is public but on none of its interfaces. Add it to the '
                            . 'interface if consumers need it, otherwise make it private (DA-14).',
                            $method->getStartLine(),
                            (string) $class->name,
                            $name
                        ));
                }
            }
        }

        return $details;
    }
}

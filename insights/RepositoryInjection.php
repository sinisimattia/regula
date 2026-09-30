<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Param;

/**
 * DA-04 / DB-04 — a repository is typed or resolved only in a service of its own domain.
 *
 * A registry may still bind one, by the wiring test in {@see InspectsDomainCode::isFrameworkRegistry()};
 * app/Filament is left to {@see FilamentDomainBoundary}.
 */
final class RepositoryInjection extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'A repository is injected only into services of its own domain (DA-04, DB-04)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $relative = $this->relative($path);

            if ($this->isGrandfathered($path) || str_starts_with($relative, 'app/Filament/')) {
                continue;
            }

            $wiring = $this->isFrameworkRegistry($path) ? $this->wiringArgumentNodes($path) : [];

            foreach ($this->findIn($path, fn (Node $node): bool => $this->repositoryNamed($node) !== []) as $node) {
                if (isset($wiring[spl_object_id($node)])) {
                    continue;
                }

                foreach ($this->repositoryNamed($node) as $repository) {
                    if ($this->mayInject($path, $repository)) {
                        continue;
                    }

                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d takes `%s`. Only a service of the repository\'s own domain may; '
                            . 'anything else injects that domain\'s service interface (DA-04, DB-04).',
                            $node->getStartLine(),
                            $repository
                        ));
                }
            }
        }

        return $details;
    }

    private function mayInject(string $path, string $repository): bool
    {
        $domain = $this->domainOf($path);

        return $domain !== null
            && $this->layerOf($path) === 'Services'
            && str_starts_with($repository, 'App\\Domains\\' . $domain . '\\');
    }

    /**
     * The repositories a node injects: a parameter's type, or the class handed to the container.
     *
     * @return string[]
     */
    private function repositoryNamed(Node $node): array
    {
        if ($node instanceof Param) {
            return array_values(array_filter($this->typeNames($node->type), $this->isRepository(...)));
        }

        $class = $this->resolvedClass($node);

        return $class !== null && $this->isRepository($class) ? [$class] : [];
    }

    private function isRepository(string $name): bool
    {
        return str_ends_with($name, 'Repository') || str_ends_with($name, 'RepositoryInterface');
    }
}

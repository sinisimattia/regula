<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;

/**
 * DA-10 / DA-11 — `Contracts/` holds interfaces or abstract bases for *other* domains to implement.
 *
 * Every file there declares an interface or an abstract class, and no class in the declaring
 * domain implements or extends one of its own contracts: if it does, the type belongs beside its
 * implementation in `Services/` or `Repositories/`.
 */
final class ContractsFolder extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Contracts/ holds interfaces and abstract bases that other domains implement (DA-10, DA-11)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $domain = $this->domainOf($path);

            if ($domain === null || $this->isGrandfathered($path)) {
                continue;
            }

            if ($this->layerOf($path) === 'Contracts') {
                $details = array_merge($details, $this->nonContracts($path));

                continue;
            }

            $ownContracts = 'App\\Domains\\' . $domain . '\\Contracts\\';

            foreach ($this->classesIn($path) as $class) {
                if (! $class instanceof Class_) {
                    continue;
                }

                $supertypes = array_merge($class->extends !== null ? [$class->extends] : [], $class->implements);

                foreach ($supertypes as $supertype) {
                    /** @var Name $supertype */
                    if (! str_starts_with($supertype->toString(), $ownContracts)) {
                        continue;
                    }

                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            '`%s` implements `%s`, a contract of its own domain. A contract is for other '
                            . 'domains to implement; if this domain implements it, move it beside its '
                            . 'implementation in `Services/` or `Repositories/` (DA-11).',
                            (string) $class->name,
                            $supertype->toString()
                        ));
                }
            }
        }

        return $details;
    }

    /**
     * @return Details[]
     */
    private function nonContracts(string $path): array
    {
        $declarations = $this->classesIn($path);
        $contracts = array_filter(
            $declarations,
            static fn ($node): bool => $node instanceof Interface_ || ($node instanceof Class_ && $node->isAbstract())
        );

        if ($declarations !== [] && count($contracts) === count($declarations)) {
            return [];
        }

        return [Details::make()
            ->setFile($path)
            ->setMessage(
                'Everything in `Contracts/` is an interface or an abstract class other domains implement. '
                . 'Move a concrete class, trait or enum to the folder its kind belongs in (DA-10).'
            )];
    }
}

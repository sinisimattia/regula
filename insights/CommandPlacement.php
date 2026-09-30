<?php

declare(strict_types=1);

namespace Insights;

use Illuminate\Console\Command;
use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Stmt\Class_;

/**
 * DA-09 — a domain's console commands live in its `Console/Commands/`.
 */
final class CommandPlacement extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Console commands live in Console/Commands/ (DA-09)';
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

            if (str_starts_with($this->relative($path), self::DOMAIN_ROOT . $domain . '/Console/Commands/')) {
                continue;
            }

            foreach ($this->classesIn($path) as $class) {
                $fqcn = $this->fqcnOf($class);

                if (! $class instanceof Class_ || $fqcn === null || ! $this->descendsFrom($fqcn, Command::class)) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        '`%s` is a console command. Move it to `%s/Console/Commands/` (DA-09).',
                        (string) $class->name,
                        self::DOMAIN_ROOT . $domain
                    ));
            }
        }

        return $details;
    }
}

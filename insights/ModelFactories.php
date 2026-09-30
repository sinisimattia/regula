<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Stmt\Class_;

/**
 * DB-09 / DB-10 — every model has a factory, and factories live flat in `database/factories/`.
 *
 * A model backing an external store the suite never reaches is exempt when listed, by fully
 * qualified name, under this insight's `external` config.
 */
final class ModelFactories extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const FACTORIES = 'database/factories';

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Every model has a factory, flat in database/factories/ (DB-09, DB-10)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ((array) glob(self::FACTORIES . '/*', GLOB_ONLYDIR) as $directory) {
            $details[] = Details::make()
                ->setFile((string) $directory)
                ->setMessage(sprintf(
                    '`%s/` nests factories. Move them flat into `%s/`, where Laravel\'s factory '
                    . 'resolution expects them (DB-10).',
                    $directory,
                    self::FACTORIES
                ));
        }

        /** @var string[] $external */
        $external = $this->config['external'] ?? [];

        foreach ($this->classIndex() as $fqcn => ['path' => $path, 'node' => $class]) {
            if (! $class instanceof Class_ || $class->isAbstract() || in_array($fqcn, $external, true)) {
                continue;
            }

            if ($this->isGrandfathered($path) || ! $this->isEloquentModel($fqcn)) {
                continue;
            }

            $factory = self::FACTORIES . '/' . $class->name . 'Factory.php';

            if (is_file($factory)) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    '`%s` has no factory. Add `%s` — or, if it backs an external store the suite never '
                    . 'reaches, list it under `external` for this insight in config/insights.php (DB-09).',
                    $class->name,
                    $factory
                ));
        }

        return $details;
    }
}

<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Trait_;

/**
 * PHP-17, the kinds that deliberately take no suffix: an enum, a trait, and a class in `Entities/`,
 * `Models/`, `Events/` or `Middleware/` names the thing itself, so `TokenAbilityEnum`, `UserEntity`
 * or `UserRegisteredEvent` is a violation.
 */
final class UnsuffixedKinds extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'database', 'tests'];

    /**
     * Folder fragment => the suffix a class in it must not carry.
     */
    private const FOLDERS = [
        '/Entities/' => 'Entity',
        '/Models/' => 'Model',
        '/Events/' => 'Event',
        '/Middleware/' => 'Middleware',
    ];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Enums, traits, entities, models, events and middleware take no suffix (PHP-17)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            foreach ($this->classLikesIn($path) as $class) {
                $name = $class->name?->toString();
                $suffix = match (true) {
                    $name === null => null,
                    $class instanceof Enum_ => 'Enum',
                    $class instanceof Trait_ => 'Trait',
                    $class instanceof Class_ => $this->folderSuffix($this->relative($path)),
                    default => null,
                };

                if ($name === null || $suffix === null || $name === $suffix || ! str_ends_with($name, $suffix)) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        '`%s` carries the `%s` suffix, which this kind does not take: the name is the '
                        . 'concept. Rename it to `%s` (PHP-17).',
                        $name,
                        $suffix,
                        substr($name, 0, -strlen($suffix))
                    ));
            }
        }

        return $details;
    }

    private function folderSuffix(string $relative): ?string
    {
        foreach (self::FOLDERS as $folder => $suffix) {
            if (str_contains('/' . $relative, $folder)) {
                return $suffix;
            }
        }

        return null;
    }
}

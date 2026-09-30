<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Stmt\EnumCase;

/**
 * PHP-07 — enum cases are TitleCase: `FavoritePerson`, `Monthly`, `AwaitingReview`.
 */
final class EnumCaseNaming extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'database', 'routes', 'tests'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Enum cases are TitleCase (PHP-07)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            /** @var EnumCase[] $cases */
            $cases = $this->findIn($path, static fn (Node $node): bool => $node instanceof EnumCase);

            foreach ($cases as $case) {
                $name = $case->name->toString();

                if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) === 1) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d: enum case `%s` is not TitleCase. Rename it to `%s` (PHP-07).',
                        $case->getStartLine(),
                        $name,
                        str_replace(' ', '', ucwords(strtolower(str_replace(['_', '-'], ' ', $name))))
                    ));
            }
        }

        return $details;
    }
}

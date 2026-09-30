<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Comment\Doc;
use PhpParser\Node;

/**
 * PHP-25 — `@throws` is the last tag in a docblock.
 *
 * PHP-CS-Fixer's `phpdoc_order` moves it below `@param` and `@return`; this catches any other tag
 * left after it.
 */
final class ThrowsLast extends DomainInsight implements HasDetails
{
    private const ROOTS = ['app', 'database', 'routes', 'tests'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return '@throws is the last tag in a docblock (PHP-25)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->filesUnder(self::ROOTS) as $path) {
            $seen = [];

            foreach ($this->findIn($path, fn (Node $node): bool => $node->getDocComment() !== null) as $node) {
                $doc = $node->getDocComment();

                if (! $doc instanceof Doc || isset($seen[$doc->getStartLine()]) || ! $this->tagFollowsThrows($doc->getText())) {
                    continue;
                }

                $seen[$doc->getStartLine()] = true;
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'The docblock on line %d has a tag after @throws. Move @throws to the end (PHP-25).',
                        $doc->getStartLine()
                    ));
            }
        }

        return $details;
    }

    private function tagFollowsThrows(string $text): bool
    {
        $throwsSeen = false;

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (preg_match('/^\s*\*?\s*@([\w-]+)/', $line, $match) !== 1) {
                continue;
            }

            if (strtolower($match[1]) === 'throws') {
                $throwsSeen = true;
            } elseif ($throwsSeen) {
                return true;
            }
        }

        return false;
    }
}

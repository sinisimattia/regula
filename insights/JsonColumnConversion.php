<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;

/**
 * DB-12 — a `json` column is never converted to `jsonb`.
 *
 * Flags `->jsonb(...)->change()` and raw SQL that alters a column `TYPE jsonb` or casts one
 * `USING …::jsonb`. Creating a new `jsonb` column is not a conversion and is left alone.
 */
final class JsonColumnConversion extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const RAW_CONVERSION = ['/\bTYPE\s+jsonb\b/i', '/\bUSING\b[^;]*::\s*jsonb\b/i'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'A json column is never converted to jsonb (DB-12)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->filesUnder(['database/migrations']) as $path) {
            foreach ($this->findIn($path, fn (Node $node): bool => $this->converts($node)) as $node) {
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d converts a column to `jsonb`. `jsonb` re-sorts object keys, so stored data '
                        . 'silently comes back in a different order; keep it `json` (DB-12, ENT-04).',
                        $node->getStartLine()
                    ));
            }
        }

        return $details;
    }

    private function converts(Node $node): bool
    {
        if ($node instanceof MethodCall && $node->name instanceof Identifier && $node->name->toString() === 'change') {
            return $this->chainCalls($node->var, 'jsonb');
        }

        $sql = match (true) {
            $node instanceof String_ => $node->value,
            $node instanceof InterpolatedString => implode(' ', array_map(
                static fn (Node $part): string => $part instanceof InterpolatedStringPart ? $part->value : '?',
                $node->parts
            )),
            default => null,
        };

        if ($sql === null) {
            return false;
        }

        foreach (self::RAW_CONVERSION as $pattern) {
            if (preg_match($pattern, $sql) === 1) {
                return true;
            }
        }

        return false;
    }

    private function chainCalls(Expr $expression, string $method): bool
    {
        while ($expression instanceof MethodCall) {
            if ($expression->name instanceof Identifier && $expression->name->toString() === $method) {
                return true;
            }

            $expression = $expression->var;
        }

        return false;
    }
}

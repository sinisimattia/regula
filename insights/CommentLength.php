<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;

/**
 * PHP-19 — a comment is at most two sentences; a docblock, a `/* … *\/` block or a run of `//`
 * lines each counts as one.
 *
 * Tags, list items, table rows, code, URLs and, in config/ only, the framework's `|-----` banners
 * are not counted.
 */
final class CommentLength extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'config', 'database', 'routes', 'tests'];

    private const MAXIMUM_SENTENCES = 2;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'A comment is one or two sentences at most (PHP-19)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            if ($this->isPublishedConfig($path)) {
                continue;
            }

            $bannersAllowed = str_starts_with($this->relative($path), 'config/');

            foreach ($this->commentBlocks((string) file_get_contents($path), $bannersAllowed) as $line => $text) {
                $sentences = $this->sentenceCount($text);

                if ($sentences <= self::MAXIMUM_SENTENCES) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d: the comment runs to %d sentences. Cut it to one or two; reasoning '
                        . 'belongs in the domain\'s Docs/ or docs/infrastructure/, linked from here (PHP-19).',
                        $line,
                        $sentences
                    ));
            }
        }

        return $details;
    }

    /**
     * Each comment's text, stripped of its comment markers, keyed by the line it starts on.
     *
     * @return array<int, string>
     */
    private function commentBlocks(string $source, bool $bannersAllowed): array
    {
        $blocks = [];
        $tokens = token_get_all($source);
        $previousLineComment = null;

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                // Only code or a blank line ends a run of `//` lines.
                if (! is_array($token) || $token[0] !== T_WHITESPACE || substr_count($token[1], "\n") > 1) {
                    $previousLineComment = null;
                }

                continue;
            }

            [, $text, $line] = $token;
            $isLineComment = $token[0] === T_COMMENT && ! str_starts_with($text, '/*');

            if (! $isLineComment) {
                if (! ($bannersAllowed && $this->isFrameworkBanner($text))) {
                    $blocks[$line] = $this->stripBlockMarkers($text);
                }

                $previousLineComment = null;

                continue;
            }

            $content = (string) preg_replace('#^(?://|\#)#', '', rtrim($text));

            $startsLine = $this->startsLine($tokens, $index);

            // A line comment continues the block above only when it starts its own line directly below.
            if ($startsLine && $previousLineComment !== null) {
                $blocks[$previousLineComment] .= "\n" . $content;
            } else {
                $blocks[$line] = $content;
                $previousLineComment = $startsLine ? $line : null;
            }
        }

        return $blocks;
    }

    /**
     * Whether nothing but indentation precedes the token on its line.
     *
     * @param  array<int, array{int, string, int}|string>  $tokens
     */
    private function startsLine(array $tokens, int $index): bool
    {
        $previous = $tokens[$index - 1] ?? null;

        if (is_array($previous) && $previous[0] === T_WHITESPACE && ! str_contains($previous[1], "\n")) {
            $previous = $tokens[$index - 2] ?? null;
        }

        if (! is_array($previous)) {
            return false;
        }

        return str_ends_with($previous[1], "\n")
            || ($previous[0] === T_WHITESPACE && str_contains($previous[1], "\n"))
            || $previous[0] === T_OPEN_TAG;
    }

    /**
     * A config file a package published, listed in `config/insights.php`: its comments are the
     * package's, like Laravel's banners.
     */
    private function isPublishedConfig(string $path): bool
    {
        /** @var string[] $published */
        $published = $this->config['published'] ?? [];

        return in_array($this->relative($path), $published, true);
    }

    /**
     * Whether a block comment is the `|---…---| Title |---…---|` banner Laravel generates above
     * each config option.
     */
    private function isFrameworkBanner(string $text): bool
    {
        return preg_match('/^[ \t]*\|-{5,}[ \t]*$/m', $text) === 1;
    }

    private function stripBlockMarkers(string $text): string
    {
        $text = (string) preg_replace(['#^/\*\*?#', '#\*/$#'], '', $text);

        return (string) preg_replace('#^[ \t]*\*[ \t]?#m', '', $text);
    }

    private function sentenceCount(string $text): int
    {
        $prose = [];
        $inFence = false;
        $bulletIndent = null;

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $trimmed = trim($line);
            $indent = strlen($line) - strlen(ltrim($line));

            if (str_starts_with($trimmed, '```')) {
                $inFence = ! $inFence;

                continue;
            }

            // Everything from the first tag down is tags and their descriptions.
            if (str_starts_with($trimmed, '@')) {
                break;
            }

            if ($inFence || str_starts_with($trimmed, '|')) {
                continue;
            }

            if (preg_match('/^(?:[-*+]|\d+[.)])\s+/', $trimmed) === 1) {
                $bulletIndent = $indent;

                continue;
            }

            if ($trimmed === '' || ($bulletIndent !== null && $indent <= $bulletIndent)) {
                $bulletIndent = null;
            }

            if ($bulletIndent === null) {
                $prose[] = $trimmed;
            }
        }

        $joined = implode(' ', $prose);
        $joined = (string) preg_replace(
            ['/\{@[^}]*\}/', '/`[^`]*`/', '#\b[a-z][a-z0-9+.-]*://\S+#i', '/\b(?:e\.g|i\.e|etc|vs|cf|approx)\./i'],
            ' ',
            $joined
        );

        $sentences = preg_match_all('/[.!?]+(?=\s|$)/', $joined);
        $afterLast = (string) preg_replace('/^.*[.!?](?=\s|$)/s', '', $joined);

        return (int) $sentences + (preg_match('/\pL/u', $afterLast) === 1 ? 1 : 0);
    }
}

<?php

declare(strict_types=1);

namespace Insights;

use FilesystemIterator;
use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * PHP-26 — colours and fonts are written only in config/theme.php. Views and stylesheets are read
 * as text, and PHP under `app/` as a syntax tree.
 */
final class ThemeTokens extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const MARKUP_DIRECTORIES = ['resources/views', 'resources/css'];

    /**
     * Stylesheets that cannot read config, relative to the project root.
     */
    private const EXEMPT_FILES = [
        // PHPUnit copies it verbatim into the coverage report.
        'resources/css/coverage.css',
    ];

    private const FILAMENT_COLOR = 'Filament\Support\Colors\Color';

    private const FONT_METHODS = ['font', 'monoFont', 'serifFont'];

    /**
     * Only the lengths CSS accepts, so `#10042` in prose is not a colour.
     */
    private const HEX_COLOR = '#(?:[0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b';

    /**
     * A hex colour as a CSS value, such as `color: #333` or `radial-gradient(#123456, #000)`.
     */
    private const HEX_VALUE = '/(?<![\w-])[a-z-]+\s*:[^;{}<>"\']*?(' . self::HEX_COLOR . ')/i';

    /**
     * A hex colour in an HTML colour attribute, as emails write `bgcolor="#ffffff"`.
     */
    private const HEX_ATTRIBUTE = '/(?<![\w-])(?:bg)?color\s*=\s*["\']\s*(' . self::HEX_COLOR . ')/i';

    /**
     * A quoted hex string, as a Blade `@php` block writes `'#ff6b6b'`. Anchors such as `href="#top"` are not colours.
     */
    private const HEX_STRING = '/(?<!href=)["\'](' . self::HEX_COLOR . ')["\']/i';

    /**
     * No space before the parenthesis, as CSS allows none, so `lab (` in prose is not a colour.
     */
    private const COLOR_FUNCTION = '/(?<![\w>$-])((?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\()/i';

    /**
     * A declaration whose value can hold a colour, custom properties such as `--accent: tomato` included.
     * A custom property takes no space before its colon, so a command option's `{--mode : …}` is not one.
     */
    private const COLOR_PROPERTY = '/(?<![\w-])(?:(?:color|background(?:-color|-image)?'
        . '|border(?:-(?:top|right|bottom|left|block|inline)(?:-(?:start|end))?)?(?:-color)?'
        . '|outline(?:-color)?|fill|stroke|box-shadow|text-shadow|caret-color|accent-color|text-decoration(?:-color)?'
        . '|text-emphasis(?:-color)?|column-rule(?:-color)?|scrollbar-color|stop-color|flood-color|lighting-color)\s*'
        . '|--[\w-]+):\s*([^;{}<>"\']*)/i';

    /**
     * `font-family`, and the `font` shorthand that also names one.
     */
    private const FONT_PROPERTY = '/(?<![\w-])(font(?:-family)?)\s*:\s*([^;{}<>\n]*)/i';

    /**
     * A font value that ends in a theme font, such as `700 1rem/1.5 var(--theme-font-sans)`.
     */
    private const THEME_FONT = '/^(?:.*\s)?var\(--theme-font-[\w-]+\)\s*(?:!important)?$/i';

    private const CSS_WIDE_KEYWORDS = ['inherit', 'initial', 'unset', 'revert', 'revert-layer'];

    private const NAMED_COLORS = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black',
        'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse',
        'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan',
        'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta',
        'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen',
        'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink',
        'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen',
        'fuchsia', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow',
        'grey', 'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender',
        'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan',
        'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon',
        'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue',
        'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine',
        'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue',
        'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream',
        'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange',
        'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred',
        'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple',
        'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell',
        'sienna', 'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen',
        'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white',
        'whitesmoke', 'yellow', 'yellowgreen',
    ];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Colours and fonts are written only in config/theme.php (PHP-26)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->markupFiles() as $path) {
            foreach ($this->literalsInMarkup((string) file_get_contents($path)) as [$line, $literal]) {
                $details[] = $this->detail($path, $line, $literal);
            }
        }

        foreach ($this->phpFilesIn(['app']) as $path) {
            foreach ($this->literalsInPhp($path) as [$line, $literal]) {
                $details[] = $this->detail($path, $line, $literal);
            }
        }

        return $details;
    }

    /**
     * Every Blade view and stylesheet under the markup directories, exempt and grandfathered files excluded.
     *
     * @return string[]
     */
    private function markupFiles(): array
    {
        $files = [];
        $root = (string) getcwd();

        foreach (self::MARKUP_DIRECTORIES as $directory) {
            if (! is_dir($root . '/' . $directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                $path = $file->getPathname();
                $isMarkup = str_ends_with($path, '.blade.php') || str_ends_with($path, '.css');

                if ($file->isFile() && $isMarkup && ! in_array($this->relative($path), self::EXEMPT_FILES, true) && ! $this->isGrandfathered($path)) {
                    $files[] = $path;
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The colour and font literals in a view or stylesheet, as line and literal pairs.
     *
     * @return list<array{int, string}>
     */
    private function literalsInMarkup(string $source): array
    {
        return $this->literalsIn($source, [self::HEX_VALUE, self::HEX_STRING]);
    }

    /**
     * The colour and font literals in CSS or HTML, as line and literal pairs. The patterns that also
     * match prose, such as `Order: #1234`, run only when given.
     *
     * @param  string[]  $prosePatterns
     * @return list<array{int, string}>
     */
    private function literalsIn(string $source, array $prosePatterns = []): array
    {
        $literals = [];

        foreach ([...$prosePatterns, self::HEX_ATTRIBUTE, self::COLOR_FUNCTION] as $pattern) {
            preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as [$literal, $offset]) {
                $literals[$offset] = [$this->lineAt($source, $offset), $literal];
            }
        }

        preg_match_all(self::COLOR_PROPERTY, $source, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$propertyValue, $offset]) {
            // A theme variable's name may hold a colour word, as `var(--theme-gray)` does; its fallback may not.
            $valueWithoutReferences = (string) preg_replace(['/url\([^)]*\)/i', '/var\(\s*--[\w-]+/i'], '', $propertyValue);
            preg_match_all('/' . self::HEX_COLOR . '/i', $valueWithoutReferences, $hexMatches);
            $words = preg_split('/[^a-z]+/', strtolower($valueWithoutReferences)) ?: [];
            $colorLiterals = [...$hexMatches[0], ...array_intersect($words, self::NAMED_COLORS)];

            if ($colorLiterals !== []) {
                $literals[$offset] = [$this->lineAt($source, $offset), $colorLiterals[0]];
            }
        }

        preg_match_all(self::FONT_PROPERTY, $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as [, [$propertyName], [$propertyValue, $offset]]) {
            // A Blade expression stops the match at its opening brace. An empty value is one, and so is the
            // family of a shorthand that stops there, because the family comes last.
            $fontValue = trim($propertyValue, " \t'\"");
            $endsInBlade = ($source[$offset + strlen($propertyValue)] ?? '') === '{';
            $isDerived = $fontValue === ''
                || (strtolower($propertyName) === 'font' && $endsInBlade && strpbrk($fontValue, ',\'"') === false)
                || in_array(strtolower($fontValue), self::CSS_WIDE_KEYWORDS, true)
                || preg_match(self::THEME_FONT, $fontValue) === 1;

            if (! $isDerived) {
                $literals[$offset] = [$this->lineAt($source, $offset), $propertyName . ': ' . trim($propertyValue)];
            }
        }

        ksort($literals);

        // A hex value is found by both HEX_VALUE and COLOR_PROPERTY, at different offsets.
        return array_values(array_unique($literals, SORT_REGULAR));
    }

    /**
     * The colour and font literals in a PHP file, as line and literal pairs.
     *
     * @return list<array{int, string}>
     */
    private function literalsInPhp(string $path): array
    {
        $literals = [];

        $palettes = $this->findIn($path, static fn (Node $node): bool => $node instanceof ClassConstFetch
            && $node->class instanceof Name
            && $node->class->toString() === self::FILAMENT_COLOR
            && $node->name instanceof Identifier
            && $node->name->toString() !== 'class');

        foreach ($palettes as $palette) {
            $literals[] = [$palette->getStartLine(), 'Color::' . $palette->name->toString()];
        }

        /** @var array<String_|InterpolatedStringPart> $stringLiterals */
        $stringLiterals = $this->findIn($path, static fn (Node $node): bool => $node instanceof String_ || $node instanceof InterpolatedStringPart);

        foreach ($stringLiterals as $stringLiteral) {
            // A heredoc's or nowdoc's text starts on the line after its opening `<<<`.
            $firstLine = $stringLiteral->getStartLine()
                + (in_array($stringLiteral->getAttribute('kind'), [String_::KIND_HEREDOC, String_::KIND_NOWDOC], true) ? 1 : 0);

            if (preg_match('/^\s*(?:' . self::HEX_COLOR . '|(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\(.*)\s*$/i', $stringLiteral->value) === 1) {
                $literals[] = [$firstLine, $stringLiteral->value];

                continue;
            }

            // CSS written inside a string, as `'style' => 'color: red'` or an HtmlString does.
            foreach ($this->literalsIn($stringLiteral->value) as [$lineInString, $literal]) {
                $literals[] = [$firstLine + $lineInString - 1, $literal];
            }
        }

        $fontCalls = $this->findIn($path, static fn (Node $node): bool => $node instanceof MethodCall
            && $node->name instanceof Identifier
            && in_array($node->name->toString(), self::FONT_METHODS, true)
            && ($node->args[0] ?? null) instanceof Arg
            && $node->args[0]->value instanceof String_);

        foreach ($fontCalls as $fontCall) {
            $literals[] = [$fontCall->name->getStartLine(), $fontCall->name->toString() . "('" . $fontCall->args[0]->value->value . "')"];
        }

        return $literals;
    }

    private function lineAt(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }

    private function detail(string $path, int $line, string $literal): Details
    {
        return Details::make()
            ->setFile($path)
            ->setMessage(sprintf(
                'Line %d writes %s. Take it from config/theme.php instead: var(--theme-…) in a browser view, '
                . 'config(\'theme.…\') in an email or in PHP (PHP-26).',
                $line,
                $literal
            ));
    }
}

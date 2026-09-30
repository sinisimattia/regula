<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;

/**
 * PHP-09 — a method implementing one of our interfaces carries no documentation of its own: the
 * interface is the contract, and a second copy drifts.
 *
 * `@throws` counts too, since PHP-09 names it first. A docblock holding only `{@inheritdoc}` or tool
 * directives (`@codeCoverageIgnore`, `@phpstan-…`, `@psalm-…`) is not counted.
 */
final class InterfaceDocblocks extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    /**
     * Tags that are not documentation of the contract.
     */
    private const IGNORED_TAGS = '/^@(?:inheritdoc|codeCoverageIgnore\w*|phpstan-\S+|psalm-\S+|noinspection)\b/i';

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Implementations leave their documentation on the interface (PHP-09)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(['app']) as $path) {
            foreach ($this->classLikesIn($path) as $class) {
                foreach ($class->getMethods() as $method) {
                    $doc = $method->getDocComment()?->getText();

                    if ($doc === null || ! $this->documents($doc)) {
                        continue;
                    }

                    $interface = $this->ourInterfaceDeclaring($this->declarationsAbove($class, $method->name->toString()));

                    if ($interface === null) {
                        continue;
                    }

                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d: `%s::%s()` implements `%s` but documents itself. Move anything '
                            . 'missing to the interface and delete this docblock (PHP-09).',
                            $method->getStartLine(),
                            $class->name?->toString() ?? 'class@anonymous',
                            $method->name->toString(),
                            substr((string) strrchr('\\' . $interface, '\\'), 1)
                        ));
                }
            }
        }

        return $details;
    }

    /**
     * @param  list<array{class: string, vendor: bool, interface: bool, params: list<array{name: string, typed: bool}>, returnTyped: bool}>  $declarations
     */
    private function ourInterfaceDeclaring(array $declarations): ?string
    {
        foreach ($declarations as $declaration) {
            if ($declaration['interface'] && str_starts_with($declaration['class'], 'App\\')) {
                return $declaration['class'];
            }
        }

        return null;
    }

    /**
     * Whether a docblock says anything beyond what this check ignores.
     */
    private function documents(string $doc): bool
    {
        $text = preg_replace('/\{@inheritdoc\}/i', '', $doc) ?? $doc;

        $inIgnoredTag = false;

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim(preg_replace('#^\s*(?:/\*\*|\*/|\*)?#', '', $line) ?? '');
            $line = trim(preg_replace('#\*/$#', '', $line) ?? '');

            // A tag's text runs on to the next tag, so a wrapped ignored tag stays ignored.
            if (str_starts_with($line, '@')) {
                $inIgnoredTag = preg_match(self::IGNORED_TAGS, $line) === 1;
            }

            if ($line === '' || $inIgnoredTag) {
                continue;
            }

            return true;
        }

        return false;
    }
}

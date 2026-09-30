<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;

/**
 * PHP-04 — a public method's `array` or `iterable` parameter or return says what it holds, in a
 * docblock type such as `User[]`, `array<string, mixed>`, `list<int>` or `array{id: int}`.
 *
 * A method declared above the class (an interface, a parent, vendor or ours) is exempt: its
 * contract is documented, or fixed, where it is declared.
 */
final class ArrayElementTypes extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Public array parameters and returns say what they hold (PHP-04)';
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
                    if (! $method->isPublic() || $this->declarationsAbove($class, $method->name->toString()) !== []) {
                        continue;
                    }

                    $label = sprintf('`%s::%s()`', $class->name?->toString() ?? 'class@anonymous', $method->name->toString());
                    array_push($details, ...$this->undocumented($path, $label, $method));
                }
            }
        }

        return $details;
    }

    /**
     * @return Details[]
     */
    private function undocumented(string $path, string $label, ClassMethod $method): array
    {
        $details = [];
        $doc = $method->getDocComment()?->getText() ?? '';

        foreach ($method->params as $param) {
            $name = $this->parameterName($param);
            $ownDoc = $param->getDocComment()?->getText() ?? '';

            if (! $this->isArrayType($param->type) || $this->documents($doc, 'param', $name) || $this->documents($ownDoc, 'var', null)) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    'Line %d: parameter `$%s` of %s is an array with no element type. Add '
                    . '`@param Type[] $%s` (or `array<Key, Value>`, `array{...}`) to the docblock (PHP-04).',
                    $param->getStartLine(),
                    $name,
                    $label,
                    $name
                ));
        }

        if ($this->isArrayType($method->returnType) && ! $this->documents($doc, 'return', null)) {
            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    'Line %d: %s returns an array with no element type. Add `@return Type[]` (or '
                    . '`array<Key, Value>`, `array{...}`) to the docblock (PHP-04).',
                    $method->getStartLine(),
                    $label
                ));
        }

        return $details;
    }

    private function isArrayType(?Node $type): bool
    {
        if ($type instanceof Identifier) {
            return in_array($type->toLowerString(), ['array', 'iterable'], true);
        }

        if ($type instanceof NullableType) {
            return $this->isArrayType($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $member) {
                if ($this->isArrayType($member)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the docblock gives the tag (for a parameter: the one naming it) a type that says what
     * the collection holds.
     */
    private function documents(string $doc, string $tag, ?string $parameter): bool
    {
        $pattern = sprintf('/@(?:phpstan-|psalm-)?%s\s+(.+)$/m', $tag);

        if (preg_match_all($pattern, $doc, $matches) === false) {
            return false;
        }

        foreach ($matches[1] as $rest) {
            $type = $this->leadingType($rest);

            if ($parameter !== null && preg_match('/^\s*(?:\.\.\.)?&?\$' . preg_quote($parameter, '/') . '\b/', substr($rest, strlen($type))) !== 1) {
                continue;
            }

            if (str_contains($type, '[]') || str_contains($type, '<') || str_contains($type, '{')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The type at the start of a tag's text, which may itself contain spaces inside brackets.
     */
    private function leadingType(string $text): string
    {
        $depth = 0;
        $length = strlen($text);

        for ($index = 0; $index < $length; $index++) {
            $character = $text[$index];

            if (in_array($character, ['<', '{', '('], true)) {
                $depth++;
            } elseif (in_array($character, ['>', '}', ')'], true)) {
                $depth--;
            } elseif ($depth <= 0 && ctype_space($character)) {
                return substr($text, 0, $index);
            }
        }

        return $text;
    }
}

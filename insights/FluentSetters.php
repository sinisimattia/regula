<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * PHP-21 — a method that sets something and hands the object back is `with{Noun}(...): self`.
 * Flags a `with…` method that does not return `self`, `static` or its own class, and a public
 * `set…` method. Eloquent's `set…Attribute` mutators, PHPUnit's `setUp…` and overrides of a
 * vendor method are exempt: their names are not ours to choose.
 */
final class FluentSetters extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Setters are with{Noun}(...): self (PHP-21)';
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
                    $problem = $this->problem($class, $method);

                    if ($problem === null) {
                        continue;
                    }

                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d: `%s::%s()` %s (PHP-21).',
                            $method->getStartLine(),
                            $class->name?->toString() ?? 'class@anonymous',
                            $method->name->toString(),
                            $problem
                        ));
                }
            }
        }

        return $details;
    }

    private function problem(ClassLike $class, ClassMethod $method): ?string
    {
        $name = $method->name->toString();
        $isWith = preg_match('/^with[A-Z]/', $name) === 1;
        $isSet = preg_match('/^set[A-Z]/', $name) === 1 && $method->isPublic()
            && preg_match('/^set\w*Attribute$/', $name) !== 1 && ! str_starts_with($name, 'setUp');

        if ((! $isWith && ! $isSet) || $this->isVendorOverride($class, $name)) {
            return null;
        }

        if ($isSet) {
            return sprintf(
                'is a public setter. Rename it `with%s(...)` and return `self`, or, if it acts on something '
                . 'other than this object, name it for what it does',
                substr($name, 3)
            );
        }

        return $this->returnsItself($class, $method)
            ? null
            : 'is named as a fluent setter but does not return `self`. Return `self` (or `static`), or rename it for what it does';
    }

    private function returnsItself(ClassLike $class, ClassMethod $method): bool
    {
        $type = $method->returnType;

        if ($type instanceof Identifier) {
            return in_array($type->toLowerString(), ['self', 'static'], true);
        }

        if ($type instanceof Name) {
            return in_array($type->toLowerString(), ['self', 'static'], true)
                || ($class->namespacedName !== null && $type->toString() === $class->namespacedName->toString());
        }

        return false;
    }

    private function isVendorOverride(ClassLike $class, string $method): bool
    {
        foreach ($this->declarationsAbove($class, $method) as $declaration) {
            if ($declaration['vendor']) {
                return true;
            }
        }

        return false;
    }
}

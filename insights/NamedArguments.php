<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use SplObjectStorage;

/**
 * PHP-08, the part a machine can hold: constructing one of our own classes with two or more
 * arguments names every one of them. Method calls are left to review, where a single obvious
 * argument or a vendor signature makes positional arguments reasonable. `new self(...)` and
 * `new static(...)` count as ours inside a class in one of our namespaces.
 */
final class NamedArguments extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'database', 'tests'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Our own classes are constructed with named arguments (PHP-08)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            $insideOurClasses = $this->constructionsInsideOurClasses($path);

            /** @var New_[] $constructions */
            $constructions = $this->findIn($path, fn (Node $node): bool => $node instanceof New_
                && $this->hasPositionalArguments($node, $insideOurClasses));

            foreach ($constructions as $construction) {
                $class = $construction->class instanceof Name ? $construction->class->toString() : '';

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d: `new %s(...)` passes %d arguments by position. Name each one, '
                        . '`new %s(first: $a, second: $b)` (PHP-08).',
                        $construction->getStartLine(),
                        substr((string) strrchr('\\' . $class, '\\'), 1),
                        count($construction->args),
                        substr((string) strrchr('\\' . $class, '\\'), 1)
                    ));
            }
        }

        return $details;
    }

    /**
     * Every `new` expression that sits inside a named class declared in one of our namespaces.
     *
     * @return SplObjectStorage<New_, null>
     */
    private function constructionsInsideOurClasses(string $path): SplObjectStorage
    {
        $constructions = new SplObjectStorage();

        foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof ClassLike) as $class) {
            /** @var ClassLike $class */
            if ($class->namespacedName === null || ! $this->isOurs($class->namespacedName->toString())) {
                continue;
            }

            foreach ((new NodeFinder())->findInstanceOf($class->stmts, New_::class) as $construction) {
                $constructions->attach($construction);
            }
        }

        return $constructions;
    }

    /**
     * @param  SplObjectStorage<New_, null>  $insideOurClasses
     */
    private function hasPositionalArguments(New_ $construction, SplObjectStorage $insideOurClasses): bool
    {
        if (! $construction->class instanceof Name || $construction->isFirstClassCallable() || count($construction->args) < 2) {
            return false;
        }

        $class = $construction->class->toLowerString();
        $ours = in_array($class, ['self', 'static'], true)
            ? $insideOurClasses->contains($construction)
            : $this->isOurs($construction->class->toString());

        if (! $ours) {
            return false;
        }

        foreach ($construction->args as $argument) {
            if ($argument instanceof Arg && $argument->name === null && ! $argument->unpack) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Shared plumbing for the insights that enforce `http.md` and `testing.md`: finding controllers,
 * Form Requests and Resources, walking a method body, and reading the tests/ tree.
 *
 * @mixin DomainInsight
 */
trait InspectsHttpLayer
{
    /**
     * @var array<string, string>|null
     */
    private ?array $parents = null;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    private function isController(string $path): bool
    {
        return str_contains('/' . $this->relative($path), '/Http/Controllers/');
    }

    private function isResource(string $path): bool
    {
        return str_contains('/' . $this->relative($path), '/Http/Resources/');
    }

    /**
     * Where `http.md` reaches: the domains and the shared HTTP layer (README, "What this governs").
     */
    private function isHttpGoverned(string $path): bool
    {
        $relative = $this->relative($path);

        return str_starts_with($relative, 'app/Domains/') || str_starts_with($relative, 'app/Http/');
    }

    /**
     * @return Class_[]
     */
    private function classesIn(string $path): array
    {
        /** @var Class_[] $classes */
        $classes = $this->findIn($path, fn (Node $node): bool => $node instanceof Class_ && $node->name !== null);

        return $classes;
    }

    /**
     * Whether a class in app/ has the given ancestor, following `extends` through app/ classes.
     */
    private function descendsFrom(Class_ $class, string $ancestor): bool
    {
        $parents = $this->parentMap();
        $current = $class->extends?->toString();
        $seen = [];

        while ($current !== null && ! isset($seen[$current])) {
            if (ltrim($current, '\\') === $ancestor) {
                return true;
            }

            $seen[$current] = true;
            $current = $parents[$current] ?? null;
        }

        return false;
    }

    /**
     * Every class in app/ mapped to the class it extends.
     *
     * @return array<string, string>
     */
    private function parentMap(): array
    {
        if ($this->parents !== null) {
            return $this->parents;
        }

        $this->parents = [];

        foreach ($this->analysedFiles() as $path) {
            foreach ($this->classesIn($path) as $class) {
                if ($class->extends !== null && $class->namespacedName !== null) {
                    $this->parents[$class->namespacedName->toString()] = $class->extends->toString();
                }
            }
        }

        return $this->parents;
    }

    /**
     * A controller's public actions: public, non-static, non-magic methods.
     *
     * @return ClassMethod[]
     */
    private function publicActions(Class_ $class): array
    {
        return array_values(array_filter(
            $class->getMethods(),
            fn (ClassMethod $method): bool => $method->isPublic() && ! $method->isStatic() && ! $method->isAbstract()
                && (! str_starts_with($method->name->toString(), '__') || $method->name->toString() === '__invoke'),
        ));
    }

    /**
     * Nodes in a body that satisfy the filter, not descending into closures or nested functions:
     * a `return` inside a closure is the closure's, not the method's.
     *
     * @param  Node[]  $statements
     * @param  callable(Node): bool  $filter
     * @return Node[]
     */
    private function findInBody(array $statements, callable $filter): array
    {
        $visitor = new class ($filter) extends NodeVisitorAbstract {
            /** @var Node[] */
            public array $found = [];

            /** @var callable(Node): bool */
            private $filter;

            public function __construct(callable $filter)
            {
                $this->filter = $filter;
            }

            public function enterNode(Node $node): ?int
            {
                if (($this->filter)($node)) {
                    $this->found[] = $node;
                }

                return $node instanceof Closure || $node instanceof ArrowFunction
                    || $node instanceof Function_ || $node instanceof Class_
                    ? NodeVisitor::DONT_TRAVERSE_CHILDREN
                    : null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($statements);

        return $visitor->found;
    }

    private function detail(string $path, string $message): Details
    {
        return Details::make()->setFile($path)->setMessage($message);
    }
}

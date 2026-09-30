<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;

/**
 * PHP-05 — constructor property promotion. Flags a constructor parameter copied unchanged into a
 * property the class declares itself, and a public constructor with no parameters and no body.
 *
 * A parameter also handed to `parent::__construct()` is left alone.
 */
final class ConstructorPromotion extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'database'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Constructors promote their properties (PHP-05)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            foreach ($this->classLikesIn($path) as $class) {
                $constructor = $class->getMethod('__construct');

                if ($constructor === null) {
                    continue;
                }

                $label = sprintf('`%s`', $class->name?->toString() ?? 'class@anonymous');

                if ($constructor->params === [] && $constructor->stmts === []
                    && ! $constructor->isPrivate() && ! $constructor->isProtected()) {
                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d: %s has an empty public constructor with no parameters. Delete it, '
                            . 'or make it private if it exists to stop instantiation (PHP-05).',
                            $constructor->getStartLine(),
                            $label
                        ));
                }

                foreach ($this->unpromoted($class, $constructor) as $line => $parameter) {
                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d: %s copies `$%s` into a property by hand. Promote it in the '
                            . 'constructor signature and delete the property and the assignment (PHP-05).',
                            $line,
                            $label,
                            $parameter
                        ));
                }
            }
        }

        return $details;
    }

    /**
     * Assignments of an unchanged constructor parameter to a property this class declares, keyed
     * by line.
     *
     * @return array<int, string>
     */
    private function unpromoted(ClassLike $class, ClassMethod $constructor): array
    {
        $finder = new NodeFinder();
        $body = $constructor->stmts ?? [];
        $parameters = [];

        foreach ($constructor->params as $param) {
            if ($param->flags === 0) {
                $parameters[] = $this->parameterName($param);
            }
        }

        $declared = [];

        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $item) {
                $declared[] = $item->name->toString();
            }
        }

        $passedToParent = [];

        foreach ($finder->find($body, static fn (Node $node): bool => $node instanceof StaticCall
            && $node->class instanceof Name && $node->class->toLowerString() === 'parent'
            && $node->name instanceof Identifier && $node->name->toLowerString() === '__construct') as $call) {
            foreach ($finder->findInstanceOf([$call], Variable::class) as $variable) {
                $passedToParent[] = $variable->name;
            }
        }

        $assignments = [];

        foreach ($body as $statement) {
            if (! $statement instanceof Expression || ! $statement->expr instanceof Assign) {
                continue;
            }

            $assign = $statement->expr;

            if (! $assign->var instanceof PropertyFetch || ! $assign->var->var instanceof Variable || $assign->var->var->name !== 'this'
                || ! $assign->var->name instanceof Identifier || ! $assign->expr instanceof Variable || ! is_string($assign->expr->name)) {
                continue;
            }

            $parameter = $assign->expr->name;

            if (in_array($parameter, $parameters, true) && in_array($assign->var->name->toString(), $declared, true)
                && ! in_array($parameter, $passedToParent, true) && ! $this->isModified($body, $parameter)) {
                $assignments[$assign->getStartLine()] = $parameter;
            }
        }

        return $assignments;
    }

    /**
     * Whether the constructor writes to the parameter anywhere, so the property would not hold
     * what was passed in.
     *
     * @param  Node[]  $body
     */
    private function isModified(array $body, string $parameter): bool
    {
        $writes = (new NodeFinder())->find($body, static function (Node $node) use ($parameter): bool {
            $target = match (true) {
                $node instanceof Assign, $node instanceof Expr\AssignOp, $node instanceof Expr\AssignRef => $node->var,
                $node instanceof Expr\PreInc, $node instanceof Expr\PreDec, $node instanceof Expr\PostInc, $node instanceof Expr\PostDec => $node->var,
                default => null,
            };

            while ($target instanceof Expr\ArrayDimFetch) {
                $target = $target->var;
            }

            return $target instanceof Variable && $target->name === $parameter;
        });

        return $writes !== [];
    }
}

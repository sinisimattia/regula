<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Switch_;

/**
 * PHP-24 — the environment is checked only through the `is…` methods (`isProduction()`,
 * `isLocal()`, `runningUnitTests()`), never by comparing an environment name.
 *
 * Flags `environment(...)` called with arguments, and a comparison, `match` or `switch` on the
 * environment's name. A string that happens to spell an environment is not flagged on its own: an
 * enum case such as `Production = 'production'` is a domain word. `tests/` is out of scope.
 */
final class EnvironmentChecks extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'routes', 'database', 'bootstrap'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'The environment is checked only through the is… methods (PHP-24)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            foreach ($this->findIn($path, fn (Node $node): bool => $this->violation($node) !== null) as $node) {
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d %s. Ask the application instead — app()->isProduction(), '
                        . 'app()->isLocal(), app()->runningUnitTests() (PHP-24).',
                        $node->getStartLine(),
                        $this->violation($node)
                    ));
            }
        }

        return $details;
    }

    /**
     * What is wrong with a node, or null when nothing is.
     */
    private function violation(Node $node): ?string
    {
        if ($this->isEnvironmentCall($node) && $node instanceof Expr\CallLike
            && ! $node->isFirstClassCallable() && $node->getArgs() !== []) {
            return 'passes an environment name to environment()';
        }

        if ($node instanceof BinaryOp\Identical || $node instanceof BinaryOp\Equal
            || $node instanceof BinaryOp\NotIdentical || $node instanceof BinaryOp\NotEqual) {
            return $this->readsEnvironmentName($node->left) || $this->readsEnvironmentName($node->right)
                ? 'compares the environment name'
                : null;
        }

        if (($node instanceof Match_ || $node instanceof Switch_) && $this->readsEnvironmentName($node->cond)) {
            return 'branches on the environment name';
        }

        return null;
    }

    private function isEnvironmentCall(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier
            && $node->name->toString() === 'environment';
    }

    /**
     * `app()->environment()`, `config('app.env')` or `Config::get('app.env')`.
     */
    private function readsEnvironmentName(Expr $expression): bool
    {
        if ($this->isEnvironmentCall($expression) && $expression instanceof Expr\CallLike) {
            return ! $expression->isFirstClassCallable() && $expression->getArgs() === [];
        }

        $first = null;

        if ($expression instanceof Expr\CallLike && $expression->isFirstClassCallable()) {
            return false;
        }

        if ($expression instanceof FuncCall && $expression->name instanceof Name && $expression->name->toLowerString() === 'config') {
            $first = $expression->getArgs()[0] ?? null;
        } elseif (($expression instanceof MethodCall || $expression instanceof StaticCall)
            && $expression->name instanceof Identifier && $expression->name->toString() === 'get') {
            $first = $expression->getArgs()[0] ?? null;
        }

        return $first !== null && $first->value instanceof String_ && $first->value->value === 'app.env';
    }
}

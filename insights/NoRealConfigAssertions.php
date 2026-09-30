<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeFinder;

/**
 * TEST-06 — a test never asserts on the shape or values of a real file under `config/`.
 *
 * A real config value is one read with `config()`, `Config::get()` and kin, or `require`d from
 * `config/`, whose key the test did not set itself (in the file or a shared base class). Flags a
 * shape assertion over one (`assertCount`, `assertArrayHasKey`, a `count()` of it), an assertion
 * pinning one to a hard-coded value, and a `foreach` over one whose body asserts.
 *
 * Not flagged: comparing application output against the configured value, which survives a config
 * edit, and a value passed into another call first, such as a validator — the "it is valid"
 * assertion TEST-06 permits.
 */
final class NoRealConfigAssertions extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const CONFIG_FACADES = ['Illuminate\Support\Facades\Config', 'Config'];

    private const READERS = ['get', 'string', 'integer', 'boolean', 'float', 'array', 'collection', 'all'];

    private const SETTERS = ['set', 'prepend', 'push'];

    /**
     * Wrappers that still hand the assertion the config value itself, or a count or listing of it.
     */
    private const PASS_THROUGH = ['count', 'sizeof', 'array_keys', 'array_values', 'array_key_exists', 'in_array',
        'array_column', 'collect', 'iterator_to_array', 'array_filter', 'array_unique', 'intval', 'strval', 'boolval', 'floatval'];

    private const SHAPE_ASSERTIONS = ['assertcount', 'assertsamesize', 'assertnotsamesize', 'assertarrayhaskey', 'assertarraynothaskey',
        'assertempty', 'assertnotempty', 'assertcontains', 'assertnotcontains', 'assertcontainsonly', 'assertisarray', 'assertislist'];

    /**
     * Files whose `config()->set(...)` every test inherits.
     */
    private const SHARED = ['tests/TestCase.php', 'tests/FeatureTestCase.php', 'tests/UnitTestCase.php', 'tests/SystemTestCase.php'];

    public function getTitle(): string
    {
        return 'Tests never assert on real configuration (TEST-06)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $root = (string) getcwd();
        $shared = [];

        foreach ([...array_map(fn (string $file): string => $root . '/' . $file, self::SHARED), ...$this->filesUnder(['tests/Helpers'])] as $path) {
            $shared = is_file($path) ? [...$shared, ...$this->keysSetIn($this->syntaxTree($path))] : $shared;
        }

        $details = [];

        foreach ($this->filesUnder(['tests']) as $path) {
            /** @var ClassMethod[] $methods */
            $methods = $this->findIn($path, fn (Node $node): bool => $node instanceof ClassMethod);

            // setUp() and helpers set fixtures for every test in the file; one test's own set does not
            // reach its siblings.
            $fileWide = $this->keysSetIn(array_merge([], ...array_map(
                fn (ClassMethod $method): array => $this->isTest($method) ? [] : ($method->stmts ?? []),
                $methods,
            )));

            foreach ($methods as $method) {
                $fixtures = [...$shared, ...$fileWide, ...$this->keysSetIn($method->stmts ?? [])];

                foreach ($this->violationsIn($method, $fixtures) as [$line, $what, $how]) {
                    $details[] = $this->detail($path, sprintf(
                        'Line %d %s real configuration (%s). Build the test\'s own fixture with config()->set(...) '
                        . 'or an injected config repository; the only assertion allowed on a real config file is '
                        . 'that it is valid (TEST-06).',
                        $line,
                        $how,
                        $what,
                    ));
                }
            }
        }

        return $details;
    }

    /**
     * @param  string[]  $fixtures
     * @return list<array{int, string, string}>  [line, what is read, how it is used]
     */
    private function violationsIn(ClassMethod $method, array $fixtures): array
    {
        $tainted = [];
        $violations = [];
        $finder = new NodeFinder();

        // Document order, so `$limits = config('x')` taints `$limits` before it is asserted on.
        foreach ($finder->find($method->stmts ?? [], fn (Node $node): bool => true) as $node) {
            if ($node instanceof Assign && $node->var instanceof Variable && is_string($node->var->name)
                && ($read = $this->realValueIn($node->expr, $tainted, $fixtures)) !== null) {
                $tainted[$node->var->name] = $read;
            } elseif ($this->isAssertion($node)) {
                /** @var MethodCall|StaticCall $node */
                if (($read = $this->pinnedRead($node, $tainted, $fixtures)) !== null) {
                    $violations[] = [$node->getStartLine(), $read, 'asserts on'];
                }
            } elseif ($node instanceof Foreach_ && ($read = $this->realValueIn($node->expr, $tainted, $fixtures)) !== null
                && $finder->findFirst($node->stmts, fn (Node $inner): bool => $this->isAssertion($inner)) !== null) {
                $violations[] = [$node->getStartLine(), $read, 'iterates and asserts on'];
            }
        }

        return $violations;
    }

    /**
     * The real value an assertion pins, or null when it only compares output against config.
     *
     * @param  array<string, string>  $tainted
     * @param  string[]  $fixtures
     */
    private function pinnedRead(MethodCall|StaticCall $assertion, array $tainted, array $fixtures): ?string
    {
        $arguments = $assertion->getArgs();
        $shape = $assertion->name instanceof Identifier && in_array($assertion->name->toLowerString(), self::SHAPE_ASSERTIONS, true);

        foreach ($arguments as $index => $argument) {
            if (($read = $this->realValueIn($argument->value, $tainted, $fixtures)) === null) {
                continue;
            }

            $others = array_filter($arguments, fn ($other, int $position): bool => $position !== $index, ARRAY_FILTER_USE_BOTH);
            $pinnedToLiteral = array_filter($others, fn ($other): bool => ! $this->isLiteral($other->value)) === [];

            if ($shape || $this->measuresShape($argument->value) || $pinnedToLiteral) {
                return $read;
            }
        }

        return null;
    }

    /**
     * count(config('x')), array_keys(config('x')) — a question about the array, not a value in it.
     */
    private function measuresShape(Expr $expression): bool
    {
        return ($expression instanceof FuncCall && $expression->name instanceof Name
                && in_array(strtolower($expression->name->getLast()), self::PASS_THROUGH, true))
            || ($expression instanceof MethodCall && $expression->name instanceof Identifier
                && in_array($expression->name->toLowerString(), ['count', 'keys', 'values', 'toarray'], true))
            || ($expression instanceof BinaryOp && ($this->measuresShape($expression->left) || $this->measuresShape($expression->right)));
    }

    private function isLiteral(Expr $expression): bool
    {
        return $expression instanceof Node\Scalar
            || $expression instanceof Expr\ClassConstFetch
            || $expression instanceof Expr\ConstFetch
            || ($expression instanceof Expr\UnaryMinus && $expression->expr instanceof Node\Scalar)
            || ($expression instanceof Array_ && array_filter($expression->items, fn ($item): bool => ! $this->isLiteral($item->value)) === []);
    }

    private function isAssertion(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier && str_starts_with($node->name->toString(), 'assert');
    }

    /**
     * A description of the real config value an expression hands over unchanged, or null.
     *
     * @param  array<string, string>  $tainted
     * @param  string[]  $fixtures
     */
    private function realValueIn(Expr $expression, array $tainted, array $fixtures): ?string
    {
        if ($expression instanceof Variable && is_string($expression->name)) {
            return $tainted[$expression->name] ?? null;
        }

        if ($expression instanceof Include_) {
            return $this->includesConfig($expression) ? 'a file required from config/' : null;
        }

        $key = $this->keyRead($expression);

        if ($key !== null) {
            return $this->isFixture($key, $fixtures) ? null : "`{$key}`";
        }

        $inner = match (true) {
            $expression instanceof ArrayDimFetch => [$expression->var],
            $expression instanceof Cast, $expression instanceof BooleanNot => [$expression->expr],
            $expression instanceof BinaryOp => [$expression->left, $expression->right],
            $expression instanceof Isset_ => $expression->vars,
            $expression instanceof Array_ => array_map(fn ($item): Expr => $item->value, $expression->items),
            $expression instanceof FuncCall && $expression->name instanceof Name
                && in_array(strtolower($expression->name->getLast()), self::PASS_THROUGH, true) => array_map(fn ($argument): Expr => $argument->value, $expression->getArgs()),
            $expression instanceof MethodCall => [$expression->var],
            default => [],
        };

        foreach ($inner as $child) {
            if (($read = $this->realValueIn($child, $tainted, $fixtures)) !== null) {
                return $read;
            }
        }

        return null;
    }

    /**
     * The key a config read names: config('k'), Config::get('k'), config()->get('k').
     */
    private function keyRead(Expr $expression): ?string
    {
        $first = null;

        if ($expression instanceof FuncCall && $expression->name instanceof Name && strtolower($expression->name->getLast()) === 'config') {
            $first = $expression->getArgs()[0]->value ?? null;
        } elseif ($expression instanceof StaticCall && $expression->class instanceof Name && $expression->name instanceof Identifier
            && in_array($expression->class->toString(), self::CONFIG_FACADES, true) && in_array($expression->name->toLowerString(), self::READERS, true)) {
            $first = $expression->getArgs()[0]->value ?? null;
        } elseif ($expression instanceof MethodCall && $expression->name instanceof Identifier && in_array($expression->name->toLowerString(), self::READERS, true)
            && $expression->var instanceof FuncCall && $expression->var->name instanceof Name
            && strtolower($expression->var->name->getLast()) === 'config' && $expression->var->getArgs() === []) {
            $first = $expression->getArgs()[0]->value ?? null;
        }

        return $first instanceof String_ ? $first->value : null;
    }

    private function includesConfig(Include_ $include): bool
    {
        return (new NodeFinder())->findFirst($include->expr, fn (Node $node): bool => $node instanceof String_
            && preg_match('#(^|/)config(/|$)#', $node->value) === 1
            || ($node instanceof FuncCall && $node->name instanceof Name && strtolower($node->name->getLast()) === 'config_path')) !== null;
    }

    /**
     * @param  string[]  $fixtures
     */
    private function isFixture(string $key, array $fixtures): bool
    {
        foreach ($fixtures as $set) {
            // Setting `limits.max` does not make all of `limits` a fixture: its siblings are still real.
            if ($key === $set || str_starts_with($key, $set . '.')) {
                return true;
            }
        }

        return false;
    }

    private function isTest(ClassMethod $method): bool
    {
        if (str_starts_with($method->name->toString(), 'test')) {
            return true;
        }

        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->getLast() === 'Test') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Keys set in the given code: Config::set('k', ...), config()->set('k', ...), config(['k' => ...]).
     *
     * @param  Node[]  $nodes
     * @return string[]
     */
    private function keysSetIn(array $nodes): array
    {
        $keys = [];

        foreach ((new NodeFinder())->find($nodes, fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall || $node instanceof FuncCall) as $call) {
            /** @var StaticCall|MethodCall|FuncCall $call */
            $first = $call->getArgs()[0]->value ?? null;
            $isSetter = ($call instanceof StaticCall || $call instanceof MethodCall)
                && $call->name instanceof Identifier && in_array($call->name->toLowerString(), self::SETTERS, true);
            $isConfigArray = $call instanceof FuncCall && $call->name instanceof Name
                && strtolower($call->name->getLast()) === 'config' && $first instanceof Array_;

            if (! $isSetter && ! $isConfigArray) {
                continue;
            }

            if ($first instanceof String_) {
                $keys[] = $first->value;
            } elseif ($first instanceof Array_) {
                foreach ($first->items as $item) {
                    if ($item->key instanceof String_) {
                        $keys[] = $item->key->value;
                    }
                }
            }
        }

        return $keys;
    }
}

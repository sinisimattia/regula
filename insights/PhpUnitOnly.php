<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;

/**
 * TEST-01 — tests are PHPUnit classes; Pest is neither installed nor written.
 *
 * Flags a `pestphp/*` package required in composer.json or locked in composer.lock, and a
 * top-level Pest call — `it()`, `test()`, `uses()`, `describe()` and their kin — in tests/.
 */
final class PhpUnitOnly extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const PEST_FUNCTIONS = ['it', 'test', 'uses', 'describe', 'beforeeach', 'aftereach', 'beforeall', 'afterall', 'dataset', 'pest', 'arch'];

    public function getTitle(): string
    {
        return 'Tests are PHPUnit only (TEST-01)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $root = (string) getcwd();
        $details = [];

        foreach ($this->pestPackages($root) as [$file, $package]) {
            $details[] = $this->detail($root . '/' . $file, sprintf(
                '%s brings in `%s`. Remove it and write the tests as PHPUnit classes (TEST-01).',
                $file,
                $package,
            ));
        }

        foreach ($this->filesUnder(['tests']) as $path) {
            foreach ($this->topLevelStatements($this->syntaxTree($path)) as $statement) {
                if ($statement instanceof Expression && ($function = $this->pestCall($statement->expr)) !== null) {
                    $details[] = $this->detail($path, sprintf(
                        'Line %d is a Pest `%s()` call. Convert it to a PHPUnit test class made with '
                        . '`php artisan make:test --phpunit` (TEST-01).',
                        $statement->getStartLine(),
                        $function,
                    ));
                }
            }
        }

        return $details;
    }

    /**
     * @return list<array{string, string}>  [file, package]
     */
    private function pestPackages(string $root): array
    {
        $found = [];
        $manifest = json_decode((string) @file_get_contents($root . '/composer.json'), true);

        foreach (['require', 'require-dev'] as $section) {
            foreach (array_keys(is_array($manifest[$section] ?? null) ? $manifest[$section] : []) as $package) {
                if (str_starts_with((string) $package, 'pestphp/')) {
                    $found[] = ['composer.json', (string) $package];
                }
            }
        }

        $lock = json_decode((string) @file_get_contents($root . '/composer.lock'), true);

        foreach (['packages', 'packages-dev'] as $section) {
            foreach (is_array($lock[$section] ?? null) ? $lock[$section] : [] as $package) {
                if (str_starts_with((string) ($package['name'] ?? ''), 'pestphp/')) {
                    $found[] = ['composer.lock', (string) $package['name']];
                }
            }
        }

        return $found;
    }

    /**
     * @param  Stmt[]  $statements
     * @return Stmt[]
     */
    private function topLevelStatements(array $statements): array
    {
        $flat = [];

        foreach ($statements as $statement) {
            $flat = $statement instanceof Namespace_ ? [...$flat, ...$statement->stmts] : [...$flat, $statement];
        }

        return $flat;
    }

    /**
     * The Pest function at the root of an expression — `it(...)->with(...)` included — or null.
     */
    private function pestCall(Expr $expression): ?string
    {
        while ($expression instanceof MethodCall) {
            $expression = $expression->var;
        }

        if ($expression instanceof FuncCall && $expression->name instanceof Name
            && in_array(strtolower($expression->name->getLast()), self::PEST_FUNCTIONS, true)) {
            return $expression->name->getLast();
        }

        return null;
    }
}

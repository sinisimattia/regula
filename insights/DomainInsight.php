<?php

declare(strict_types=1);

namespace Insights;

use FilesystemIterator;
use NunoMaduro\PhpInsights\Domain\Insights\Insight;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Shared plumbing for the insights that enforce `docs/standards/domain-architecture.md`.
 *
 * Each concrete insight grandfathers its own debt through its own `config` entry rather than the
 * global `exclude` list, so a domain that is behind on one rule is still held to every other one.
 */
abstract class DomainInsight extends Insight
{
    protected const DOMAIN_ROOT = 'app/Domains/';

    /**
     * Paths this insight tolerates for now. Each entry in config/insights.php carries a comment
     * naming the debt it represents, and the list is expected to shrink.
     *
     * @return string[]
     */
    protected function grandfathered(): array
    {
        /** @var string[] $paths */
        $paths = $this->config['grandfathered'] ?? [];

        return $paths;
    }

    protected function isGrandfathered(string $path): bool
    {
        foreach ($this->grandfathered() as $prefix) {
            if (str_contains($this->relative($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function relative(string $path): string
    {
        $cwd = getcwd();

        if ($cwd !== false && str_starts_with($path, $cwd)) {
            return ltrim(substr($path, strlen($cwd)), '/');
        }

        return $path;
    }

    /**
     * The domain a file belongs to, or null when it sits outside app/Domains.
     */
    protected function domainOf(string $path): ?string
    {
        $relative = $this->relative($path);

        if (! str_starts_with($relative, self::DOMAIN_ROOT)) {
            return null;
        }

        $segments = explode('/', substr($relative, strlen(self::DOMAIN_ROOT)));

        return $segments[0] ?? null;
    }

    /**
     * Every analysed PHP file, as repository-relative paths.
     *
     * @return string[]
     */
    protected function analysedFiles(): array
    {
        $files = [];

        foreach ($this->collector->getFiles() as $file) {
            $path = is_object($file) && method_exists($file, 'getPathname')
                ? $file->getPathname()
                : (string) $file;

            if (! str_ends_with($path, '.php') || $this->shouldSkipFile($path)) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    /**
     * Every PHP file under the given repository-relative directories, as absolute paths. For checks
     * whose rule reaches beyond `app/`, which is all the collector holds.
     *
     * @param  string[]  $directories
     * @return string[]
     */
    protected function filesUnder(array $directories): array
    {
        $files = [];
        $root = (string) getcwd();

        foreach ($directories as $directory) {
            if (! is_dir($root . '/' . $directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getPathname(), '.php') && ! $this->isGrandfathered($file->getPathname())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * A file's syntax tree with every name resolved to its fully qualified form, so `Storage` and
     * `\Illuminate\Support\Facades\Storage` compare equal. Parsed once per file per run.
     *
     * @return Node[]
     */
    protected function syntaxTree(string $path): array
    {
        /** @var array<string, Node[]> $cache */
        static $cache = [];

        if (isset($cache[$path])) {
            return $cache[$path];
        }

        $ast = (new ParserFactory())->createForHostVersion()->parse((string) file_get_contents($path)) ?? [];
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        return $cache[$path] = $traverser->traverse($ast);
    }

    /**
     * Every node in a file's syntax tree that satisfies the filter.
     *
     * @param  callable(Node): bool  $filter
     * @return Node[]
     */
    protected function findIn(string $path, callable $filter): array
    {
        return (new NodeFinder())->find($this->syntaxTree($path), $filter);
    }

    /**
     * The class or interface name declared in a file, without its namespace.
     */
    protected function declaredName(string $source): ?string
    {
        if (preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}

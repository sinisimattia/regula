<?php

declare(strict_types=1);

namespace Insights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;

/**
 * Helpers shared by the domain-architecture, entity and persistence insights.
 *
 * Class hierarchy questions are answered from the analysed tree first and by reflection only for
 * classes outside it, so a check gives the same answer on this repository and on a fixture tree
 * whose classes are not autoloadable.
 */
trait InspectsDomainCode
{
    private const RESOLVERS = ['app', 'resolve'];

    private const CONTAINER_METHODS = ['make', 'makeWith', 'get'];

    /** Registration calls, lower-cased, whose arguments name classes to wire them rather than call them. */
    private const WIRING_METHODS = [
        'bind', 'bindif', 'singleton', 'singletonif', 'scoped', 'scopedif', 'instance', 'tag', 'when', 'needs',
        'give', 'extend', 'listen', 'subscribe', 'policy', 'register', 'job', 'command', 'model',
    ];

    /** @var array<string, array{path: string, node: ClassLike}>|null */
    private ?array $classIndex = null;

    /**
     * Every class, interface, trait and enum declared under app/, by fully qualified name.
     *
     * @return array<string, array{path: string, node: ClassLike}>
     */
    protected function classIndex(): array
    {
        if ($this->classIndex !== null) {
            return $this->classIndex;
        }

        $this->classIndex = [];

        foreach ($this->analysedFiles() as $path) {
            foreach ($this->classesIn($path) as $node) {
                $fqcn = $this->fqcnOf($node);

                if ($fqcn !== null) {
                    $this->classIndex[$fqcn] = ['path' => $path, 'node' => $node];
                }
            }
        }

        return $this->classIndex;
    }

    /**
     * @return ClassLike[]
     */
    protected function classesIn(string $path): array
    {
        /** @var ClassLike[] $nodes */
        $nodes = $this->findIn($path, static fn (Node $node): bool => $node instanceof ClassLike && $node->name !== null);

        return $nodes;
    }

    protected function fqcnOf(ClassLike $node): ?string
    {
        return isset($node->namespacedName) ? $node->namespacedName->toString() : null;
    }

    /**
     * Whether a class is, or descends from, the given class.
     */
    protected function descendsFrom(string $fqcn, string $ancestor): bool
    {
        $index = $this->classIndex();
        $visited = [];

        while (! isset($visited[$fqcn])) {
            $visited[$fqcn] = true;

            if (strcasecmp($fqcn, $ancestor) === 0) {
                return true;
            }

            if (! isset($index[$fqcn])) {
                return class_exists($fqcn) && is_a($fqcn, $ancestor, true);
            }

            $node = $index[$fqcn]['node'];

            if (! $node instanceof Class_ || $node->extends === null) {
                return false;
            }

            $fqcn = $node->extends->toString();
        }

        return false;
    }

    protected function isEloquentModel(string $fqcn): bool
    {
        return $this->descendsFrom($fqcn, Model::class);
    }

    /**
     * Whether a file declares framework wiring — a service provider or a kernel — judged by the
     * class's kind, not its folder. README.md and domain-architecture.md describe the same wiring
     * test, so changing one means changing both.
     */
    protected function isFrameworkRegistry(string $path): bool
    {
        foreach ($this->classesIn($path) as $class) {
            $fqcn = $this->fqcnOf($class);

            if ($fqcn === null) {
                continue;
            }

            foreach ([ServiceProvider::class, HttpKernel::class, ConsoleKernel::class] as $registry) {
                if ($this->descendsFrom($fqcn, $registry)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Object ids of every node inside the arguments of a registration call such as `bind()`,
     * `listen()` or `Gate::policy()`, where naming a class wires it rather than calls it.
     *
     * @return array<int, true>
     */
    protected function wiringArgumentNodes(string $path): array
    {
        $inside = [];
        $calls = $this->findIn($path, static fn (Node $node): bool => ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier
            && in_array($node->name->toLowerString(), self::WIRING_METHODS, true));

        foreach ($calls as $call) {
            /** @var MethodCall|NullsafeMethodCall|StaticCall $call */
            foreach ($call->getArgs() as $argument) {
                $inside[spl_object_id($argument->value)] = true;

                foreach ((new NodeFinder())->findInstanceOf($argument->value, Node::class) as $node) {
                    $inside[spl_object_id($node)] = true;
                }
            }
        }

        return $inside;
    }

    /**
     * The class a node resolves from the container — `app(X::class)`, `resolve(X::class)`,
     * `$this->app->make(X::class)` — or null.
     */
    protected function resolvedClass(Node $node): ?string
    {
        $argument = null;

        if ($node instanceof FuncCall && $node->name instanceof Name && in_array($node->name->toLowerString(), self::RESOLVERS, true)) {
            $argument = $node->getArgs()[0]->value ?? null;
        }

        if (($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier
            && in_array($node->name->toString(), self::CONTAINER_METHODS, true)) {
            $argument = $node->getArgs()[0]->value ?? null;
        }

        return $this->classConstant($argument);
    }

    protected function classConstant(?Expr $expression): ?string
    {
        if ($expression instanceof ClassConstFetch
            && $expression->class instanceof Name
            && $expression->name instanceof Identifier
            && $expression->name->toLowerString() === 'class') {
            return $expression->class->toString();
        }

        return null;
    }

    /**
     * Every method name an interface declares, its parents included, lower-cased.
     *
     * @return string[]|null null when the interface cannot be found
     */
    protected function interfaceMethods(string $interface): ?array
    {
        $index = $this->classIndex();

        if (! isset($index[$interface])) {
            if (! interface_exists($interface)) {
                return null;
            }

            return array_map(
                static fn (\ReflectionMethod $method): string => strtolower($method->getName()),
                (new \ReflectionClass($interface))->getMethods()
            );
        }

        $node = $index[$interface]['node'];
        $methods = array_map(static fn ($method): string => $method->name->toLowerString(), $node->getMethods());

        if ($node instanceof Interface_) {
            foreach ($node->extends as $parent) {
                $methods = array_merge($methods, $this->interfaceMethods($parent->toString()) ?? []);
            }
        }

        return $methods;
    }

    /**
     * The folder directly under a domain a file sits in — `Services`, `Http`, `Entities` — or null
     * outside app/Domains.
     */
    protected function layerOf(string $path): ?string
    {
        $relative = $this->relative($path);

        if (! str_starts_with($relative, self::DOMAIN_ROOT)) {
            return null;
        }

        $segments = explode('/', substr($relative, strlen(self::DOMAIN_ROOT)));

        return count($segments) > 2 ? $segments[1] : null;
    }

    /**
     * The names a type declaration mentions: fully qualified class names as written, and scalar
     * keywords lower-cased.
     *
     * @return string[]
     */
    protected function typeNames(?Node $type): array
    {
        if ($type instanceof NullableType) {
            return $this->typeNames($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $names = [];

            foreach ($type->types as $member) {
                $names = array_merge($names, $this->typeNames($member));
            }

            return $names;
        }

        if ($type instanceof Name) {
            return [$type->toString()];
        }

        if ($type instanceof Identifier) {
            return [$type->toLowerString()];
        }

        return [];
    }

    protected function isNullableType(?Node $type): bool
    {
        return $type instanceof NullableType
            || ($type instanceof UnionType && in_array('null', $this->typeNames($type), true));
    }

    /**
     * Class names a PHPDoc type mentions — `UserModel[]`, `Collection<int, UserModel>|null` —
     * resolved against the file's namespace and imports.
     *
     * @return string[]
     */
    protected function docTypeNames(string $path, string $docType): array
    {
        [$namespace, $imports] = $this->importsOf($path);
        $names = [];

        preg_match_all('/\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*/', $docType, $matches);

        foreach ($matches[0] as $name) {
            if (str_starts_with($name, '\\')) {
                $names[] = ltrim($name, '\\');

                continue;
            }

            $first = strtolower(explode('\\', $name)[0]);

            if (isset($imports[$first])) {
                $rest = substr($name, strlen($first));
                $names[] = $imports[$first] . $rest;

                continue;
            }

            // Scalars and pseudo-types are lower case; a class name starts with a capital.
            if (ctype_upper($name[0])) {
                $names[] = ($namespace !== '' ? $namespace . '\\' : '') . $name;
            }
        }

        return $names;
    }

    /**
     * @return array{0: string, 1: array<string, string>} the namespace, and imports by lower-cased alias
     */
    private function importsOf(string $path): array
    {
        $namespace = '';
        $imports = [];

        foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof Namespace_ || $node instanceof Use_ || $node instanceof GroupUse) as $node) {
            if ($node instanceof Namespace_) {
                $namespace = $node->name?->toString() ?? '';

                continue;
            }

            $prefix = $node instanceof GroupUse ? $node->prefix->toString() . '\\' : '';

            foreach ($node->uses as $use) {
                $imports[strtolower($use->getAlias()->toString())] = $prefix . $use->name->toString();
            }
        }

        return [$namespace, $imports];
    }
}

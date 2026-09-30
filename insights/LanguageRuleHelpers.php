<?php

declare(strict_types=1);

namespace Insights;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use ReflectionClass;
use Throwable;

/**
 * Shared plumbing for the insights enforcing `docs/standards/php.md`: which files a rule reads, and
 * what a class inherits. Our own classes are read from their syntax tree, so a fixture tree works
 * without autoloading; anything else (vendor) is read by reflection.
 *
 * Only for subclasses of {@see DomainInsight}.
 */
trait LanguageRuleHelpers
{
    /**
     * @var array<string, ClassLike>|null
     */
    private ?array $classIndex = null;

    /**
     * Every PHP file under the given top-level folders, grandfathered files excluded. `app` comes
     * from the collector; the others are read from disk. Generated `bootstrap/cache/` is never read.
     *
     * @param  string[]  $roots
     * @return string[]
     */
    private function phpFilesIn(array $roots): array
    {
        $files = [];

        foreach ($roots as $root) {
            $candidates = $root === 'app' ? $this->analysedFiles() : $this->filesUnder([$root]);

            foreach ($candidates as $path) {
                if ($this->isGrandfathered($path) || str_starts_with($this->relative($path), 'bootstrap/cache/')) {
                    continue;
                }

                $files[] = $path;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Every class, interface, trait and enum in the given file, anonymous classes included.
     *
     * @return ClassLike[]
     */
    private function classLikesIn(string $path): array
    {
        /** @var ClassLike[] $classes */
        $classes = $this->findIn($path, static fn (Node $node): bool => $node instanceof ClassLike);

        return $classes;
    }

    private function nameOf(ClassLike $class): string
    {
        return $class->namespacedName?->toString() ?? 'class@anonymous';
    }

    private function isOurs(string $className): bool
    {
        foreach (['App\\', 'Tests\\', 'Database\\'] as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The names a class-like extends or implements directly, fully qualified.
     *
     * @return string[]
     */
    private function parentNamesOf(ClassLike $class): array
    {
        $names = [];

        if ($class instanceof Class_) {
            $names = [...($class->extends !== null ? [$class->extends] : []), ...$class->implements];
        } elseif ($class instanceof Interface_) {
            $names = $class->extends;
        } elseif ($class instanceof Enum_) {
            $names = $class->implements;
        }

        return array_map(static fn (Name $name): string => $name->toString(), $names);
    }

    /**
     * Every ancestor of a class-like, direct or not, by fully qualified name.
     *
     * @return string[]
     */
    private function ancestorsOf(ClassLike $class): array
    {
        $ancestors = [];

        foreach ($this->hierarchyAbove($class) as $name => $source) {
            $ancestors[] = $name;
        }

        return $ancestors;
    }

    /**
     * Every declaration of a method above the given class-like: in a parent, an interface, or any
     * ancestor of those. A constructor has none: PHP does not hold it to its parent's signature.
     *
     * @return list<array{class: string, vendor: bool, interface: bool, params: list<array{name: string, typed: bool}>, returnTyped: bool}>
     */
    private function declarationsAbove(ClassLike $class, string $method): array
    {
        $declarations = [];

        if (strtolower($method) === '__construct') {
            return [];
        }

        foreach ($this->hierarchyAbove($class) as $name => $source) {
            if ($source instanceof ClassLike) {
                $node = $source->getMethod($method);

                if ($node === null) {
                    continue;
                }

                $declarations[] = [
                    'class' => $name,
                    'vendor' => ! $this->isOurs($name),
                    'interface' => $source instanceof Interface_,
                    'params' => array_map(
                        fn (Node\Param $param): array => ['name' => $this->parameterName($param), 'typed' => $param->type !== null],
                        $node->params
                    ),
                    'returnTyped' => $node->returnType !== null,
                ];

                continue;
            }

            if (! $source->hasMethod($method)) {
                continue;
            }

            $reflected = $source->getMethod($method);

            if ($reflected->getDeclaringClass()->getName() !== $source->getName()) {
                continue;
            }

            $declarations[] = [
                'class' => $name,
                'vendor' => ! $this->isOurs($name),
                'interface' => $source->isInterface(),
                'params' => array_map(
                    static fn (\ReflectionParameter $param): array => ['name' => $param->getName(), 'typed' => $param->hasType()],
                    $reflected->getParameters()
                ),
                'returnTyped' => $reflected->hasReturnType() || $reflected->hasTentativeReturnType(),
            ];
        }

        return $declarations;
    }

    /**
     * Whether a vendor ancestor declares an untyped property of this name, which a subclass may not
     * redeclare with a type.
     */
    private function vendorDeclaresUntypedProperty(ClassLike $class, string $property): bool
    {
        foreach ($this->hierarchyAbove($class) as $name => $source) {
            if ($source instanceof ReflectionClass && ! $this->isOurs($name) && $source->hasProperty($property)) {
                $reflected = $source->getProperty($property);

                if ($reflected->getDeclaringClass()->getName() === $source->getName() && ! $reflected->hasType()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Every ancestor, keyed by name, as its syntax tree when it is one of ours and indexed, or by
     * reflection otherwise. An ancestor that is neither (an unloadable fixture name) is skipped.
     *
     * @return array<string, ClassLike|ReflectionClass<object>>
     */
    private function hierarchyAbove(ClassLike $class): array
    {
        $found = [];
        $queue = $this->parentNamesOf($class);

        while ($queue !== []) {
            $name = array_shift($queue);

            if (isset($found[$name])) {
                continue;
            }

            $indexed = $this->classIndex()[$name] ?? null;

            if ($indexed !== null) {
                $found[$name] = $indexed;
                array_push($queue, ...$this->parentNamesOf($indexed));

                continue;
            }

            $reflected = $this->reflect($name);

            if ($reflected === null) {
                continue;
            }

            $found[$name] = $reflected;

            foreach ([$reflected->getParentClass() ?: null, ...$reflected->getInterfaces()] as $ancestor) {
                if ($ancestor !== null) {
                    $queue[] = $ancestor->getName();
                }
            }
        }

        return $found;
    }

    /**
     * @return ReflectionClass<object>|null
     */
    private function reflect(string $name): ?ReflectionClass
    {
        try {
            if (class_exists($name) || interface_exists($name)) {
                return new ReflectionClass($name);
            }
        } catch (Throwable) {
            // An unloadable ancestor is treated as unknown.
        }

        return null;
    }

    /**
     * Our own class-likes, from app/, tests/ and database/, by fully qualified name.
     *
     * @return array<string, ClassLike>
     */
    private function classIndex(): array
    {
        if ($this->classIndex !== null) {
            return $this->classIndex;
        }

        $this->classIndex = [];

        foreach ([...$this->analysedFiles(), ...$this->filesUnder(['tests', 'database'])] as $path) {
            foreach ($this->classLikesIn($path) as $class) {
                if ($class->namespacedName !== null) {
                    $this->classIndex[$class->namespacedName->toString()] = $class;
                }
            }
        }

        return $this->classIndex;
    }

    /**
     * The name of a method's parameter.
     */
    private function parameterName(Node\Param $param): string
    {
        return $param->var instanceof Node\Expr\Variable && is_string($param->var->name) ? $param->var->name : '?';
    }
}

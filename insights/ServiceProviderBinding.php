<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;

/**
 * DA-17 — every service and repository interface is bound in its own domain's
 * `{Domain}ServiceProvider`, and a contract implementation is bound by the domain implementing it.
 *
 * A binding is a container call — `bind`, `singleton`, `scoped`, `instance` or their `…If` forms —
 * whose first argument is `X::class`, or an `X::class` key in `$bindings` or `$singletons`. A name
 * appearing anywhere else in the provider, a comment included, does not count.
 */
final class ServiceProviderBinding extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const BINDERS = ['bind', 'bindif', 'singleton', 'singletonif', 'scoped', 'scopedif', 'instance'];

    /** Calls that wire a contract implementation: a binding, a tag, or a contextual `needs`/`give`. */
    private const WIRING = [...self::BINDERS, 'tag', 'needs', 'give', 'when'];

    /** @var array<string, array{bound: string[], wired: string[]}|null> */
    private array $providers = [];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Service and repository interfaces, and contract implementations, are bound in their domain provider (DA-17)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $domain = $this->domainOf($path);
            $layer = $this->layerOf($path);

            if ($domain === null || ! in_array($layer, ['Services', 'Repositories'], true) || $this->isGrandfathered($path)) {
                continue;
            }

            foreach ($this->classesIn($path) as $class) {
                $fqcn = $this->fqcnOf($class);

                if ($fqcn === null) {
                    continue;
                }

                if (str_ends_with($fqcn, 'Interface') && ! $class instanceof Class_) {
                    if (! in_array($fqcn, $this->providerOf($domain)['bound'] ?? [], true)) {
                        $details[] = $this->unbound($path, $domain, sprintf('`%s` is never bound', $class->name));
                    }

                    continue;
                }

                if ($layer === 'Services' && $class instanceof Class_) {
                    foreach ($this->foreignContracts($class, $domain) as $contract) {
                        $wired = $this->providerOf($domain)['wired'] ?? [];

                        if (! in_array($contract, $wired, true) && ! in_array($fqcn, $wired, true)) {
                            $details[] = $this->unbound($path, $domain, sprintf(
                                '`%s` implements the contract `%s` but is never bound or tagged',
                                $class->name,
                                $contract
                            ));
                        }
                    }
                }
            }
        }

        return $details;
    }

    private function unbound(string $path, string $domain, string $what): Details
    {
        if ($this->providerOf($domain) === null) {
            return Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    '%1$s: the domain has no provider. Create `%2$s%3$s/Providers/%3$sServiceProvider.php` '
                    . 'and bind it there (DA-17).',
                    $what,
                    self::DOMAIN_ROOT,
                    $domain
                ));
        }

        return Details::make()
            ->setFile($path)
            ->setMessage(sprintf(
                '%s in %sServiceProvider. Bind it there with `$this->app->bind(X::class, Y::class)`: '
                . 'autowiring works, but the provider no longer shows what this domain offers (DA-17).',
                $what,
                $domain
            ));
    }

    /**
     * Other domains' contracts a class implements.
     *
     * @return string[]
     */
    private function foreignContracts(Class_ $class, string $domain): array
    {
        $contracts = [];

        foreach ($class->implements as $interface) {
            $name = $interface->toString();

            if (preg_match('/^App\\\\Domains\\\\(\w+)\\\\Contracts\\\\/', $name, $match) === 1 && $match[1] !== $domain) {
                $contracts[] = $name;
            }
        }

        return $contracts;
    }

    /**
     * What a domain's `{Domain}ServiceProvider` binds as an abstract, and every class it names in any
     * wiring call. Null when the domain has no such provider.
     *
     * @return array{bound: string[], wired: string[]}|null
     */
    private function providerOf(string $domain): ?array
    {
        if (array_key_exists($domain, $this->providers)) {
            return $this->providers[$domain];
        }

        $path = getcwd() . '/' . self::DOMAIN_ROOT . $domain . '/Providers/' . $domain . 'ServiceProvider.php';

        if (! is_file($path)) {
            return $this->providers[$domain] = null;
        }

        $bound = [];
        $wired = [];

        foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof Property) as $node) {
            if ($node instanceof MethodCall && $node->name instanceof Identifier) {
                $method = $node->name->toLowerString();

                if (! in_array($method, self::WIRING, true)) {
                    continue;
                }

                $arguments = array_map(static fn ($argument): Expr => $argument->value, $node->getArgs());

                foreach ($arguments as $argument) {
                    $wired = array_merge($wired, $this->classesNamed($argument));
                }

                if ((in_array($method, self::BINDERS, true) || $method === 'needs') && isset($arguments[0])) {
                    $bound = array_merge($bound, $this->classesNamed($arguments[0]));
                }

                continue;
            }

            /** @var Property $node */
            foreach ($node->props as $prop) {
                if (! in_array($prop->name->toString(), ['bindings', 'singletons'], true) || ! $prop->default instanceof Array_) {
                    continue;
                }

                foreach ($prop->default->items as $item) {
                    if ($item?->key !== null) {
                        $bound = array_merge($bound, $this->classesNamed($item->key));
                        $wired = array_merge($wired, $this->classesNamed($item->key), $this->classesNamed($item->value));
                    }
                }
            }
        }

        return $this->providers[$domain] = ['bound' => $bound, 'wired' => $wired];
    }

    /**
     * The `X::class` constants an expression names, directly or as items of an array.
     *
     * @return string[]
     */
    private function classesNamed(Expr $expression): array
    {
        if ($expression instanceof ClassConstFetch && $expression->class instanceof Name
            && $expression->name instanceof Identifier && $expression->name->toLowerString() === 'class') {
            return [$expression->class->toString()];
        }

        if ($expression instanceof Array_) {
            $names = [];

            foreach ($expression->items as $item) {
                if ($item !== null) {
                    $names = array_merge($names, $this->classesNamed($item->value));
                }
            }

            return $names;
        }

        return [];
    }
}

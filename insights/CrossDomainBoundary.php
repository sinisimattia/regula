<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * DA-01 / DA-02 / DA-03 — only service interfaces, contracts, entities, enums, events and exceptions
 * may cross a domain boundary, from anywhere in app/ but app/Filament ({@see FilamentDomainBoundary}).
 *
 * A registry is held only to what it calls, by the wiring test in domain-architecture.md.
 */
final class CrossDomainBoundary extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    /**
     * Folders whose classes are part of a domain's public surface.
     *
     * Services are conditional: only an interface may cross (DA-03). Contracts are unconditional
     * and deliberately exempt from the *Interface suffix, because they name a role (DA-12).
     */
    private const EXPORTABLE = ['Contracts', 'Entities', 'Enums', 'Events', 'Exceptions'];

    private const PREFIX = 'App\\Domains\\';

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Only interfaces, contracts, entities, enums, events and exceptions may cross a domain boundary (DA-01, DA-02, DA-03)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $relative = $this->relative($path);

            if (str_starts_with($relative, 'app/Filament/')) {
                continue;
            }

            $ownDomain = $this->domainOf($path);
            $allowedHere = $this->allowedImportsFor($relative);
            $references = $this->isFrameworkRegistry($path)
                ? $this->domainClassesCalled($path)
                : $this->domainReferences((string) file_get_contents($path));

            foreach ($references as $fqcn) {
                $segments = explode('\\', substr($fqcn, strlen(self::PREFIX)));
                $otherDomain = array_shift($segments);
                $folder = $segments[0] ?? '';
                $class = end($segments);

                if ($otherDomain === $ownDomain || $otherDomain === null) {
                    continue;
                }

                if (in_array($folder, self::EXPORTABLE, true)) {
                    continue;
                }

                if ($folder === 'Services' && str_ends_with((string) $class, 'Interface')) {
                    continue;
                }

                // DA-20's single exception: another domain's API Resource may be used to compose
                // a response — nested in your own Resource, or returned from a controller. A
                // Resource is a shape, not behaviour. Everything else under Http/ still crosses
                // nothing.
                if ($folder === 'Http' && ($segments[1] ?? '') === 'Resources') {
                    continue;
                }

                // Grandfathering is per import, not per file: excusing one crossing must never
                // quietly excuse a second one that nobody has looked at.
                if (in_array($fqcn, $allowedHere, true)) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        '`%s` depends on `%s`. %s',
                        $relative,
                        $fqcn,
                        $folder === 'Services'
                            ? 'A service crossing a boundary needs an interface (DA-03).'
                            : sprintf(
                                '`%s/` is internal to the %s domain (DA-02); go through its service interface.',
                                $folder,
                                $otherDomain
                            )
                    ));
            }
        }

        return $details;
    }

    /**
     * The `App\Domains\…` classes a registry calls rather than wires: a static call, `new`, container
     * resolution or a method's parameter type, outside the arguments of a registration call.
     *
     * @return string[]
     */
    private function domainClassesCalled(string $path): array
    {
        $wiring = $this->wiringArgumentNodes($path);
        $found = [];

        foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof Expr) as $node) {
            if (isset($wiring[spl_object_id($node)])) {
                continue;
            }

            $class = match (true) {
                ($node instanceof StaticCall || $node instanceof New_ || $node instanceof StaticPropertyFetch)
                    && $node->class instanceof Name => $node->class->toString(),
                default => $this->resolvedClass($node),
            };

            if ($class !== null && str_starts_with(ltrim($class, '\\'), self::PREFIX)) {
                $found[ltrim($class, '\\')] = true;
            }
        }

        foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof ClassMethod) as $method) {
            /** @var ClassMethod $method */
            foreach ($method->params as $param) {
                foreach ($this->typeNames($param->type) as $type) {
                    if (str_starts_with($type, self::PREFIX)) {
                        $found[$type] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Every distinct `App\Domains\…` name the file actually depends on.
     *
     * Both `use App\Domains\X\Y;` and an inline `\App\Domains\X\Y::class` produce a name token, so
     * one pass catches both. Comments produce no name tokens at all, so a documented reference is
     * correctly ignored.
     *
     * @return string[]
     */
    private function domainReferences(string $source): array
    {
        $found = [];

        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] !== T_NAME_QUALIFIED && $token[0] !== T_NAME_FULLY_QUALIFIED) {
                continue;
            }

            $name = ltrim($token[1], '\\');

            if (str_starts_with($name, self::PREFIX)) {
                $found[$name] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * The imports this specific file is allowed to keep for now.
     *
     * @return string[]
     */
    private function allowedImportsFor(string $relative): array
    {
        /** @var array<string, string[]> $map */
        $map = $this->config['grandfathered'] ?? [];

        return $map[$relative] ?? [];
    }
}

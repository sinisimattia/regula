<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

/**
 * ENT-06 — an entity is pure PHP: no framework class, no container, no Laravel helper.
 *
 * Checks every file in a domain's `Entities/`. Only names the parser sees as code count, so a
 * framework class mentioned in a comment is not a dependency.
 */
final class PureEntities extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const FRAMEWORK_PREFIXES = ['Illuminate\\', 'Laravel\\', 'Filament\\', 'Livewire\\', 'Spatie\\', 'App\\Models\\'];

    /**
     * Laravel helpers that reach the container, the request, the clock or the framework's types.
     */
    private const HELPERS = [
        'app', 'resolve', 'config', 'env', 'now', 'today', 'event', 'dispatch', 'broadcast', 'request',
        'session', 'auth', 'cache', 'route', 'url', 'logger', 'abort', 'trans', '__', 'collect', 'str',
    ];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Entities are pure PHP, with no framework dependency (ENT-06)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->layerOf($path) !== 'Entities' || $this->isGrandfathered($path)) {
                continue;
            }

            $reported = [];

            foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof Name || $node instanceof FuncCall) as $node) {
                $problem = $node instanceof FuncCall ? $this->helperCalled($node) : $this->frameworkNamed($node);

                if ($problem === null || isset($reported[$problem])) {
                    continue;
                }

                $reported[$problem] = true;
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d depends on %s. An entity is pure PHP, constructible in a test without '
                        . 'booting Laravel; do the framework work in the repository or service (ENT-06).',
                        $node->getStartLine(),
                        $problem
                    ));
            }
        }

        return $details;
    }

    private function helperCalled(FuncCall $call): ?string
    {
        if (! $call->name instanceof Name || str_contains($call->name->toString(), '\\')) {
            return null;
        }

        $name = $call->name->toLowerString();

        return in_array($name, self::HELPERS, true) ? sprintf('the `%s()` helper', $name) : null;
    }

    private function frameworkNamed(Node $node): ?string
    {
        /** @var Name $node */
        $name = $node->toString();

        foreach (self::FRAMEWORK_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return sprintf('`%s`', $name);
            }
        }

        return str_contains('\\' . $name, '\\Models\\') ? sprintf('the model `%s`', $name) : null;
    }
}

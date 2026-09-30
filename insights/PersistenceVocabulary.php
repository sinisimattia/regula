<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * ENT-02 / ENT-08 / PHP-03 — services and repositories speak `store` / `get` / `delete`, and a
 * `get*` throws rather than returning null.
 *
 * Checks the public methods of every class and interface in a domain's `Services/` and
 * `Repositories/`. Only Laravel's drift-in vocabulary is flagged, not every other verb: ENT-02
 * allows domain-specific names where genuinely needed.
 */
final class PersistenceVocabulary extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const LAYERS = ['Services', 'Repositories'];

    private const FORBIDDEN = '/^(find|create|update|save|associate|dissociate)(?=[A-Z]|$)/';

    private const REPLACEMENT = [
        'find' => '`get…` (throwing when missing)',
        'create' => '`store…`',
        'update' => '`store…`',
        'save' => '`store…`',
        'associate' => '`attach…To…`',
        'dissociate' => '`detach…From…`',
    ];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Services and repositories use store / get / delete, and get* never returns null (ENT-02, ENT-08, PHP-03)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if (! in_array($this->layerOf($path), self::LAYERS, true) || $this->isGrandfathered($path)) {
                continue;
            }

            foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof ClassMethod && $node->isPublic()) as $method) {
                /** @var ClassMethod $method */
                $name = $method->name->toString();

                if (preg_match(self::FORBIDDEN, $name, $match) === 1) {
                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d: `%s()` uses Laravel\'s vocabulary. Name it %s, identically in the '
                            . 'service and the repository (ENT-02).',
                            $method->getStartLine(),
                            $name,
                            self::REPLACEMENT[$match[1]]
                        ));
                }

                if (preg_match('/^get(?=[A-Z]|$)/', $name) === 1 && $this->isNullableType($method->returnType)) {
                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d: `%s()` may return null. A `get*` throws a domain exception when '
                            . 'the record is missing, declares it with `@throws`, and its return type is '
                            . 'not nullable (ENT-08, PHP-03).',
                            $method->getStartLine(),
                            $name
                        ));
                }
            }
        }

        return $details;
    }
}

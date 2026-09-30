<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;

/**
 * DB-01 — a controller never references an Eloquent model, vendor ones included, not even as a type.
 *
 * A bare `use` line is not a reference by itself; what the import is used for is.
 */
final class NoModelsInControllers extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const MODEL_NAMESPACE = '/^App\\\\(?:Models|Domains\\\\\w+\\\\(?:\w+\\\\)*Models)\\\\/';

    private const DOC_TAGS = '/@(?:param|return|var|throws|property(?:-read|-write)?)[ \t]+(.+)$/m';

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Controllers never reference a model; they talk to services (DB-01)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if (! str_contains($this->relative($path), '/Http/Controllers/') || $this->isGrandfathered($path)) {
                continue;
            }

            foreach ($this->modelReferences($path) as $line => $models) {
                foreach ($models as $model) {
                    $details[] = Details::make()
                        ->setFile($path)
                        ->setMessage(sprintf(
                            'Line %d references `%s`. A controller never touches a model, not even as '
                            . 'a type: read what it needs through the service interface or the '
                            . 'framework\'s contracts, such as `Authenticatable` (DB-01).',
                            $line,
                            $model
                        ));
                }
            }
        }

        return $details;
    }

    /**
     * Model names the file uses, by line. Name resolution leaves `use` lines unresolved, so only
     * the places an import is used come back as fully qualified names.
     *
     * @return array<int, string[]>
     */
    private function modelReferences(string $path): array
    {
        $found = [];

        foreach ($this->findIn($path, static fn (Node $node): bool => $node instanceof FullyQualified) as $name) {
            /** @var FullyQualified $name */
            if ($this->isModel($name->toString())) {
                $found[$name->getStartLine()][$name->toString()] = true;
            }
        }

        foreach ($this->findIn($path, static fn (Node $node): bool => $node->getDocComment() !== null) as $node) {
            $doc = $node->getDocComment();

            if ($doc === null || preg_match_all(self::DOC_TAGS, $doc->getText(), $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $tagBody) {
                foreach ($this->docTypeNames($path, $this->leadingType($tagBody)) as $type) {
                    if ($this->isModel(ltrim($type, '\\'))) {
                        $found[$doc->getStartLine()][ltrim($type, '\\')] = true;
                    }
                }
            }
        }

        ksort($found);

        return array_map(static fn (array $names): array => array_keys($names), $found);
    }

    /**
     * The type at the start of a tag's text, generics included: `Collection<int, User> $users` gives
     * `Collection<int, User>`.
     */
    private function leadingType(string $tagBody): string
    {
        $tagBody = (string) preg_replace('#\s*\*/.*$#', '', $tagBody);
        $depth = 0;

        foreach (str_split($tagBody) as $offset => $character) {
            $depth += match ($character) {
                '<', '{', '(', '[' => 1,
                '>', '}', ')', ']' => -1,
                default => 0,
            };

            if ($depth <= 0 && ctype_space($character)) {
                return substr($tagBody, 0, $offset);
            }
        }

        return $tagBody;
    }

    /**
     * Decided by kind; the namespace test covers a fixture model that cannot be resolved.
     */
    private function isModel(string $fqcn): bool
    {
        return $this->isEloquentModel($fqcn) || preg_match(self::MODEL_NAMESPACE, $fqcn) === 1;
    }
}

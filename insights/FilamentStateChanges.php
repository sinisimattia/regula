<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * DA-22 — the admin panel reads models, but changes state through a domain's service interface.
 *
 * Flags the Eloquent writes a Filament page or action reaches for: `->save()`, `->delete()`,
 * `->forceDelete()`, `->restore()` and `->update(...)` on anything but `$this`, and `::create()`
 * and friends on a model or on a dynamic class such as `static::getModel()`. Filling a form is not
 * a write and is left alone.
 */
final class FilamentStateChanges extends DomainInsight implements HasDetails
{
    use InspectsDomainCode;

    private const ROOT = 'app/Filament/';

    private const INSTANCE_WRITES = ['save', 'delete', 'forcedelete', 'restore', 'update'];

    private const STATIC_WRITES = ['create', 'forcecreate', 'firstorcreate', 'updateorcreate', 'destroy', 'insert', 'upsert'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Filament changes state through a domain\'s service interface, never the model (DA-22)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if (! str_starts_with($this->relative($path), self::ROOT) || $this->isGrandfathered($path)) {
                continue;
            }

            foreach ($this->findIn($path, fn (Node $node): bool => $this->write($node) !== null) as $node) {
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d writes through Eloquent with `%s`. Call the domain\'s service interface '
                        . 'instead, so the panel fires the same events and keeps the same invariants as '
                        . 'the API (DA-22).',
                        $node->getStartLine(),
                        $this->write($node)
                    ));
            }
        }

        return $details;
    }

    private function write(Node $node): ?string
    {
        if (($node instanceof MethodCall || $node instanceof NullsafeMethodCall) && $node->name instanceof Identifier) {
            $method = $node->name->toLowerString();

            // `$this->save()` on a Filament page is the page's own save, which reaches
            // handleRecordUpdate — not a model write.
            $onThis = $node->var instanceof Variable && $node->var->name === 'this';

            if (! in_array($method, self::INSTANCE_WRITES, true) || $onThis) {
                return null;
            }

            if ($method === 'update' && $node->getArgs() === []) {
                return null;
            }

            return '->' . $node->name->toString() . '()';
        }

        if ($node instanceof StaticCall && $node->name instanceof Identifier
            && in_array($node->name->toLowerString(), self::STATIC_WRITES, true)) {
            if (! $node->class instanceof Name) {
                return '::' . $node->name->toString() . '()';
            }

            return $this->isEloquentModel($node->class->toString())
                ? $node->class->getLast() . '::' . $node->name->toString() . '()'
                : null;
        }

        return null;
    }
}

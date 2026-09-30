<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * HTTP-14 — an API Resource sends dates as Unix timestamps.
 *
 * Flags, inside `Http/Resources/`, the Carbon and DateTime calls that turn a date into a string.
 * `format()` is flagged only with a literal or DateTime-constant pattern, since plenty of
 * non-date objects have a `format()` too.
 */
final class UnixTimestamps extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    /**
     * Matched exactly: `toJSON` is Carbon's spelling, `toJson` is a model's or a resource's.
     */
    private const TO_STRING = [
        'toIso8601String', 'toIso8601ZuluString', 'toISOString', 'toDateTimeString', 'toDateTimeLocalString',
        'toDateString', 'toTimeString', 'toAtomString', 'toRfc3339String', 'toRfc2822String', 'toRfc7231String',
        'toW3cString', 'toCookieString', 'toDayDateTimeString', 'toFormattedDateString', 'toJSON',
        'isoFormat', 'translatedFormat',
    ];

    private const DATE_CLASSES = [
        'DateTimeInterface', 'DateTime', 'DateTimeImmutable',
        'Carbon\Carbon', 'Carbon\CarbonImmutable', 'Carbon\CarbonInterface', 'Illuminate\Support\Carbon',
    ];

    public function getTitle(): string
    {
        return 'Resources send dates as Unix timestamps (HTTP-14)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->isGrandfathered($path) || ! $this->isResource($path)) {
                continue;
            }

            foreach ($this->findIn($path, fn (Node $node): bool => $this->formatsDate($node)) as $call) {
                /** @var MethodCall|NullsafeMethodCall $call */
                $details[] = $this->detail($path, sprintf(
                    'Line %d sends a date as a string with `%s()`. Send `->timestamp` or `->getTimestamp()` (HTTP-14).',
                    $call->getStartLine(),
                    $call->name instanceof Identifier ? $call->name->toString() : 'format',
                ));
            }
        }

        return $details;
    }

    private function formatsDate(Node $node): bool
    {
        if (! ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) || ! $node->name instanceof Identifier) {
            return false;
        }

        // $this->toJson() is the resource serialising itself.
        if ($node->var instanceof Variable && $node->var->name === 'this') {
            return false;
        }

        if (in_array($node->name->toString(), self::TO_STRING, true)) {
            return true;
        }

        if ($node->name->toLowerString() !== 'format') {
            return false;
        }

        $pattern = $node->getArgs()[0]->value ?? null;

        return $pattern instanceof String_
            || ($pattern instanceof ClassConstFetch && $pattern->class instanceof Name
                && in_array($pattern->class->toString(), self::DATE_CLASSES, true));
    }
}

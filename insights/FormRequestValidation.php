<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Return_;

/**
 * HTTP-08 — validation lives in a Form Request with its rules and custom messages, in the rule
 * style of its sibling requests.
 *
 * Flags inline validation in a controller, a Form Request whose `rules()` has no `messages()`,
 * and a request whose rule style differs from the rest of its directory.
 */
final class FormRequestValidation extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const FORM_REQUEST = 'Illuminate\Foundation\Http\FormRequest';

    private const VALIDATOR_FACADES = ['Illuminate\Support\Facades\Validator', 'Validator'];

    public function getTitle(): string
    {
        return 'Validation lives in a Form Request, with messages, in its siblings\' style (HTTP-08)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];
        $styles = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->isGrandfathered($path) || ! $this->isHttpGoverned($path)) {
                continue;
            }

            if ($this->isController($path)) {
                foreach ($this->findIn($path, fn (Node $node): bool => $this->isInlineValidation($node)) as $node) {
                    $details[] = $this->detail($path, sprintf(
                        'Line %d validates inline in a controller. Move the rules into a Form Request '
                        . 'and type-hint it on the action (HTTP-08).',
                        $node->getStartLine(),
                    ));
                }
            }

            foreach ($this->classesIn($path) as $class) {
                if ($class->isAbstract() || ! $this->descendsFrom($class, self::FORM_REQUEST) || $class->getMethod('rules') === null) {
                    continue;
                }

                if ($class->getMethod('messages') === null) {
                    $details[] = $this->detail($path, sprintf(
                        '%s defines rules() but no messages(). Give every rule a custom message (HTTP-08).',
                        $class->name,
                    ));
                }

                $style = $this->ruleStyle($class);

                if ($style !== null) {
                    $styles[dirname($path)][$path] = $style;
                }
            }
        }

        foreach ($styles as $files) {
            $details = [...$details, ...$this->styleOutliers($files)];
        }

        return $details;
    }

    private function isInlineValidation(Node $node): bool
    {
        // $request->validate(), $this->validate(), request()->validate() — but not
        // $this->couponService->validate(), which is a service call that happens to share the name.
        if (($node instanceof MethodCall || $node instanceof NullsafeMethodCall) && $node->name instanceof Identifier) {
            $onRequestOrController = $node->var instanceof Variable
                || ($node->var instanceof FuncCall && $node->var->name instanceof Name && strtolower($node->var->name->getLast()) === 'request');

            return $onRequestOrController && in_array($node->name->toLowerString(), ['validate', 'validatewithbag'], true);
        }

        if ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Identifier) {
            return in_array($node->class->toString(), self::VALIDATOR_FACADES, true) && $node->name->toLowerString() === 'make';
        }

        return $node instanceof FuncCall && $node->name instanceof Name && strtolower($node->name->getLast()) === 'validator';
    }

    /**
     * 'array' when every rule is `['required', 'string']`, 'string' when every rule is
     * `'required|string'`, 'mixed' for both, null when rules() returns nothing readable.
     */
    private function ruleStyle(Class_ $class): ?string
    {
        $seen = [];
        $rules = $class->getMethod('rules');

        foreach ($this->findInBody($rules->stmts ?? [], fn (Node $node): bool => $node instanceof Return_) as $return) {
            if (! $return instanceof Return_ || ! $return->expr instanceof Array_) {
                continue;
            }

            foreach ($return->expr->items as $item) {
                if ($item->value instanceof Array_) {
                    $seen['array'] = true;
                } elseif ($item->value instanceof String_) {
                    $seen['string'] = true;
                }
            }
        }

        return match (count($seen)) {
            0 => null,
            1 => (string) array_key_first($seen),
            default => 'mixed',
        };
    }

    /**
     * @param  array<string, string>  $files  path => style
     * @return list<\NunoMaduro\PhpInsights\Domain\Details>
     */
    private function styleOutliers(array $files): array
    {
        $details = [];
        $counts = array_count_values(array_filter($files, fn (string $style): bool => $style !== 'mixed'));
        arsort($counts);
        $majority = count($counts) === 1 || (count($counts) > 1 && max($counts) > min($counts))
            ? (string) array_key_first($counts)
            : null;

        foreach ($files as $path => $style) {
            if ($style === 'mixed') {
                $details[] = $this->detail($path, 'rules() mixes array-style and pipe-string rules. Use one style, '
                    . 'the one its sibling requests use (HTTP-08).');
            } elseif ($majority === null && count($counts) > 1) {
                $details[] = $this->detail($path, sprintf('rules() is %s-style, but the requests in this directory are '
                    . 'split evenly between styles. Settle on one (HTTP-08).', $style));
            } elseif ($majority !== null && $style !== $majority) {
                $details[] = $this->detail($path, sprintf('rules() is %s-style, but its sibling requests are %s-style. '
                    . 'Match them (HTTP-08).', $style, $majority));
            }
        }

        return $details;
    }
}

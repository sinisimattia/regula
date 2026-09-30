<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

/**
 * PHP-01 and PHP-02 — every method and named function declares a native return type, and every
 * parameter a native type. A `@return` or `@param` tag does not count. Closures are not checked.
 *
 * An override of a vendor method that leaves the same slot untyped is exempt, as is a promoted
 * property redeclaring an untyped vendor property: PHP will not let either be typed.
 */
final class NativeTypes extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'database', 'routes'];

    private const NO_RETURN_TYPE = ['__construct', '__destruct'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Every method and named function declares native parameter and return types (PHP-01, PHP-02)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            foreach ($this->classLikesIn($path) as $class) {
                foreach ($class->getMethods() as $method) {
                    $label = sprintf('`%s::%s()`', $class->name?->toString() ?? 'class@anonymous', $method->name->toString());
                    array_push($details, ...$this->untyped($path, $label, $method, $class));
                }
            }

            /** @var Function_[] $functions */
            $functions = $this->findIn($path, static fn (Node $node): bool => $node instanceof Function_);

            foreach ($functions as $function) {
                array_push($details, ...$this->untyped($path, sprintf('`%s()`', $function->name->toString()), $function, null));
            }
        }

        return $details;
    }

    /**
     * @return Details[]
     */
    private function untyped(string $path, string $label, ClassMethod|Function_ $function, ?ClassLike $class): array
    {
        $details = [];
        $name = $function->name->toString();
        $declarations = $class !== null ? $this->declarationsAbove($class, $name) : [];

        if ($function->returnType === null && ! in_array(strtolower($name), self::NO_RETURN_TYPE, true)
            && ! $this->vendorLeavesUntyped($declarations, null)) {
            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    'Line %d: %s has no return type. Declare a native one; a @return tag is not enough (PHP-01).',
                    $function->getStartLine(),
                    $label
                ));
        }

        foreach ($function->params as $position => $param) {
            if ($param->type !== null || $this->vendorLeavesUntyped($declarations, $position)
                || $this->redeclaresUntypedVendorProperty($param, $class)) {
                continue;
            }

            $details[] = Details::make()
                ->setFile($path)
                ->setMessage(sprintf(
                    'Line %d: parameter `$%s` of %s has no type. Declare a native one; a @param tag is '
                    . 'not enough (PHP-02).',
                    $param->getStartLine(),
                    $this->parameterName($param),
                    $label
                ));
        }

        return $details;
    }

    /**
     * Whether a vendor declaration of the method leaves the return type (position null) or the
     * parameter at the given position untyped.
     *
     * @param  list<array{class: string, vendor: bool, interface: bool, params: list<array{name: string, typed: bool}>, returnTyped: bool}>  $declarations
     */
    private function vendorLeavesUntyped(array $declarations, ?int $position): bool
    {
        foreach ($declarations as $declaration) {
            if (! $declaration['vendor']) {
                continue;
            }

            if ($position === null ? ! $declaration['returnTyped'] : ! ($declaration['params'][$position]['typed'] ?? true)) {
                return true;
            }
        }

        return false;
    }

    private function redeclaresUntypedVendorProperty(Param $param, ?ClassLike $class): bool
    {
        return $class !== null && $param->flags !== 0
            && $this->vendorDeclaresUntypedProperty($class, $this->parameterName($param));
    }
}

<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node\Stmt\Class_;

/**
 * PHP-17 for tests: a concrete class extending a `*TestCase` is named `{Subject}Test`. PHPUnit
 * only discovers files ending in `Test.php`, so a misnamed test never runs.
 */
final class TestClassNaming extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Test classes are named {Subject}Test (PHP-17)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(['tests']) as $path) {
            foreach ($this->classLikesIn($path) as $class) {
                if (! $class instanceof Class_ || $class->name === null || $class->isAbstract()) {
                    continue;
                }

                $name = $class->name->toString();

                if (str_ends_with($name, 'Test') || str_ends_with($name, 'TestCase') || ! $this->extendsTestCase($this->ancestorsOf($class))) {
                    continue;
                }

                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        '`%s` is a test case but is not named `{Subject}Test`, so PHPUnit will not '
                        . 'discover it. Rename the class and its file (PHP-17).',
                        $name
                    ));
            }
        }

        return $details;
    }

    /**
     * @param  string[]  $ancestors
     */
    private function extendsTestCase(array $ancestors): bool
    {
        foreach ($ancestors as $ancestor) {
            if (str_ends_with($ancestor, 'TestCase')) {
                return true;
            }
        }

        return false;
    }
}

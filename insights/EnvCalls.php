<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

/**
 * PHP-15 — `env()` is called only inside `config/`. Configuration is cached in production, and
 * `env()` outside `config/` returns null once it is.
 */
final class EnvCalls extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    private const SCOPE = ['app', 'routes', 'database', 'bootstrap', 'tests'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'env() is called only inside config/ (PHP-15)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->phpFilesIn(self::SCOPE) as $path) {
            $calls = $this->findIn($path, static fn (Node $node): bool => $node instanceof FuncCall
                && $node->name instanceof Name
                && $node->name->toLowerString() === 'env');

            foreach ($calls as $call) {
                $details[] = Details::make()
                    ->setFile($path)
                    ->setMessage(sprintf(
                        'Line %d calls env(), which returns null once config is cached. Read the value '
                        . 'with config(...), adding a key to a config/ file that calls env() there (PHP-15).',
                        $call->getStartLine()
                    ));
            }
        }

        return $details;
    }
}

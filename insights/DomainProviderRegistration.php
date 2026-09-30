<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Details;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * PHP-17 with DA-17: a domain with services or repositories has a `{Domain}ServiceProvider`, and
 * `config/app.php` registers it. Without the registration none of the domain's bindings exist.
 */
final class DomainProviderRegistration extends DomainInsight implements HasDetails
{
    use LanguageRuleHelpers;

    /**
     * Where providers are registered: the pre-11 skeleton this app uses, and the 11+ one.
     */
    private const REGISTRIES = ['config/app.php', 'bootstrap/providers.php'];

    public function hasIssue(): bool
    {
        return $this->getDetails() !== [];
    }

    public function getTitle(): string
    {
        return 'Every domain with services has a registered {Domain}ServiceProvider (PHP-17, DA-17)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];
        $root = (string) getcwd();
        $registered = $this->registeredProviders($root);

        foreach (glob($root . '/' . self::DOMAIN_ROOT . '*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (! is_dir($directory . '/Services') && ! is_dir($directory . '/Repositories')) {
                continue;
            }

            $domain = basename($directory);
            $provider = sprintf('%s/Providers/%sServiceProvider.php', $directory, $domain);
            $class = sprintf('App\Domains\%s\Providers\%sServiceProvider', $domain, $domain);

            if ($this->isGrandfathered($directory . '/')) {
                continue;
            }

            if (! is_file($provider)) {
                $details[] = Details::make()
                    ->setFile($directory)
                    ->setMessage(sprintf(
                        'The %s domain has services or repositories but no Providers/%sServiceProvider.php '
                        . 'to bind them. Create it with `php artisan make:provider` and register it in '
                        . 'config/app.php (PHP-17, DA-17).',
                        $domain,
                        $domain
                    ));

                continue;
            }

            if (! in_array($class, $registered, true)) {
                $details[] = Details::make()
                    ->setFile($provider)
                    ->setMessage(sprintf(
                        '`%sServiceProvider` is never registered, so none of its bindings exist. Add '
                        . '`%sServiceProvider::class` to the providers in config/app.php (PHP-17, DA-17).',
                        $domain,
                        $domain
                    ));
            }
        }

        return $details;
    }

    /**
     * Every class named in a provider registry, as `Foo::class` or as a string.
     *
     * @return string[]
     */
    private function registeredProviders(string $root): array
    {
        $classes = [];

        foreach (self::REGISTRIES as $registry) {
            if (! is_file($root . '/' . $registry)) {
                continue;
            }

            $references = $this->findIn($root . '/' . $registry, static fn (Node $node): bool => ($node instanceof ClassConstFetch
                && $node->class instanceof Name && $node->name instanceof Identifier && $node->name->toLowerString() === 'class')
                || $node instanceof String_);

            foreach ($references as $reference) {
                $classes[] = $reference instanceof ClassConstFetch && $reference->class instanceof Name
                    ? $reference->class->toString()
                    : ltrim($reference instanceof String_ ? $reference->value : '', '\\');
            }
        }

        return $classes;
    }
}

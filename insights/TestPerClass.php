<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;

/**
 * TEST-02 — every class with behaviour of its own has a test.
 *
 * Checks, by folder, the kinds that carry behaviour: services, repositories, controllers,
 * resources, middleware, listeners, jobs, rules, notifications, mailables and console commands.
 * Each concrete class `app/<path>/<Name>.php` needs `tests/Unit/Application/<path>/<Name>Test.php`
 * or `tests/Feature/Application/<path>/<Name>Test.php`. Interfaces, traits,
 * enums and abstract classes are skipped, and so is a class that declares no methods: it only
 * sets properties on a framework class, and testing it would test the framework (TEST-14).
 */
final class TestPerClass extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const DOMAIN_KINDS = '#^app/Domains/[^/]+/(Services|Repositories|Http/Controllers|Http/Resources|Http/Middleware'
        . '|Listeners|Subscribers|Jobs|Rules|Notifications|Mail|Console/Commands)/#';

    private const SHARED_KINDS = '#^app/(Http/Controllers|Http/Middleware|Mail|Jobs|Console/Commands)/#';

    /**
     * The base every controller extends; it has nothing to test on its own.
     */
    private const BASE_CONTROLLER = 'app/Http/Controllers/Controller.php';

    public function getTitle(): string
    {
        return 'Every class with behaviour of its own has a test (TEST-02)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $root = (string) getcwd();
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $relative = $this->relative($path);

            if ($this->isGrandfathered($path) || $relative === self::BASE_CONTROLLER
                || (preg_match(self::DOMAIN_KINDS, $relative) !== 1 && preg_match(self::SHARED_KINDS, $relative) !== 1)) {
                continue;
            }

            $folder = dirname(substr($relative, strlen('app/')));
            $folder = $folder === '.' ? '' : $folder . '/';

            foreach ($this->classesIn($path) as $class) {
                $expected = [
                    'tests/Unit/Application/' . $folder . $class->name . 'Test.php',
                    'tests/Feature/Application/' . $folder . $class->name . 'Test.php',
                ];

                if ($class->isAbstract() || $class->getMethods() === []
                    || is_file($root . '/' . $expected[0]) || is_file($root . '/' . $expected[1])) {
                    continue;
                }

                $details[] = $this->detail($path, sprintf(
                    '%s has no test at %s or %s. The test mirrors the class\'s path; cover its happy path, '
                    . 'failure paths and edge cases (TEST-02).',
                    $class->name,
                    $expected[0],
                    $expected[1],
                ));
            }
        }

        return $details;
    }
}

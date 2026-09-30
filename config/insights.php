<?php

declare(strict_types=1);

use Insights\BackendNames;
use Insights\ClassNaming;
use Insights\CrossDomainBoundary;
use Insights\DomainFolderStructure;
use Insights\FilamentDomainBoundary;
use Insights\HttpExceptionsFolder;
use Insights\EnvCalls;
use Insights\EnvironmentChecks;
use Insights\NativeTypes;
use Insights\ArrayElementTypes;
use Insights\ConstructorPromotion;
use Insights\EnumCaseNaming;
use Insights\NamedArguments;
use Insights\FluentSetters;
use Insights\UnsuffixedKinds;
use Insights\ParentClassNaming;
use Insights\ListenerNaming;
use Insights\TestClassNaming;
use Insights\DomainProviderRegistration;
use Insights\InterfaceDocblocks;
use Insights\CommentLength;
use Insights\ApplicationName;
use Insights\RepositoryInjection;
use Insights\EventDispatch;
use Insights\PersistenceVocabulary;
use Insights\EloquentInRepositories;
use Insights\PureEntities;
use Insights\TypedIds;
use Insights\ServicePublicSurface;
use Insights\SubscriberPlacement;
use Insights\CommandPlacement;
use Insights\ContractsFolder;
use Insights\ModelShape;
use Insights\ModelFactories;
use Insights\ScheduledConfirmableCommands;
use Insights\JsonColumnConversion;
use Insights\PostgresMigrations;
use Insights\FilamentStateChanges;
use Insights\FormRequestValidation;
use Insights\FormatOnlyValidation;
use Insights\HttpExceptionKeepsPrevious;
use Insights\EntityExtraction;
use Insights\ThinControllers;
use Insights\ResourceResponses;
use Insights\UnixTimestamps;
use Insights\NamedRouteLinks;
use Insights\PhpUnitOnly;
use Insights\TestPerClass;
use Insights\ThrowsLast;
use Insights\NoFilamentTests;
use Insights\NoRealConfigAssertions;
use Insights\NoExternalServicesInTests;
use Insights\ServiceNaming;
use Insights\ServiceProviderBinding;
use Insights\NoModelsInControllers;
use Insights\RepositoryDelegationNaming;
use Insights\ThemeTokens;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenDefineFunctions;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenNormalClasses;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenPrivateMethods;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenTraits;
use NunoMaduro\PhpInsights\Domain\Metrics\Architecture\Classes;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Commenting\TodoSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Files\LineLengthSniff;
use SlevomatCodingStandard\Sniffs\Classes\SuperfluousExceptionNamingSniff;
use SlevomatCodingStandard\Sniffs\Classes\SuperfluousInterfaceNamingSniff;
use SlevomatCodingStandard\Sniffs\Commenting\UselessFunctionDocCommentSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\AlphabeticallySortedUsesSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DeclareStrictTypesSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DisallowArrayTypeHintSyntaxSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DisallowMixedTypeHintSniff;

/*
|--------------------------------------------------------------------------
| PHP Insights — the analysis half of the quality gate
|--------------------------------------------------------------------------
|
| Two tools, one job each, so they never fight:
|
|   PHP-CS-Fixer  owns FORMATTING. It auto-fixes, and runs as `composer lint:check` in CI.
|   PHP Insights  owns ANALYSIS.   It reports and never rewrites, and runs as `composer insights`.
|
| That split is why the `remove` list below exists: every sniff in it is one PHP-CS-Fixer already
| corrects. Removing them here is not "turning off a rule" — it is refusing to have two tools with
| an opinion about the same line.
|
| The rules this file enforces are written down in `docs/standards/`. Each custom insight names the
| rule ID it implements.
|
*/

return [

    'preset' => 'laravel',

    'ide' => null,

    'exclude' => [
        // Tooling, not application code — outside the canon's scope in the same way cloud/aws is.
        'insights',
    ],

    'add' => [
        Classes::class => [
            /*
             * ForbiddenFinalClasses is deliberately off: nothing in docs/standards/ asks for it, so
             * enabling it is a policy decision for the team to take in the canon first.
             *
             * ForbiddenFinalClasses::class,
             */

            // The structural canon — docs/standards/domain-architecture.md
            ServiceNaming::class,

            // Every mechanical rule in docs/standards/, one insight per rule or group
            ClassNaming::class,
            ServiceProviderBinding::class,
            CrossDomainBoundary::class,
            DomainFolderStructure::class,
            FilamentDomainBoundary::class,
            HttpExceptionsFolder::class,
            BackendNames::class,
            EnvCalls::class,
            EnvironmentChecks::class,
            NativeTypes::class,
            ArrayElementTypes::class,
            ConstructorPromotion::class,
            EnumCaseNaming::class,
            NamedArguments::class,
            FluentSetters::class,
            UnsuffixedKinds::class,
            ParentClassNaming::class,
            ListenerNaming::class,
            TestClassNaming::class,
            DomainProviderRegistration::class,
            InterfaceDocblocks::class,
            CommentLength::class,
            ApplicationName::class,
            ThemeTokens::class,
            ThrowsLast::class,
            RepositoryInjection::class,
            EventDispatch::class,
            PersistenceVocabulary::class,
            EloquentInRepositories::class,
            PureEntities::class,
            TypedIds::class,
            ServicePublicSurface::class,
            SubscriberPlacement::class,
            CommandPlacement::class,
            ContractsFolder::class,
            ModelShape::class,
            ModelFactories::class,
            ScheduledConfirmableCommands::class,
            JsonColumnConversion::class,
            PostgresMigrations::class,
            FilamentStateChanges::class,
            FormRequestValidation::class,
            FormatOnlyValidation::class,
            HttpExceptionKeepsPrevious::class,
            EntityExtraction::class,
            ThinControllers::class,
            ResourceResponses::class,
            UnixTimestamps::class,
            NamedRouteLinks::class,
            PhpUnitOnly::class,
            TestPerClass::class,
            NoFilamentTests::class,
            NoRealConfigAssertions::class,
            NoExternalServicesInTests::class,
            NoModelsInControllers::class,
            RepositoryDelegationNaming::class,
        ],
    ],

    /*
     * Owned by PHP-CS-Fixer (see .php-cs-fixer.dist.php). Do not re-enable: it would give two
     * tools a say over the same formatting, and they disagree.
     */
    'remove' => [
        AlphabeticallySortedUsesSniff::class,
        DeclareStrictTypesSniff::class,
        ForbiddenDefineFunctions::class,
        ForbiddenNormalClasses::class,
        ForbiddenTraits::class,
        UselessFunctionDocCommentSniff::class,

        /*
         * These three do not merely overlap with the formatter — they contradict the canon
         * outright. Leaving them on would mean CI failing correct code.
         */

        // Flags the `Interface` suffix as superfluous. DA-16 requires it: a domain's public
        // surface is named {Noun}ServiceInterface, and that naming is how a reader tells an
        // exported service from an internal one.
        SuperfluousInterfaceNamingSniff::class,

        // Same objection to the `Exception` suffix. Every domain exception here is named
        // {What}Exception, and a caller reading a catch block should not have to guess.
        SuperfluousExceptionNamingSniff::class,

        // Flags `Comment[]` and demands generic syntax. PHP-04 requires `Type[]`, because it is
        // the only array element documentation the IDE and the reader both understand here.
        DisallowArrayTypeHintSyntaxSniff::class,

        // "Private methods are not idiomatic in Laravel" — DA-14 says the opposite and means it:
        // anything a consumer does not need is private or protected, so the public surface of a
        // service is exactly its interface.
        ForbiddenPrivateMethods::class,

        // It flags `array<string, mixed>` PHPDoc, the annotation PHP-04 asks for, so an annotated
        // array scores worse than a bare one; ENT-01 also permits exactly that config payload.
        DisallowMixedTypeHintSniff::class,

        // Flags every TODO, including `TODO(<KEY>-1234):` — the exact form PHP-13 holds up as
        // correct and encourages. A rule that fails the canon's own worked example is not a rule.
        TodoSniff::class,

        // Insights' 80-character default is a limit nobody agreed to and no tool can fix. A line
        // limit, if we want one, is decided in php.md first: the canon decides, the tool enforces.
        LineLengthSniff::class,
    ],

    'config' => [
        // Grandfathering is per rule, never global, and every entry names the debt it represents.
        // Adding one is a decision, not a convenience (docs/standards/README.md).

        ServiceNaming::class => [
            'grandfathered' => [],
        ],

        ClassNaming::class => [
            // Each entry is a rename that would spill outside the change at hand (GIT-06). List
            // files, never folders, or the next file written there is excused too.
            'grandfathered' => [],
        ],

        ServiceProviderBinding::class => [
            'grandfathered' => [],
        ],

        ModelFactories::class => [
            // Models backed by a store other than the database, which have no factory (DB-09).
            'external' => [],
            'grandfathered' => [],
        ],

        CrossDomainBoundary::class => [
            // Keyed by file, then by the exact import excused, so a new crossing in that file is still caught.
            'grandfathered' => [],
        ],

        FilamentDomainBoundary::class => [
            // Only behaviour the panel triggers: model reads are exempt under DA-22, never listed here.
            'grandfathered' => [],
        ],

        NoModelsInControllers::class => [
            'grandfathered' => [],
        ],

        RepositoryDelegationNaming::class => [
            'grandfathered' => [],
        ],

        CommentLength::class => [
            // Config files a package published: their comments are the package's (PHP-19).
            'published' => [
                'config/filament.php',
                'config/permission.php',
                'config/sanctum.php',
            ],
            'grandfathered' => [],
        ],

        DomainFolderStructure::class => [
            // Domain-specific folders excused from the README/Docs documentation requirement (DA-06).
            'grandfathered' => [],

            // Domains excused from carrying Docs/ (DA-18). Every domain carries it; keep this empty.
            'documentation_exempt' => [],
        ],
    ],

    // The merge gate: floors that only ratchet upward, raised whenever the codebase scores well above
    // them. Dependency advisories are `composer audit`'s job, so Insights' own security check is off.
    'requirements' => [
        'min-quality' => 68,
        'min-complexity' => 89,
        'min-architecture' => 88,
        'min-style' => 81,
        'disable-security-check' => true,
    ],

    'threads' => null,

    'timeout' => 60,
];

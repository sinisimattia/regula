<?php

declare(strict_types=1);

namespace Insights\Console;

use FilesystemIterator;
use Illuminate\Console\Command;
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
use NunoMaduro\PhpInsights\Domain\Collector;
use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use NunoMaduro\PhpInsights\Domain\Insights\Insight;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The hard gate: fails on any single divergence from `docs/standards/` that a machine can detect.
 *
 * PHP Insights gates the aggregate score, which cannot fail a build over one new violation. This
 * runs the same insight classes and exits non-zero if any of them reports anything at all. It is
 * dev tooling, registered only where dev dependencies are installed.
 */
final class CanonCheckCommand extends Command
{
    protected $signature = 'canon:check
        {--root= : Check another tree instead of this repository, such as a fixture proving a check}
        {--only=* : Run only these insights, by short class name (repeatable or comma-separated)}';

    protected $description = 'Fail on any divergence from docs/standards/ that a machine can detect';

    /**
     * @var list<class-string<Insight&HasDetails>>
     */
    private const INSIGHTS = [
        ServiceNaming::class,
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
    ];

    public function handle(): int
    {
        $insights = $this->selectedInsights();

        if ($insights === null) {
            return self::FAILURE;
        }

        $root = rtrim((string) ($this->option('root') ?: base_path()), '/');
        chdir($root);

        /** @var array<class-string, array<string, mixed>> $perInsightConfig */
        $perInsightConfig = config('insights.config', []);
        $collector = $this->collectorFor($root);
        $divergences = 0;

        foreach ($insights as $class) {
            $insight = new $class($collector, $perInsightConfig[$class] ?? []);
            $details = $insight->getDetails();

            if ($details === []) {
                $this->line('  <fg=green;options=bold>PASS</>  ' . $insight->getTitle());

                continue;
            }

            $divergences += count($details);
            $this->line('  <fg=red;options=bold>FAIL</>  ' . $insight->getTitle());

            foreach ($details as $detail) {
                $where = $detail->hasFile() ? str_replace($root . '/', '', $detail->getFile()) : '';
                $this->line('          ' . ($where !== '' ? $where . ' — ' : '') . $detail->getMessage());
            }

            $this->newLine();
        }

        if ($divergences > 0) {
            $this->components->error("{$divergences} divergence(s) from docs/standards/.");
            $this->line('Fix them, or — if the fix would spill outside the files this change touches (GIT-06) —');
            $this->line('raise a Technical Task and add a grandfathered entry in config/insights.php saying so.');

            return self::FAILURE;
        }

        $this->components->info('No divergences.');

        return self::SUCCESS;
    }

    /**
     * The insights `--only` names, all of them when it names none, or null after reporting an unknown name.
     *
     * @return list<class-string<Insight&HasDetails>>|null
     */
    private function selectedInsights(): ?array
    {
        /** @var list<string> $option */
        $option = $this->option('only');
        $requested = array_values(array_filter(array_map('trim', explode(',', implode(',', $option)))));

        if ($requested === []) {
            return self::INSIGHTS;
        }

        $byShortName = [];

        foreach (self::INSIGHTS as $class) {
            $byShortName[substr((string) strrchr('\\' . $class, '\\'), 1)] = $class;
        }

        $unknown = array_diff($requested, array_keys($byShortName));

        if ($unknown !== []) {
            $this->components->error('Unknown insight(s): ' . implode(', ', $unknown) . '.');
            $this->line('Valid names: ' . implode(', ', array_keys($byShortName)));

            return null;
        }

        return array_values(array_map(fn (string $name): string => $byShortName[$name], array_unique($requested)));
    }

    private function collectorFor(string $root): Collector
    {
        $collector = new Collector(['app'], $root);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getPathname(), '.php')) {
                $collector->addFile($file->getPathname());
            }
        }

        return $collector;
    }
}

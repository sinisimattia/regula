<?php

declare(strict_types=1);

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Database\PostgresRelationshipJoiner;
use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Every pgsql connection gets the grammar that compiles `like` as
         * `ilike`, so `like` matches case-insensitively everywhere. Registered
         * here rather than in boot() because the resolver has to be in place
         * before the first connection is made.
         */
        Connection::resolverFor(
            'pgsql',
            fn ($pdo, $database, $prefix, $config): PostgresConnection => new PostgresConnection(
                pdo: $pdo,
                database: $database,
                tablePrefix: $prefix,
                config: $config,
            ),
        );

        /*
         * Filament asks the container for the joiner every time it builds the
         * option list of a many-to-many relationship, so the PostgreSQL-safe
         * one takes its place here.
         *
         * @see PostgresRelationshipJoiner for why the override exists.
         */
        $this->app->bind(RelationshipJoiner::class, PostgresRelationshipJoiner::class);
    }
}

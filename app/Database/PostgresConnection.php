<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use Illuminate\Database\Query\Grammars\Grammar;

/**
 * A PostgreSQL connection that compiles `like` case-insensitively.
 *
 * Registered for the `pgsql` driver in {@see \App\Providers\AppServiceProvider}.
 *
 * @see PostgresGrammar for why the override exists.
 */
class PostgresConnection extends BasePostgresConnection
{
    protected function getDefaultQueryGrammar(): Grammar
    {
        // The grammar receives its connection through the constructor.
        return new PostgresGrammar($this);
    }
}

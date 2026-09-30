<?php

declare(strict_types=1);

namespace App\Database;

use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Throwable;

/**
 * Lets Filament's `select distinct "<table>".*` option queries for many-to-many relationships run on
 * PostgreSQL, by expanding `*` and casting the `json` and `xml` columns, which have no equality
 * operator, to `text`. Bound over Filament's own joiner in {@see \App\Providers\AppServiceProvider}.
 */
class PostgresRelationshipJoiner extends RelationshipJoiner
{
    /**
     * Columns per table, read from the catalog once per process.
     *
     * @var array<string, array<int, string|Expression>>
     */
    private static array $columns = [];

    /**
     * Kept to the types that cannot be compared: casting any other column could break the
     * `DISTINCT ... ORDER BY` the option list relies on.
     */
    private const INCOMPARABLE_TYPES = ['json', 'xml'];

    /**
     * `pg_catalog` resolves the table through the connection's `search_path`.
     */
    private const COLUMNS_QUERY = <<<'SQL'
        select a.attname as column_name,
               t.typname as type_name
        from pg_catalog.pg_attribute a
        join pg_catalog.pg_type t on t.oid = a.atttypid
        where a.attrelid = ?::regclass
          and a.attnum > 0
          and not a.attisdropped
        order by a.attnum
        SQL;

    public function prepareQueryForNoConstraints(Relation $relationship): Builder
    {
        $query = parent::prepareQueryForNoConstraints($relationship);

        if (!$relationship instanceof BelongsToMany) {
            return $query;
        }

        $baseQuery = $query->getQuery();
        $table = $query->getModel()->getTable();

        $wildcardIndex = array_search("{$table}.*", $baseQuery->columns ?? [], strict: true);

        if ($wildcardIndex === false) {
            return $query;
        }

        $columns = $this->comparableColumns($query->getConnection(), $table);

        if ($columns === []) {
            return $query;
        }

        array_splice($baseQuery->columns, $wildcardIndex, 1, $columns);

        return $query;
    }

    /**
     * The table's columns, with the ones PostgreSQL cannot compare cast to text.
     *
     * Empty whenever the wildcard is better left alone: any other database
     * engine, or a table the catalog cannot describe.
     *
     * @return array<int, string|Expression>
     */
    private function comparableColumns(Connection $connection, string $table): array
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return [];
        }

        $qualifiedTable = $connection->getTablePrefix().$table;
        $cacheKey = $connection->getName().':'.$qualifiedTable;

        if (array_key_exists($cacheKey, self::$columns)) {
            return self::$columns[$cacheKey];
        }

        try {
            $rows = $connection->select(self::COLUMNS_QUERY, [$qualifiedTable]);
        } catch (Throwable) {
            // A table the catalog has nothing to say about is not this class's
            // problem to report: leave the wildcard in place and let the query
            // fail on its own terms.
            return self::$columns[$cacheKey] = [];
        }

        $grammar = $connection->getQueryGrammar();

        return self::$columns[$cacheKey] = array_map(
            fn (object $row): string|Expression => in_array($row->type_name, self::INCOMPARABLE_TYPES, strict: true)
                ? new Expression($grammar->wrap("{$table}.{$row->column_name}").'::text as '.$grammar->wrap($row->column_name))
                : "{$table}.{$row->column_name}",
            $rows,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;

/**
 * Compiles `like` and `not like` as PostgreSQL's case-insensitive `ilike` and `not ilike`, so every
 * call site, Filament's searchable columns included, matches regardless of case.
 */
class PostgresGrammar extends BasePostgresGrammar
{
    /**
     * @param  array<string, mixed>  $where
     */
    protected function whereBasic(Builder $query, $where): string
    {
        $where['operator'] = $this->caseInsensitiveOperator($where['operator']);

        return parent::whereBasic($query, $where);
    }

    private function caseInsensitiveOperator(string $operator): string
    {
        return match (strtolower(trim($operator))) {
            'like' => 'ilike',
            'not like' => 'not ilike',
            default => $operator,
        };
    }
}

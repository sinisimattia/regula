<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Database\QueryException;

class DatabaseErrorHelper
{
    /**
     * PostgreSQL's SQLSTATE for a unique constraint rejecting a row.
     */
    private const UNIQUE_VIOLATION_CODES = ['23505'];

    public static function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), self::UNIQUE_VIOLATION_CODES, true);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Database\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * The related side of a throwaway many-to-many. Its `json` column is what PostgreSQL
 * cannot compare under `select distinct`.
 *
 * @property int $id
 * @property string $code
 * @property array<string, mixed>|null $settings
 */
class JoinerChild extends Model
{
    public const TABLE = 'joiner_test_children';

    protected $table = self::TABLE;

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
    ];
}

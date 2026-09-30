<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Tests\TestCase;

/**
 * @mixin TestCase
 * @mixin InteractsWithDatabase
 */
trait HasAdvancedDatabaseAsserts
{
    /**
     * Assert that a given where condition exists in the database
     * only for the specified amount of times.
     */
    protected function assertDatabaseHasWithCount(
        Model|string $table,
        array $data,
        int $count,
        ?string $connection = null
    ): self {
        return $this
            ->assertDatabaseHas(
                table: $table,
                data: $data,
                connection: $connection,
            )
            ->assertDatabaseCount(
                table: $table,
                count: $count,
                connection: $connection,
            );
    }
}

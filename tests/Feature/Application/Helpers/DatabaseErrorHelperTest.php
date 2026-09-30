<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Helpers;

use App\Helpers\DatabaseErrorHelper;
use App\Models\User as UserModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Tests for DatabaseErrorHelper::isUniqueViolation():
 * - recognises_a_unique_constraint_violation
 * - does_not_mistake_a_not_null_violation_for_a_duplicate
 *
 * A failed statement aborts the test's transaction, so each test asserts on the
 * exception alone and runs no query after it.
 */
class DatabaseErrorHelperTest extends FeatureTestCase
{
    #[Test]
    public function recognises_a_unique_constraint_violation(): void
    {
        $existing = UserModel::factory()->create();

        $exception = $this->captureQueryException(fn () => UserModel::factory()->create(['email' => $existing->email]));

        $this->assertTrue(DatabaseErrorHelper::isUniqueViolation($exception));
    }

    #[Test]
    public function does_not_mistake_a_not_null_violation_for_a_duplicate(): void
    {
        $exception = $this->captureQueryException(
            fn () => DB::table('users')->insert(['email' => 'nobody@example.com', 'password' => 'x', 'preferred_language' => null]),
        );

        $this->assertFalse(DatabaseErrorHelper::isUniqueViolation($exception));
    }

    private function captureQueryException(callable $statement): QueryException
    {
        try {
            $statement();
        } catch (QueryException $exception) {
            return $exception;
        }

        $this->fail('Expected the statement to raise a QueryException.');
    }
}

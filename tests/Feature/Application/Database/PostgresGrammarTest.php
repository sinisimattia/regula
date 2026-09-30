<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Database;

use App\Database\PostgresConnection;
use App\Database\PostgresGrammar;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Tests for PostgresGrammar, through the pgsql connection that binds it:
 * - the_pgsql_connection_compiles_with_the_case_insensitive_grammar
 * - like_matches_regardless_of_case
 * - not_like_excludes_regardless_of_case
 * - other_operators_are_left_alone
 */
class PostgresGrammarTest extends FeatureTestCase
{
    #[Test]
    public function the_pgsql_connection_compiles_with_the_case_insensitive_grammar(): void
    {
        $connection = DB::connection('pgsql');

        $this->assertInstanceOf(PostgresConnection::class, $connection);
        $this->assertInstanceOf(PostgresGrammar::class, $connection->getQueryGrammar());
        $this->assertStringContainsString(
            '"email"::text ilike ?',
            UserModel::query()->where('email', 'like', '%jane%')->toSql(),
        );
    }

    #[Test]
    public function like_matches_regardless_of_case(): void
    {
        $janeModel = UserModel::factory()->create(['email' => 'Jane.Smith@Example.com']);
        UserModel::factory()->create(['email' => 'john@example.com']);

        $matchedIds = UserModel::query()->where('email', 'like', '%JANE.SMITH%')->pluck('id')->all();

        $this->assertSame([$janeModel->id], $matchedIds);
    }

    #[Test]
    public function not_like_excludes_regardless_of_case(): void
    {
        UserModel::factory()->create(['email' => 'Jane.Smith@Example.com']);
        $johnModel = UserModel::factory()->create(['email' => 'john@example.com']);

        $remainingIds = UserModel::query()->where('email', 'not like', '%jane.smith%')->pluck('id')->all();

        $this->assertSame([$johnModel->id], $remainingIds);
    }

    #[Test]
    public function other_operators_are_left_alone(): void
    {
        UserModel::factory()->create(['email' => 'Jane.Smith@Example.com']);

        $this->assertSame(0, UserModel::query()->where('email', '=', 'jane.smith@example.com')->count());
    }
}

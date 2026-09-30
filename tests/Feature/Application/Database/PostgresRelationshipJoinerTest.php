<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Database;

use App\Database\PostgresRelationshipJoiner;
use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Application\Database\Fixtures\JoinerChild;
use Tests\Feature\Application\Database\Fixtures\JoinerParent;
use Tests\FeatureTestCase;

/**
 * Tests for PostgresRelationshipJoiner::prepareQueryForNoConstraints():
 *
 *  - executes without throwing against a related table with a json column (the regression)
 *  - still de-duplicates a related record reached through two pivot rows
 *  - preserves the json attribute's value and key order
 *  - leaves comparable columns uncast, so ordering by one of them still works
 *  - the container resolves Filament's RelationshipJoiner to this class
 *
 * The tables are created inside the test's transaction, so they vanish with it.
 */
class PostgresRelationshipJoinerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(JoinerParent::TABLE, function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create(JoinerChild::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('code')->default('');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('joiner_test_child_parent', function (Blueprint $table) {
            $table->foreignId('parent_id')->constrained(JoinerParent::TABLE);
            $table->foreignId('child_id')->constrained(JoinerChild::TABLE);
        });
    }

    private function createParent(): JoinerParent
    {
        return JoinerParent::query()->create();
    }

    private function createChild(array $attributes = []): JoinerChild
    {
        return JoinerChild::query()->create($attributes);
    }

    /**
     * Mirrors how Filament itself builds the relationship for an option list
     * (see Filament\Tables\Actions\AttachAction): no pivot constraints, but the
     * join clause is still there.
     */
    private function unconstrainedChildrenRelationship(JoinerParent $parent): BelongsToMany
    {
        /** @var BelongsToMany $relationship */
        $relationship = Relation::noConstraints(fn () => $parent->children());

        return $relationship;
    }

    #[Test]
    public function prepareQueryForNoConstraints_executes_without_throwing_for_related_table_with_json_column(): void
    {
        $parent = $this->createParent();
        $child = $this->createChild(['settings' => ['some' => 'value']]);
        $parent->children()->attach($child);

        $relationship = $this->unconstrainedChildrenRelationship($parent);
        $joiner = new PostgresRelationshipJoiner();

        $results = $joiner->prepareQueryForNoConstraints($relationship)->get();

        $this->assertTrue($results->contains('id', $child->getKey()));
    }

    #[Test]
    public function prepareQueryForNoConstraints_deduplicates_related_record_attached_via_two_pivot_rows(): void
    {
        $parentA = $this->createParent();
        $parentB = $this->createParent();
        $child = $this->createChild(['settings' => ['shared' => true]]);

        $parentA->children()->attach($child);
        $parentB->children()->attach($child);

        $relationship = $this->unconstrainedChildrenRelationship($parentA);
        $joiner = new PostgresRelationshipJoiner();

        $results = $joiner->prepareQueryForNoConstraints($relationship)->get();

        $this->assertCount(1, $results->where('id', $child->getKey()));
    }

    #[Test]
    public function prepareQueryForNoConstraints_preserves_json_value_and_key_order(): void
    {
        $parent = $this->createParent();
        $settings = ['zebra' => 1, 'apple' => 2, 'mango' => 3];
        $child = $this->createChild(['settings' => $settings]);
        $parent->children()->attach($child);

        $relationship = $this->unconstrainedChildrenRelationship($parent);
        $joiner = new PostgresRelationshipJoiner();

        /** @var JoinerChild $result */
        $result = $joiner->prepareQueryForNoConstraints($relationship)
            ->get()
            ->firstWhere('id', $child->getKey());

        $this->assertSame($settings, $result->settings);
        $this->assertSame(array_keys($settings), array_keys($result->settings));
    }

    #[Test]
    public function prepareQueryForNoConstraints_leaves_comparable_columns_uncast_so_order_by_still_works(): void
    {
        $parent = $this->createParent();
        $childA = $this->createChild(['code' => 'zzz-child']);
        $childB = $this->createChild(['code' => 'aaa-child']);
        $childC = $this->createChild(['code' => 'mmm-child']);
        $parent->children()->attach([
            $childA->getKey(),
            $childB->getKey(),
            $childC->getKey(),
        ]);

        $relationship = $this->unconstrainedChildrenRelationship($parent);
        $relationship->orderBy(JoinerChild::TABLE . '.code');
        $joiner = new PostgresRelationshipJoiner();

        $codes = $joiner->prepareQueryForNoConstraints($relationship)->get()->pluck('code')->all();

        $this->assertSame(['aaa-child', 'mmm-child', 'zzz-child'], $codes);
    }

    #[Test]
    public function container_resolves_relationship_joiner_to_postgres_implementation(): void
    {
        $joiner = app(RelationshipJoiner::class);

        $this->assertInstanceOf(PostgresRelationshipJoiner::class, $joiner);
    }
}

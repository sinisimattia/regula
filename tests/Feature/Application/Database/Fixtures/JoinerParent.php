<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Database\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The owning side of a throwaway many-to-many, created by the test that uses it.
 */
class JoinerParent extends Model
{
    public const TABLE = 'joiner_test_parents';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return BelongsToMany<JoinerChild, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            related: JoinerChild::class,
            table: 'joiner_test_child_parent',
            foreignPivotKey: 'parent_id',
            relatedPivotKey: 'child_id',
        );
    }
}

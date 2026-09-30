<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Domains\Auth\Listeners;

use App\Domains\Auth\Events\UserDeleted;
use App\Domains\Auth\Listeners\DeleteUserAccountOnUserDeletedListener;
use App\Models\User as UserModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Tests for DeleteUserAccountOnUserDeletedListener:
 * - is_registered_for_user_deleted_and_queued
 * - handle_deletes_the_user_row
 */
class DeleteUserAccountOnUserDeletedListenerTest extends FeatureTestCase
{
    #[Test]
    public function is_registered_for_user_deleted_and_queued(): void
    {
        Event::fake();

        Event::assertListening(UserDeleted::class, DeleteUserAccountOnUserDeletedListener::class);
        $this->assertInstanceOf(ShouldQueue::class, app(DeleteUserAccountOnUserDeletedListener::class));
    }

    #[Test]
    public function handle_deletes_the_user_row(): void
    {
        $userModel = UserModel::factory()->create();

        app(DeleteUserAccountOnUserDeletedListener::class)->handle(new UserDeleted($userModel->toEntity()));

        $this->assertDatabaseMissing('users', ['id' => $userModel->id]);
    }
}

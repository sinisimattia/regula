<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Helpers\Traits\HasAdvancedDatabaseAsserts;
use Tests\Helpers\Traits\HasAdvancedMocks;

class TestCase extends BaseTestCase
{
    use HasAdvancedMocks;
    use HasAdvancedDatabaseAsserts;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.disks', [
            'test_disk' => [],
        ]);
        Config::set('filesystems.default', 'test_disk');
        Storage::fake('test_disk');

        $this->withoutMiddleware([
            ThrottleRequests::class,
        ]);

        // Any unfaked outbound request fails the test instead of reaching a real service.
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

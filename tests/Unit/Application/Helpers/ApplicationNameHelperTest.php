<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Helpers;

use App\Helpers\ApplicationNameHelper;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for ApplicationNameHelper::fromComposerManifest():
 * - reads_the_vendor_part_of_the_composer_name
 * - fails_loudly_on_a_malformed_manifest
 */
class ApplicationNameHelperTest extends UnitTestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = tempnam(sys_get_temp_dir(), 'manifest');
    }

    protected function tearDown(): void
    {
        @unlink($this->manifestPath);

        parent::tearDown();
    }

    #[Test]
    public function reads_the_vendor_part_of_the_composer_name(): void
    {
        file_put_contents($this->manifestPath, json_encode(['name' => 'northwind/backend']));

        $this->assertSame('northwind', ApplicationNameHelper::fromComposerManifest($this->manifestPath));
    }

    #[Test]
    public function fails_loudly_on_a_malformed_manifest(): void
    {
        file_put_contents($this->manifestPath, '{not json');

        $this->expectException(JsonException::class);

        ApplicationNameHelper::fromComposerManifest($this->manifestPath);
    }
}

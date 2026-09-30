<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for {@see \App\Console\Kernel}.
 *
 *  - commands_loads_the_closure_commands_in_routes_console
 *  - commands_registers_the_canon_check_where_dev_dependencies_are_installed
 */
class KernelTest extends UnitTestCase
{
    #[Test]
    public function commands_loads_the_closure_commands_in_routes_console(): void
    {
        $this->assertArrayHasKey('inspire', Artisan::all());
    }

    #[Test]
    public function commands_registers_the_canon_check_where_dev_dependencies_are_installed(): void
    {
        $this->assertArrayHasKey('canon:check', Artisan::all());
    }
}

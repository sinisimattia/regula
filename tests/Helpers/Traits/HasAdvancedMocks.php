<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Mockery\MockInterface;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait HasAdvancedMocks
{
    public function hardMock(string $className): MockInterface
    {
        return $this->mock("overload:$className");
    }
}

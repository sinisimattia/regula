<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions as DatabaseStrategy;

class FeatureTestCase extends TestCase
{
    use DatabaseStrategy;
}

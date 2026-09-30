<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Http\Controllers;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

class HealthControllerTest extends FeatureTestCase
{
    #[Test]
    public function health_answers_ok_without_authentication(): void
    {
        $this->getJson(route('health'))
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}

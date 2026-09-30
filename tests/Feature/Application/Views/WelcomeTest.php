<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Views;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Tests for the welcome page (resources/views/welcome.blade.php, served at `GET /`). The route is
 * a closure with no controller, so the test is named after the view it renders.
 *
 * - renders_with_the_configured_theme_variables
 */
class WelcomeTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('theme', $this->fakeTheme());
    }

    #[Test]
    public function renders_with_the_configured_theme_variables(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('--theme-primary: #123456;', false);
        $response->assertSee("--theme-font-sans: 'Fixture Sans', cursive;", false);
        $response->assertSee('<link href="https://fonts.example.test/css?family=Fixture" rel="stylesheet">', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeTheme(): array
    {
        return [
            'colors' => [
                'primary' => '#123456',
                'ink' => '#000011',
                'paper' => '#fffefd',
                'code' => '#222333',
                'code_background' => '#d0d0d0',
                'gray' => '#999999',
                'border' => '#aaaaaa',
                'surface' => '#bbbbbb',
                'danger' => '#ff0000',
                'warning' => '#ff9900',
                'success' => '#00ff00',
                'info' => '#0000ff',
            ],
            'fonts' => [
                'url' => 'https://fonts.example.test/css?family=Fixture',
                'sans' => ['family' => 'Fixture Sans', 'fallback' => 'cursive'],
                'mono' => ['family' => 'Fixture Mono', 'fallback' => 'fantasy'],
            ],
        ];
    }
}

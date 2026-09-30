<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Views\Errors;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Tests for the error page layout (resources/views/errors/_layout.blade.php, shared by every
 * errors/{status}.blade.php view). It has no backing class, so the test is named after it.
 *
 * - renders_the_404_page_with_the_theme_variables_and_the_warning_accent
 * - uses_the_danger_accent_for_every_other_status
 */
class ErrorLayoutTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('theme', $this->fakeTheme());
    }

    #[Test]
    public function renders_the_404_page_with_the_theme_variables_and_the_warning_accent(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound();
        $response->assertSee('--theme-primary: #123456;', false);
        $response->assertSee('color: var(--theme-warning);', false);
    }

    #[Test]
    public function uses_the_danger_accent_for_every_other_status(): void
    {
        $html = (string) $this->view('errors._layout', ['statusCode' => 500, 'title' => 'Server Error']);

        $this->assertStringContainsString('color: var(--theme-danger);', $html);
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

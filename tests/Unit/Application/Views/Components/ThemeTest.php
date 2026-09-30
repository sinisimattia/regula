<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Views\Components;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for the `<x-theme />` component (resources/views/components/theme.blade.php). It has no
 * backing class — the anonymous component is the whole subject — so the test is named after it.
 *
 * - renders_one_css_variable_per_configured_colour
 * - does_not_render_the_raw_underscored_colour_name
 * - renders_the_font_stack_variables
 * - renders_the_font_stylesheet_link
 */
class ThemeTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('theme', $this->fakeTheme());
    }

    #[Test]
    public function renders_one_css_variable_per_configured_colour(): void
    {
        $html = (string) $this->blade('<x-theme />');

        foreach ($this->fakeTheme()['colors'] as $name => $color) {
            $this->assertStringContainsString(
                sprintf('--theme-%s: %s;', str_replace('_', '-', $name), $color),
                $html,
            );
        }
    }

    #[Test]
    public function does_not_render_the_raw_underscored_colour_name(): void
    {
        $html = (string) $this->blade('<x-theme />');

        $this->assertStringNotContainsString('--theme-code_background', $html);
    }

    #[Test]
    public function renders_the_font_stack_variables(): void
    {
        $html = (string) $this->blade('<x-theme />');

        $this->assertStringContainsString("--theme-font-sans: 'Fixture Sans', cursive;", $html);
        $this->assertStringContainsString("--theme-font-mono: 'Fixture Mono', fantasy;", $html);
    }

    #[Test]
    public function renders_the_font_stylesheet_link(): void
    {
        $html = (string) $this->blade('<x-theme />');

        $this->assertStringContainsString(
            '<link href="https://fonts.example.test/css?family=Fixture" rel="stylesheet">',
            $html,
        );
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

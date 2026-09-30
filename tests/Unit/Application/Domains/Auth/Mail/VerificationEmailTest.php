<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Domains\Auth\Mail;

use App\Domains\Auth\Mail\VerificationEmail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for VerificationEmail:
 * - envelope_carries_the_subject_and_from_address
 * - content_renders_the_verification_view_with_the_url
 * - render_includes_the_verification_link
 * - render_uses_the_configured_cta_button_colours
 * - render_uses_the_configured_font_stack
 */
class VerificationEmailTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('theme', $this->fakeTheme());
    }

    #[Test]
    public function envelope_carries_the_subject_and_from_address(): void
    {
        $mailable = new VerificationEmail(verificationUrl: 'https://example.com/verify/123/abc');

        $envelope = $mailable->envelope();

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertSame(__('emails.verification.subject'), $envelope->subject);
        $this->assertInstanceOf(Address::class, $envelope->from);
        $this->assertSame(config('mail.from.address'), $envelope->from->address);
        $this->assertSame(config('mail.from.name'), $envelope->from->name);
    }

    #[Test]
    public function content_renders_the_verification_view_with_the_url(): void
    {
        $mailable = new VerificationEmail(verificationUrl: 'https://example.com/verify/123/abc');

        $content = $mailable->content();

        $this->assertInstanceOf(Content::class, $content);
        $this->assertSame('emails.verification', $content->view);
        $this->assertSame(['verificationUrl' => 'https://example.com/verify/123/abc'], $content->with);
    }

    #[Test]
    public function render_includes_the_verification_link(): void
    {
        $mailable = new VerificationEmail(verificationUrl: 'https://example.com/verify/123/abc');

        $html = $mailable->render();

        $this->assertStringContainsString('https://example.com/verify/123/abc', $html);
        $this->assertStringContainsString(__('emails.verification.title'), $html);
        $this->assertStringContainsString(__('emails.verification.cta'), $html);
    }

    #[Test]
    public function render_uses_the_configured_cta_button_colours(): void
    {
        $mailable = new VerificationEmail(verificationUrl: 'https://example.com/verify/123/abc');

        $html = $mailable->render();

        $this->assertStringContainsString('bgcolor="#123456"', $html);
        $this->assertStringContainsString('background-color: #123456;', $html);
        $this->assertStringContainsString('color: #fffefd; text-decoration: none;', $html);
    }

    #[Test]
    public function render_uses_the_configured_font_stack(): void
    {
        $mailable = new VerificationEmail(verificationUrl: 'https://example.com/verify/123/abc');

        $html = $mailable->render();

        $this->assertStringContainsString("'Fixture Sans', cursive", $html);
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

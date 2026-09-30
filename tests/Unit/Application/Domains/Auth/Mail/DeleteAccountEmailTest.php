<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Domains\Auth\Mail;

use App\Domains\Auth\Mail\DeleteAccountEmail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for DeleteAccountEmail:
 * - envelope_carries_the_subject_and_from_address
 * - content_renders_the_delete_account_view_with_the_display_name
 * - render_includes_the_display_name
 * - render_uses_the_configured_font_stack
 */
class DeleteAccountEmailTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('theme', $this->fakeTheme());
    }

    #[Test]
    public function envelope_carries_the_subject_and_from_address(): void
    {
        $mailable = new DeleteAccountEmail(displayName: 'Jane Doe');

        $envelope = $mailable->envelope();

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertSame(__('emails.delete_account.subject'), $envelope->subject);
        $this->assertInstanceOf(Address::class, $envelope->from);
        $this->assertSame(config('mail.from.address'), $envelope->from->address);
        $this->assertSame(config('mail.from.name'), $envelope->from->name);
    }

    #[Test]
    public function content_renders_the_delete_account_view_with_the_display_name(): void
    {
        $mailable = new DeleteAccountEmail(displayName: 'Jane Doe');

        $content = $mailable->content();

        $this->assertInstanceOf(Content::class, $content);
        $this->assertSame('emails.delete_account', $content->view);
        $this->assertSame(['displayName' => 'Jane Doe'], $content->with);
    }

    #[Test]
    public function render_includes_the_display_name(): void
    {
        $mailable = new DeleteAccountEmail(displayName: 'Jane Doe');

        $html = $mailable->render();

        $this->assertStringContainsString(
            __('emails.delete_account.greeting', ['name' => 'Jane Doe']),
            $html,
        );
        $this->assertStringContainsString(__('emails.delete_account.title'), $html);
        $this->assertStringContainsString(__('emails.delete_account.confirmation'), $html);
    }

    #[Test]
    public function render_uses_the_configured_font_stack(): void
    {
        $mailable = new DeleteAccountEmail(displayName: 'Jane Doe');

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

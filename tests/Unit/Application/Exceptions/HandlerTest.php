<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Exceptions;

use App\Exceptions\HttpApplicationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\UnitTestCase;
use RuntimeException;

class HandlerTest extends UnitTestCase
{
    #[Test]
    public function an_unexpected_throwable_is_written_to_the_log(): void
    {
        $logged = [];
        $context = [];
        Log::shouldReceive('error')
            ->andReturnUsing(function (string $message, array $messageContext = []) use (&$logged, &$context): void {
                $logged[] = $message;
                $context = $messageContext;
            });

        $exception = new RuntimeException('The database went away.');

        app(ExceptionHandler::class)->report($exception);

        $this->assertSame(['The database went away.'], $logged);
        $this->assertSame($exception, $context['exception']);
    }

    #[Test]
    public function a_server_error_is_written_to_the_log(): void
    {
        $logged = [];
        Log::shouldReceive('error')
            ->andReturnUsing(function (string $message) use (&$logged): void {
                $logged[] = $message;
            });

        app(ExceptionHandler::class)->report(new HttpApplicationException(
            code: Response::HTTP_INTERNAL_SERVER_ERROR,
            message: 'Could not perform permission check.',
        ));

        $this->assertSame(['Could not perform permission check.'], $logged);
    }
}

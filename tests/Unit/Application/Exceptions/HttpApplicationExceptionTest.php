<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Exceptions;

use App\Exceptions\HttpApplicationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\UnitTestCase;
use Exception;

class HttpApplicationExceptionTest extends UnitTestCase
{
    #[Test]
    public function render_renders_correctly(): void
    {
        $exception = new HttpApplicationException(
            code: 123,
            message: 'message',
            errorKey: 'error.key',
            additionalInfo: [
                'some_field' => 'some value',
            ],
            previous: new Exception('previous exception'),
        );

        $result = $exception->render(
            request: request()
        )->getContent();
        $expectedResult = json_encode([
            'code' => 123,
            'message' => 'message',
            'error_key' => 'error.key',
            'additional_info' => [
                'some_field' => 'some value',
            ],
        ]);

        $this->assertJsonStringEqualsJsonString($expectedResult, $result);
    }

    #[Test]
    public function a_client_error_is_not_written_to_the_log(): void
    {
        $logged = [];
        Log::shouldReceive('error')
            ->andReturnUsing(function (string $message) use (&$logged): void {
                $logged[] = $message;
            });

        app(ExceptionHandler::class)->report(new HttpApplicationException(
            code: Response::HTTP_FORBIDDEN,
            message: 'Permission "members_list" not found.',
        ));

        $this->assertSame([], $logged);
    }

    #[Test]
    public function the_boundary_between_reported_and_unreported_is_500(): void
    {
        $logged = [];
        Log::shouldReceive('error')
            ->andReturnUsing(function (string $message) use (&$logged): void {
                $logged[] = $message;
            });

        $handler = app(ExceptionHandler::class);

        foreach ([499, 500] as $code) {
            $handler->report(new HttpApplicationException(
                code: $code,
                message: (string) $code,
            ));
        }

        $this->assertSame(['500'], $logged);
    }
}

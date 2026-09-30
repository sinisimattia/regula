<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HttpApplicationException extends Exception
{
    /**
     * @param  array<string, mixed>  $additionalInfo
     */
    public function __construct(
        public $code,
        public $message,
        public ?string $errorKey = null,
        public array $additionalInfo = [],
        public ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $this->message,
            code: $this->code,
            previous: $this->previous
        );
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => $this->code,
            'error_key' => $this->errorKey,
            'message' => $this->message,
            'additional_info' => $this->additionalInfo,
        ], $this->code);
    }

    /**
     * Reads the opposite way round to how it looks: true means "reported, stop here", false
     * falls through to the logger — {@see \Illuminate\Foundation\Exceptions\Handler::reportThrowable()}.
     * A 4xx is a deliberate answer to the caller, so only a 5xx is worth a log entry.
     */
    public function report(): bool
    {
        return $this->code < Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}

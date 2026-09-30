<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use Exception;
use Throwable;

class PasswordResetFailedException extends Exception
{
    public function __construct(string $status, ?Throwable $previous = null)
    {
        parent::__construct(message: $status, previous: $previous);
    }
}

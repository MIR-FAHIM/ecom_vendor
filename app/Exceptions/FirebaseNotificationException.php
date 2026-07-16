<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class FirebaseNotificationException extends RuntimeException
{
    public function __construct(
        string $message,
        private mixed $errors = null,
        private int $statusCode = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errors(): mixed
    {
        return $this->errors;
    }

    public function statusCode(): int
    {
        return $this->statusCode >= 400 && $this->statusCode <= 599
            ? $this->statusCode
            : 400;
    }
}

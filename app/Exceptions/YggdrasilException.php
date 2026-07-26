<?php

namespace App\Exceptions;

use Exception;

class YggdrasilException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $error = 'ForbiddenOperationException',
        private readonly int $statusCode = 403,
    ) {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self('Invalid credentials. Invalid username or password.');
    }

    public static function invalidToken(): self
    {
        return new self('Invalid token.');
    }

    public static function illegalArgument(string $message): self
    {
        return new self($message, 'IllegalArgumentException', 400);
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

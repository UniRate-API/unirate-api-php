<?php

declare(strict_types=1);

namespace UniRate\Exception;

/**
 * Thrown for any HTTP error that doesn't map to a more specific exception
 * (e.g. 403 Pro-gated endpoint, 503 service unavailable, 5xx, etc.).
 */
class ApiException extends UniRateException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $body = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}

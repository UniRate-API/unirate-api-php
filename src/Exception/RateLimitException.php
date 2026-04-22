<?php

declare(strict_types=1);

namespace UniRate\Exception;

/**
 * Thrown when the API returns 429 — rate limit exceeded.
 */
class RateLimitException extends UniRateException
{
}

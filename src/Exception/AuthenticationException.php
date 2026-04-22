<?php

declare(strict_types=1);

namespace UniRate\Exception;

/**
 * Thrown when the API returns 401 — missing or invalid API key.
 */
class AuthenticationException extends UniRateException
{
}

<?php

declare(strict_types=1);

namespace UniRate\Exception;

/**
 * Thrown when the API returns 404 — unknown currency or no data available.
 */
class InvalidCurrencyException extends UniRateException
{
}

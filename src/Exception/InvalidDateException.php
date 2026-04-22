<?php

declare(strict_types=1);

namespace UniRate\Exception;

/**
 * Thrown when the API returns 400 — invalid request parameters,
 * typically a malformed date.
 */
class InvalidDateException extends UniRateException
{
}

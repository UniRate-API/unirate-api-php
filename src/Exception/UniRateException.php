<?php

declare(strict_types=1);

namespace UniRate\Exception;

/**
 * Base exception for all UniRate client errors.
 *
 * All typed exceptions thrown by the client extend this class so callers
 * can catch every UniRate-specific failure with a single catch block.
 */
class UniRateException extends \RuntimeException
{
}

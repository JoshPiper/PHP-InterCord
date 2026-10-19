<?php

declare(strict_types=1);

namespace Internet\InterCord\Exception;

use Throwable;

/**
 * Implemented by every exception InterCord throws, so callers can catch the library's failures in one place.
 */
interface InterCordException extends Throwable
{
}

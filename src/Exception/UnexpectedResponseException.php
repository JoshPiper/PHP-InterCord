<?php

declare(strict_types=1);

namespace Internet\InterCord\Exception;

use RuntimeException;

/**
 * Discord reported success, but the response wasn't the shape InterCord expects.
 */
final class UnexpectedResponseException extends RuntimeException implements InterCordException {}

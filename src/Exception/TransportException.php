<?php

declare(strict_types=1);

namespace Internet\InterCord\Exception;

use RuntimeException;

/**
 * The request never got a response: DNS, connection, TLS or timeout failure.
 */
final class TransportException extends RuntimeException implements InterCordException {}

<?php

declare(strict_types=1);

namespace Internet\InterCord\Exception;

use LogicException;

/**
 * A Message restored from serialised data no longer holds its webhook, so it can't edit or delete itself.
 */
final class DetachedMessageException extends LogicException implements InterCordException {}

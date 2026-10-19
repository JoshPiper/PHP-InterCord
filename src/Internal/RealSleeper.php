<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

use Internet\InterCord\Sleeper;

/**
 * @internal
 */
final class RealSleeper implements Sleeper
{
	public function sleep(float $seconds): void
	{
		if ($seconds > 0) {
			usleep((int) ceil($seconds * 1_000_000));
		}
	}
}

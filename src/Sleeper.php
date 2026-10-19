<?php

declare(strict_types=1);

namespace Internet\InterCord;

/**
 * Waits out rate limits. Swap it to integrate with an event loop, or to keep tests from actually sleeping.
 */
interface Sleeper
{
	public function sleep(float $seconds): void;
}

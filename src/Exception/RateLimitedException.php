<?php

declare(strict_types=1);

namespace Internet\InterCord\Exception;

/**
 * Discord kept rate limiting the request after every allowed retry.
 */
final class RateLimitedException extends DiscordApiException
{
	/**
	 * @param float $retryAfter Seconds Discord asked us to wait on the final attempt.
	 * @param bool $global Whether the limit applies to every request, not just this route.
	 */
	public function __construct(
		string $message,
		public readonly float $retryAfter,
		public readonly bool $global,
		?int $discordCode = null,
	) {
		parent::__construct($message, 429, $discordCode);
	}
}

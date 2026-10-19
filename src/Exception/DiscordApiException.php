<?php

declare(strict_types=1);

namespace Internet\InterCord\Exception;

use RuntimeException;

/**
 * Discord answered with an error status.
 */
class DiscordApiException extends RuntimeException implements InterCordException
{
	/**
	 * @param int $status HTTP status code.
	 * @param ?int $discordCode Discord's JSON error code, when the body had one.
	 * @param array<mixed> $errors Discord's per-field validation errors, when the body had them.
	 */
	public function __construct(
		string $message,
		public readonly int $status,
		public readonly ?int $discordCode = null,
		public readonly array $errors = [],
	) {
		parent::__construct($message, $status);
	}
}

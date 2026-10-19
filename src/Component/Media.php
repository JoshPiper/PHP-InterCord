<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Attachment;
use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * @internal
 */
final class Media
{
	public const MAX_DESCRIPTION = 1024;

	/**
	 * An unfurled media item: an http or https URL, or a file uploaded with the same message.
	 *
	 * @return array{url: string}
	 * @throws InvalidArgumentException
	 */
	public static function item(string|Attachment $media): array
	{
		$url = $media instanceof Attachment ? $media->url() : $media;
		if (preg_match('~^(https?|attachment)://~i', $url) !== 1) {
			throw new InvalidArgumentException('Media must be an http or https URL, or an Attachment uploaded with the message.');
		}

		return ['url' => $url];
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function description(?string $description): ?string
	{
		if ($description !== null && preg_match('/^.{1,' . self::MAX_DESCRIPTION . '}$/us', $description) !== 1) {
			throw new InvalidArgumentException('A media description must be valid UTF-8 of 1 to ' . self::MAX_DESCRIPTION . ' characters.');
		}

		return $description;
	}
}

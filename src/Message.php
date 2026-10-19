<?php

declare(strict_types=1);

namespace Internet\InterCord;

use DateTimeImmutable;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Exception\UnexpectedResponseException;

/**
 * A message as Discord returned it.
 *
 * $threadId is the thread the message lives in, when it was sent to or fetched from one; Webhook's message methods
 * use it automatically when given this Message.
 *
 * Embeds and attachments are left as Discord sent them, including the fields only Discord fills in, such as
 * proxy URLs and image dimensions. Anything not mapped to a property is in $raw.
 */
final class Message
{
	/**
	 * @param list<array<string, mixed>> $embeds
	 * @param list<array<string, mixed>> $attachments
	 * @param array<string, mixed> $raw
	 */
	private function __construct(
		public readonly Snowflake $id,
		public readonly Snowflake $channelId,
		public readonly ?Snowflake $webhookId,
		public readonly string $content,
		public readonly array $embeds,
		public readonly array $attachments,
		public readonly DateTimeImmutable $timestamp,
		public readonly ?DateTimeImmutable $editedTimestamp,
		public readonly array $raw,
		public readonly ?Snowflake $threadId,
	) {}

	/**
	 * @internal
	 * @param ?array<mixed> $data
	 * @param ?Snowflake $threadId The thread the message was sent to or fetched from, to carry into later calls.
	 * @throws UnexpectedResponseException
	 */
	public static function fromResponse(?array $data, ?Snowflake $threadId = null): self
	{
		if ($data === null) {
			throw new UnexpectedResponseException('Discord returned no message body.');
		}

		/** @var array<string, mixed> $data */
		return new self(
			self::snowflake($data, 'id'),
			self::snowflake($data, 'channel_id'),
			isset($data['webhook_id']) ? self::snowflake($data, 'webhook_id') : null,
			is_string($data['content'] ?? null) ? $data['content'] : '',
			self::objects($data, 'embeds'),
			self::objects($data, 'attachments'),
			self::timestamp($data, 'timestamp'),
			isset($data['edited_timestamp']) ? self::timestamp($data, 'edited_timestamp') : null,
			$data,
			$threadId,
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function snowflake(array $data, string $key): Snowflake
	{
		$value = $data[$key] ?? null;
		try {
			if (!is_string($value)) {
				throw new InvalidArgumentException('not a string');
			}
			return Snowflake::from($value);
		} catch (InvalidArgumentException) {
			throw new UnexpectedResponseException("Discord's message has no valid '{$key}'.");
		}
	}

	/**
	 * @param array<string, mixed> $data
	 * @return list<array<string, mixed>>
	 */
	private static function objects(array $data, string $key): array
	{
		$value = $data[$key] ?? [];
		if (!is_array($value) || !array_is_list($value)) {
			throw new UnexpectedResponseException("Discord's message has an invalid '{$key}' list.");
		}

		$objects = [];
		foreach ($value as $object) {
			if (!is_array($object)) {
				throw new UnexpectedResponseException("Discord's message has an invalid '{$key}' list.");
			}
			/** @var array<string, mixed> $object */
			$objects[] = $object;
		}

		return $objects;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function timestamp(array $data, string $key): DateTimeImmutable
	{
		$value = $data[$key] ?? null;
		if (is_string($value)) {
			// Discord sends ISO 8601 with microseconds, but has also been seen without the fraction.
			foreach (['Y-m-d\TH:i:s.uP', 'Y-m-d\TH:i:sP'] as $format) {
				$parsed = DateTimeImmutable::createFromFormat($format, $value);
				if ($parsed !== false && DateTimeImmutable::getLastErrors() === false) {
					return $parsed;
				}
			}
		}

		throw new UnexpectedResponseException("Discord's message has no valid '{$key}'.");
	}
}

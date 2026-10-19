<?php

declare(strict_types=1);

namespace Internet\InterCord;

use DateTimeImmutable;
use Internet\InterCord\Exception\DetachedMessageException;
use Internet\InterCord\Exception\InterCordException;
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
 *
 * @phpstan-type SerialisedMessage array{
 *     id: Snowflake,
 *     channelId: Snowflake,
 *     webhookId: ?Snowflake,
 *     content: string,
 *     embeds: list<array<string, mixed>>,
 *     attachments: list<array<string, mixed>>,
 *     timestamp: DateTimeImmutable,
 *     editedTimestamp: ?DateTimeImmutable,
 *     raw: array<string, mixed>,
 *     threadId: ?Snowflake,
 * }
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
		private readonly ?Webhook $webhook,
	) {}

	/**
	 * @internal
	 * @param ?array<mixed> $data
	 * @param ?Snowflake $threadId The thread the message was sent to or fetched from, to carry into later calls.
	 * @param ?Webhook $webhook The webhook that sent it, for edit() and delete().
	 * @throws UnexpectedResponseException
	 */
	public static function fromResponse(?array $data, ?Snowflake $threadId = null, ?Webhook $webhook = null): self
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
			$webhook,
		);
	}

	/**
	 * The IDs of this message's attachments, in order.
	 *
	 * @return list<Snowflake>
	 */
	public function attachmentIds(): array
	{
		$ids = [];
		foreach ($this->attachments as $attachment) {
			if (is_string($attachment['id'] ?? null)) {
				$ids[] = Snowflake::from($attachment['id']);
			}
		}

		return $ids;
	}

	/**
	 * A copy of this message that knows the thread it lives in.
	 *
	 * @internal
	 */
	public function inThread(Snowflake $threadId): self
	{
		return new self(
			$this->id,
			$this->channelId,
			$this->webhookId,
			$this->content,
			$this->embeds,
			$this->attachments,
			$this->timestamp,
			$this->editedTimestamp,
			$this->raw,
			$threadId,
			$this->webhook,
		);
	}

	/**
	 * Edit this message, in its thread if it has one. Returns the edited message; this one is left unchanged.
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 * @throws InterCordException
	 * @see Webhook::editMessage()
	 */
	public function edit(array|string|int|float|RichEmbed|Payload $content): self
	{
		return $this->webhook()->editMessage($this, $content);
	}

	/**
	 * Delete this message, in its thread if it has one.
	 *
	 * @throws InterCordException
	 * @see Webhook::deleteMessage()
	 */
	public function delete(): void
	{
		$this->webhook()->deleteMessage($this);
	}

	/**
	 * Leaves the webhook out, so the token never reaches debug output.
	 *
	 * @return SerialisedMessage
	 */
	public function __debugInfo(): array
	{
		return $this->__serialize();
	}

	/**
	 * Leaves the webhook out, so a message stored in a cache or job queue never carries the token with it.
	 *
	 * @return SerialisedMessage
	 */
	public function __serialize(): array
	{
		return [
			'id' => $this->id,
			'channelId' => $this->channelId,
			'webhookId' => $this->webhookId,
			'content' => $this->content,
			'embeds' => $this->embeds,
			'attachments' => $this->attachments,
			'timestamp' => $this->timestamp,
			'editedTimestamp' => $this->editedTimestamp,
			'raw' => $this->raw,
			'threadId' => $this->threadId,
		];
	}

	/**
	 * @param SerialisedMessage $data
	 */
	public function __unserialize(array $data): void
	{
		$this->id = $data['id'];
		$this->channelId = $data['channelId'];
		$this->webhookId = $data['webhookId'];
		$this->content = $data['content'];
		$this->embeds = $data['embeds'];
		$this->attachments = $data['attachments'];
		$this->timestamp = $data['timestamp'];
		$this->editedTimestamp = $data['editedTimestamp'];
		$this->raw = $data['raw'];
		$this->threadId = $data['threadId'];
		$this->webhook = null;
	}

	private function webhook(): Webhook
	{
		return $this->webhook ?? throw new DetachedMessageException(
			'This message was restored from serialised data and has no webhook; use Webhook::editMessage() or Webhook::deleteMessage() with its ID.',
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

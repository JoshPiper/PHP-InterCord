<?php

declare(strict_types=1);

namespace Internet\InterCord;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Exception\InvalidWebhookUrlException;
use Internet\InterCord\Internal\RealSleeper;
use Internet\InterCord\Internal\Transport;
use LogicException;
use Psr\Http\Client\ClientInterface;

/**
 * A single Discord webhook endpoint.
 */
final class Webhook
{
	private const URL_PATTERN = '~^https://(?:(?:canary|ptb)\.)?discord(?:app)?\.com/api(?:/v\d+)?/webhooks/(\d+)/([\w.-]+)/?$~';

	private function __construct(
		private readonly Transport $transport,
		public readonly Snowflake $id,
	) {}

	/**
	 * @param string $url The webhook URL from Discord's channel integration settings.
	 * @param ?ClientInterface $client Any PSR-18 client. Defaults to Guzzle.
	 * @param ?Sleeper $sleeper Waits out rate limits. Defaults to usleep().
	 * @param int $maxRetries How many times to retry a rate limited request before giving up.
	 * @throws InvalidWebhookUrlException
	 */
	public static function fromUrl(
		#[\SensitiveParameter]
		string $url,
		?ClientInterface $client = null,
		?Sleeper $sleeper = null,
		int $maxRetries = 3,
	): self {
		if (preg_match(self::URL_PATTERN, $url, $matches) !== 1) {
			// The URL holds the token, so it stays out of the message.
			throw new InvalidWebhookUrlException('Not a Discord webhook URL; expected https://discord.com/api/webhooks/{id}/{token}.');
		}

		return self::fromCredentials($matches[1], $matches[2], $client, $sleeper, $maxRetries);
	}

	/**
	 * @see fromUrl() for the optional parameters.
	 * @throws InvalidArgumentException
	 */
	public static function fromCredentials(
		Snowflake|string $id,
		#[\SensitiveParameter]
		string $token,
		?ClientInterface $client = null,
		?Sleeper $sleeper = null,
		int $maxRetries = 3,
	): self {
		$id = Snowflake::from($id);
		if (preg_match('/^[\w.-]+$/', $token) !== 1) {
			throw new InvalidArgumentException('Webhook token is empty or contains invalid characters.');
		}
		if ($maxRetries < 0) {
			throw new InvalidArgumentException('maxRetries cannot be negative.');
		}

		$factory = new HttpFactory();

		return new self(
			new Transport(
				$client ?? new Client(['connect_timeout' => 10, 'timeout' => 30]),
				$factory,
				$factory,
				$sleeper ?? new RealSleeper(),
				$id->value,
				$token,
				$maxRetries,
			),
			$id,
		);
	}

	/**
	 * Execute the webhook with a string, an embed, an array of strings and embeds, or a prepared payload.
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 * @param string $username Username override. Ignored when $content is a Payload.
	 * @param string $avatar Avatar URL override. Ignored when $content is a Payload.
	 * @param Snowflake|string|null $threadId Post into this thread of the webhook's channel instead of the channel.
	 * @throws Exception\InterCordException
	 */
	public function execute(
		array|string|int|float|RichEmbed|Payload $content,
		string $username = '',
		string $avatar = '',
		Snowflake|string|null $threadId = null,
	): void {
		$thread = self::thread($threadId);
		$payload = Payload::from($content, $username, $avatar);

		$this->transport->send(
			'POST',
			query: self::threadQuery($thread),
			body: $payload->toExecuteBody($thread !== null),
			files: $payload->files(),
		);
	}

	/**
	 * Execute the webhook and wait for Discord to confirm, returning the message it created.
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 * @throws Exception\InterCordException
	 */
	public function executeAndWait(
		array|string|int|float|RichEmbed|Payload $content,
		string $username = '',
		string $avatar = '',
		Snowflake|string|null $threadId = null,
	): Message {
		$thread = self::thread($threadId);
		$payload = Payload::from($content, $username, $avatar);

		$message = Message::fromResponse($this->transport->send(
			'POST',
			query: ['wait' => 'true'] + self::threadQuery($thread),
			body: $payload->toExecuteBody($thread !== null),
			files: $payload->files(),
		), $thread, $this);

		// A new forum or media post is a thread, and its first message's channel is that thread.
		return $payload->startsThread() ? $message->inThread($message->channelId) : $message;
	}

	/**
	 * Fetch a message this webhook sent.
	 *
	 * @param Snowflake|string|null $threadId The thread the message is in. Defaults to the thread of a given Message.
	 * @throws Exception\InterCordException
	 */
	public function getMessage(Snowflake|string|Message $message, Snowflake|string|null $threadId = null): Message
	{
		$thread = self::thread($threadId, $message);

		return Message::fromResponse(
			$this->transport->send('GET', self::messagePath($message), self::threadQuery($thread)),
			$thread,
			$this,
		);
	}

	/**
	 * Edit a message this webhook sent. Only what $content sets is changed; everything else is left as it is.
	 *
	 * Given a Message, new files are added alongside its existing attachments; see Payload::addAttachment().
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 * @param Snowflake|string|null $threadId The thread the message is in. Defaults to the thread of a given Message.
	 * @throws Exception\InterCordException
	 */
	public function editMessage(
		Snowflake|string|Message $message,
		array|string|int|float|RichEmbed|Payload $content,
		Snowflake|string|null $threadId = null,
	): Message {
		$thread = self::thread($threadId, $message);
		$payload = Payload::from($content);
		$existing = $message instanceof Message ? $message->attachmentIds() : null;

		return Message::fromResponse($this->transport->send(
			'PATCH',
			self::messagePath($message),
			self::threadQuery($thread),
			$payload->toEditBody($existing),
			$payload->files(),
		), $thread, $this);
	}

	/**
	 * Delete a message this webhook sent.
	 *
	 * @param Snowflake|string|null $threadId The thread the message is in. Defaults to the thread of a given Message.
	 * @throws Exception\InterCordException
	 */
	public function deleteMessage(Snowflake|string|Message $message, Snowflake|string|null $threadId = null): void
	{
		$this->transport->send('DELETE', self::messagePath($message), self::threadQuery(self::thread($threadId, $message)));
	}

	/**
	 * A new, empty queue that sends through this webhook. Each call gives a separate queue.
	 */
	public function queued(): QueuedWebhook
	{
		return new QueuedWebhook($this);
	}

	/**
	 * @return array<string, string>
	 */
	public function __debugInfo(): array
	{
		return ['id' => $this->id->value, 'token' => '[redacted]'];
	}

	/**
	 * A webhook is a credential. Store its URL somewhere secret and rebuild it with fromUrl() instead.
	 *
	 * @return never
	 */
	public function __serialize(): array
	{
		throw new LogicException('Webhooks hold their token and refuse to be serialised; store the URL securely and use Webhook::fromUrl().');
	}

	private static function messagePath(Snowflake|string|Message $message): string
	{
		return '/messages/' . ($message instanceof Message ? $message->id : Snowflake::from($message));
	}

	/**
	 * An explicit thread wins; otherwise a Message brings the thread it came from.
	 */
	private static function thread(Snowflake|string|null $threadId, Snowflake|string|Message|null $message = null): ?Snowflake
	{
		if ($threadId !== null) {
			return Snowflake::from($threadId);
		}

		return $message instanceof Message ? $message->threadId : null;
	}

	/**
	 * @return array<string, string>
	 */
	private static function threadQuery(?Snowflake $thread): array
	{
		return $thread === null ? [] : ['thread_id' => $thread->value];
	}
}

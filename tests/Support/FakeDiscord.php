<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests\Support;

use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Internet\InterCord\Webhook;
use LogicException;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * A Webhook wired to canned responses, recording every request it sends.
 */
final class FakeDiscord
{
	public const ID = '123456789012345678';
	public const TOKEN = 'tok3n-SECRET_value.42';
	public const URL = 'https://discord.com/api/webhooks/' . self::ID . '/' . self::TOKEN;
	public const ENDPOINT = 'https://discord.com/api/v10/webhooks/' . self::ID . '/' . self::TOKEN;

	public readonly RecordingSleeper $sleeper;

	/** @var ArrayObject<int, mixed> Filled by Guzzle's history middleware. */
	private ArrayObject $history;
	private readonly HandlerStack $stack;

	public function __construct(Response|Throwable ...$responses)
	{
		$this->sleeper = new RecordingSleeper();
		$this->stack = HandlerStack::create(new MockHandler(array_values($responses)));

		// Passed through a local: the middleware takes its container by reference and appends to the object.
		$history = $this->history = new ArrayObject();
		$this->stack->push(Middleware::history($history));
	}

	public function webhook(int $maxRetries = 3): Webhook
	{
		return Webhook::fromUrl(self::URL, $this->client(), $this->sleeper, $maxRetries);
	}

	public function client(): Client
	{
		return new Client(['handler' => $this->stack]);
	}

	public function requestCount(): int
	{
		return count($this->history);
	}

	public function request(int $index = 0): RequestInterface
	{
		$entry = $this->history[$index] ?? null;
		if (!is_array($entry) || !($entry['request'] ?? null) instanceof RequestInterface) {
			throw new LogicException("No request #{$index} was sent.");
		}

		return $entry['request'];
	}

	public function body(int $index = 0): mixed
	{
		return json_decode((string) $this->request($index)->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * @param array<mixed> $body
	 * @param array<string, string> $headers
	 */
	public static function json(int $status, array $body, array $headers = []): Response
	{
		return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, JSON_THROW_ON_ERROR));
	}

	public const MESSAGE_ID = '1300000000000000001';
	public const CHANNEL_ID = '1200000000000000002';

	/**
	 * A message object shaped like Discord's response to a webhook execute or edit.
	 *
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	public static function message(array $overrides = []): array
	{
		return $overrides + [
			'id' => self::MESSAGE_ID,
			'type' => 0,
			'content' => 'hello',
			'channel_id' => self::CHANNEL_ID,
			'author' => ['id' => self::ID, 'username' => 'Captain Hook', 'bot' => true],
			'attachments' => [],
			'embeds' => [],
			'mentions' => [],
			'mention_roles' => [],
			'pinned' => false,
			'mention_everyone' => false,
			'tts' => false,
			'timestamp' => '2026-10-19T12:00:00.123000+00:00',
			'edited_timestamp' => null,
			'flags' => 0,
			'components' => [],
			'webhook_id' => self::ID,
		];
	}

	/**
	 * @param array<string, string> $headers
	 */
	public static function noContent(array $headers = []): Response
	{
		return new Response(204, $headers);
	}
}

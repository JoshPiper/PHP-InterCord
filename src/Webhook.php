<?php

declare(strict_types=1);

namespace Internet\InterCord;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Exception\InvalidWebhookUrlException;
use Internet\InterCord\Internal\RealSleeper;
use Internet\InterCord\Internal\Transport;
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
	 * @throws Exception\InterCordException
	 */
	public function execute(array|string|int|float|RichEmbed|Payload $content, string $username = '', string $avatar = ''): void
	{
		$this->transport->send('POST', body: Payload::from($content, $username, $avatar)->jsonSerialize());
	}

	/**
	 * Execute the webhook and wait for Discord to confirm, returning the message it created.
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 * @throws Exception\InterCordException
	 */
	public function executeAndWait(array|string|int|float|RichEmbed|Payload $content, string $username = '', string $avatar = ''): Message
	{
		return Message::fromResponse($this->transport->send(
			'POST',
			query: ['wait' => 'true'],
			body: Payload::from($content, $username, $avatar)->jsonSerialize(),
		));
	}

	/**
	 * @return array<string, string>
	 */
	public function __debugInfo(): array
	{
		return ['id' => $this->id->value, 'token' => '[redacted]'];
	}
}

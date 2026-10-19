<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Exception\InvalidWebhookUrlException;
use Internet\InterCord\Tests\Support\FakeDiscord;
use Internet\InterCord\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookUrlTest extends TestCase
{
	/**
	 * @return iterable<string, array{string}>
	 */
	public static function acceptedUrls(): iterable
	{
		$path = FakeDiscord::ID . '/' . FakeDiscord::TOKEN;
		yield 'discord.com' => ["https://discord.com/api/webhooks/{$path}"];
		yield 'legacy discordapp.com' => ["https://discordapp.com/api/webhooks/{$path}"];
		yield 'canary' => ["https://canary.discord.com/api/webhooks/{$path}"];
		yield 'ptb' => ["https://ptb.discord.com/api/webhooks/{$path}"];
		yield 'versioned' => ["https://discord.com/api/v9/webhooks/{$path}"];
		yield 'trailing slash' => ["https://discord.com/api/v10/webhooks/{$path}/"];
	}

	#[DataProvider('acceptedUrls')]
	public function testAcceptedUrlsAlwaysTargetDiscordApiV10(string $url): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());
		$webhook = Webhook::fromUrl($url, $discord->client());

		$webhook->execute('hello');

		self::assertSame(FakeDiscord::ENDPOINT, (string) $discord->request()->getUri());
		self::assertSame(FakeDiscord::ID, $webhook->id);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function rejectedUrls(): iterable
	{
		$path = FakeDiscord::ID . '/' . FakeDiscord::TOKEN;
		yield 'empty' => [''];
		yield 'plain http' => ["http://discord.com/api/webhooks/{$path}"];
		yield 'other host' => ["https://example.com/api/webhooks/{$path}"];
		yield 'lookalike host' => ["https://discord.com.example.com/api/webhooks/{$path}"];
		yield 'non-numeric id' => ['https://discord.com/api/webhooks/abc/' . FakeDiscord::TOKEN];
		yield 'missing token' => ['https://discord.com/api/webhooks/' . FakeDiscord::ID];
		yield 'query string' => ["https://discord.com/api/webhooks/{$path}?thread_id=1"];
		yield 'extra path' => ["https://discord.com/api/webhooks/{$path}/messages/1"];
	}

	#[DataProvider('rejectedUrls')]
	public function testRejectedUrlsDoNotEchoTheToken(string $url): void
	{
		try {
			Webhook::fromUrl($url);
			self::fail('Expected InvalidWebhookUrlException.');
		} catch (InvalidWebhookUrlException $ex) {
			self::assertStringNotContainsString(FakeDiscord::TOKEN, $ex->getMessage());
		}
	}

	public function testCredentialsBuildTheSameEndpoint(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());
		$discord->webhook()->execute('hello');

		self::assertSame(FakeDiscord::ENDPOINT, (string) $discord->request()->getUri());
	}

	/**
	 * @return iterable<string, array{string, string, int}>
	 */
	public static function invalidCredentials(): iterable
	{
		yield 'non-numeric id' => ['abc', FakeDiscord::TOKEN, 3];
		yield 'empty token' => [FakeDiscord::ID, '', 3];
		yield 'token with a slash' => [FakeDiscord::ID, 'a/b', 3];
		yield 'negative retries' => [FakeDiscord::ID, FakeDiscord::TOKEN, -1];
	}

	#[DataProvider('invalidCredentials')]
	public function testInvalidCredentialsAreRejected(string $id, string $token, int $maxRetries): void
	{
		$this->expectException(InvalidArgumentException::class);
		Webhook::fromCredentials($id, $token, maxRetries: $maxRetries);
	}
}

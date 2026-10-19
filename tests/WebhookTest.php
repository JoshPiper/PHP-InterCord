<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Internet\InterCord\Exception\DiscordApiException;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Exception\TransportException;
use Internet\InterCord\Internal\Payload;
use Internet\InterCord\RichEmbed;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
	public function testExecutePostsJson(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute('hello', 'Bot', 'https://example.com/avatar.png');

		$request = $discord->request();
		self::assertSame('POST', $request->getMethod());
		self::assertSame(FakeDiscord::ENDPOINT, (string) $request->getUri());
		self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
		self::assertSame(
			['content' => 'hello', 'username' => 'Bot', 'avatar_url' => 'https://example.com/avatar.png'],
			$discord->body(),
		);
	}

	public function testExecuteSendsEmbedsAndContentTogether(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute(['hello', (new RichEmbed())->setTitle('first'), (new RichEmbed())->setTitle('second')]);

		self::assertSame(
			[
				'content' => 'hello',
				'embeds' => [
					['title' => 'first', 'type' => 'rich'],
					['title' => 'second', 'type' => 'rich'],
				],
			],
			$discord->body(),
		);
	}

	public function testExecuteSendsAPreparedPayloadAsIs(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute((new Payload())->setContent('hello')->setTTS(true), 'ignored');

		self::assertSame(['content' => 'hello', 'tts' => true], $discord->body());
	}

	public function testDiscordErrorsBecomeDiscordApiException(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(400, [
			'message' => 'Invalid Form Body',
			'code' => 50035,
			'errors' => ['content' => ['_errors' => [['code' => 'BASE_TYPE_MAX_LENGTH']]]],
		]));

		try {
			$discord->webhook()->execute('hello');
			self::fail('Expected DiscordApiException.');
		} catch (DiscordApiException $ex) {
			self::assertSame(400, $ex->status);
			self::assertSame(400, $ex->getCode());
			self::assertSame(50035, $ex->discordCode);
			self::assertSame(['content' => ['_errors' => [['code' => 'BASE_TYPE_MAX_LENGTH']]]], $ex->errors);
			self::assertSame(
				'Discord rejected POST /webhooks/' . FakeDiscord::ID . '/[redacted] with 400 (code 50035): Invalid Form Body',
				$ex->getMessage(),
			);
		}
	}

	public function testNonJsonErrorsFallBackToTheReasonPhrase(): void
	{
		$discord = new FakeDiscord(new Response(502, [], '<html>Bad Gateway</html>'));

		try {
			$discord->webhook()->execute('hello');
			self::fail('Expected DiscordApiException.');
		} catch (DiscordApiException $ex) {
			self::assertSame(502, $ex->status);
			self::assertNull($ex->discordCode);
			self::assertStringEndsWith('with 502: Bad Gateway', $ex->getMessage());
		}
	}

	public function testConnectionFailuresBecomeTransportExceptionWithoutTheToken(): void
	{
		$discord = new FakeDiscord(new ConnectException(
			'cURL error 6: Could not resolve host: discord.com for ' . FakeDiscord::ENDPOINT,
			new Request('POST', FakeDiscord::ENDPOINT),
		));

		try {
			$discord->webhook()->execute('hello');
			self::fail('Expected TransportException.');
		} catch (TransportException $ex) {
			self::assertStringNotContainsString(FakeDiscord::TOKEN, $ex->getMessage());
			self::assertStringContainsString('Could not resolve host', $ex->getMessage());
			// The original exception's message holds the token, so it must not be reachable through the chain.
			self::assertNull($ex->getPrevious());
		}
	}

	public function testErrorMessagesFromDiscordAreRedactedToo(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(401, ['message' => 'Invalid Webhook Token ' . FakeDiscord::TOKEN, 'code' => 50027]));

		try {
			$discord->webhook()->execute('hello');
			self::fail('Expected DiscordApiException.');
		} catch (DiscordApiException $ex) {
			self::assertStringNotContainsString(FakeDiscord::TOKEN, $ex->getMessage());
		}
	}

	public function testDebugOutputDoesNotContainTheToken(): void
	{
		$webhook = (new FakeDiscord())->webhook();

		self::assertStringNotContainsString(FakeDiscord::TOKEN, print_r($webhook, true));

		ob_start();
		var_dump($webhook);
		self::assertStringNotContainsString(FakeDiscord::TOKEN, (string) ob_get_clean());
	}

	public function testUnencodablePayloadsAreRejectedBeforeSending(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		try {
			$discord->webhook()->execute("invalid UTF-8: \xB1");
		} finally {
			self::assertSame(0, $discord->requestCount());
		}
	}
}

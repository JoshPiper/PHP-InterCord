<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\DiscordApiException;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Payload;
use Internet\InterCord\RichEmbed;
use Internet\InterCord\Snowflake;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageManagementTest extends TestCase
{
	private const MESSAGE_URI = FakeDiscord::ENDPOINT . '/messages/' . FakeDiscord::MESSAGE_ID;

	public function testGetMessage(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message(['content' => 'fetched'])));

		$message = $discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID);

		self::assertSame('GET', $discord->request()->getMethod());
		self::assertSame(self::MESSAGE_URI, (string) $discord->request()->getUri());
		self::assertSame('', (string) $discord->request()->getBody());
		self::assertSame('fetched', $message->content);
	}

	public function testEditMessageSendsOnlyWhatWasSet(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message(['content' => 'edited'])));

		$message = $discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, 'edited');

		self::assertSame('PATCH', $discord->request()->getMethod());
		self::assertSame(self::MESSAGE_URI, (string) $discord->request()->getUri());
		self::assertSame(['content' => 'edited'], $discord->body());
		self::assertSame('edited', $message->content);
	}

	public function testEditMessageWithEmbedsOnlyLeavesContentAlone(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new RichEmbed())->setTitle('new'));

		self::assertSame(['embeds' => [['title' => 'new', 'type' => 'rich']]], $discord->body());
	}

	public function testDeleteMessage(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->deleteMessage(FakeDiscord::MESSAGE_ID);

		self::assertSame('DELETE', $discord->request()->getMethod());
		self::assertSame(self::MESSAGE_URI, (string) $discord->request()->getUri());
	}

	public function testMessagesCanBeReferencedByMessageOrSnowflake(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message()),
			FakeDiscord::json(200, FakeDiscord::message()),
			FakeDiscord::noContent(),
		);
		$webhook = $discord->webhook();

		$message = $webhook->getMessage(Snowflake::from(FakeDiscord::MESSAGE_ID));
		$webhook->editMessage($message, 'edited');
		$webhook->deleteMessage($message);

		self::assertSame(self::MESSAGE_URI, (string) $discord->request(1)->getUri());
		self::assertSame(self::MESSAGE_URI, (string) $discord->request(2)->getUri());
	}

	/**
	 * @return iterable<string, array{Payload}>
	 */
	public static function notEditable(): iterable
	{
		yield 'username' => [(new Payload())->setContent('x')->setUsername('Bot')];
		yield 'avatar' => [(new Payload())->setContent('x')->setAvatar('https://example.com/a.png')];
		yield 'tts' => [(new Payload())->setContent('x')->setTTS(true)];
	}

	#[DataProvider('notEditable')]
	public function testFieldsDiscordWontEditAreRejectedBeforeSending(Payload $payload): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		try {
			$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, $payload);
		} finally {
			self::assertSame(0, $discord->requestCount());
		}
	}

	public function testAnInvalidMessageIdIsRejectedBeforeSending(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		$discord->webhook()->deleteMessage('../../');
	}

	public function testAnUnknownMessageIsADiscordApiException(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(404, ['message' => 'Unknown Message', 'code' => 10008]));

		try {
			$discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID);
			self::fail('Expected DiscordApiException.');
		} catch (DiscordApiException $ex) {
			self::assertSame(10008, $ex->discordCode);
			self::assertStringContainsString('/messages/' . FakeDiscord::MESSAGE_ID, $ex->getMessage());
		}
	}
}

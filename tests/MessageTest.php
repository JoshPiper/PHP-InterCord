<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\UnexpectedResponseException;
use Internet\InterCord\RichEmbed;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
	public function testExecuteAndWaitAsksDiscordToWait(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->executeAndWait('hello');

		self::assertSame('POST', $discord->request()->getMethod());
		self::assertSame(FakeDiscord::ENDPOINT . '?wait=true', (string) $discord->request()->getUri());
		self::assertSame(['content' => 'hello'], $discord->body());
	}

	public function testExecuteAndWaitReturnsTheMessage(): void
	{
		$embed = ['type' => 'rich', 'title' => 't', 'image' => ['url' => 'https://example.com/a.png', 'proxy_url' => 'https://media.discordapp.net/a.png', 'width' => 10, 'height' => 20]];
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message(['embeds' => [$embed], 'edited_timestamp' => '2026-10-19T12:05:00+00:00'])));

		$message = $discord->webhook()->executeAndWait(['hello', (new RichEmbed())->setTitle('t')]);

		self::assertTrue($message->id->equals(FakeDiscord::MESSAGE_ID));
		self::assertTrue($message->channelId->equals(FakeDiscord::CHANNEL_ID));
		self::assertTrue($message->webhookId?->equals(FakeDiscord::ID));
		self::assertSame('hello', $message->content);
		self::assertSame([$embed], $message->embeds);
		self::assertSame([], $message->attachments);
		self::assertSame('2026-10-19T12:00:00.123+00:00', $message->timestamp->format('Y-m-d\TH:i:s.vP'));
		self::assertSame('2026-10-19T12:05:00+00:00', $message->editedTimestamp?->format(DATE_ATOM));
		self::assertSame(FakeDiscord::message(['embeds' => [$embed], 'edited_timestamp' => '2026-10-19T12:05:00+00:00']), $message->raw);
	}

	public function testANeverEditedMessageHasNoEditedTimestamp(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		self::assertNull($discord->webhook()->executeAndWait('hello')->editedTimestamp);
	}

	/**
	 * @return iterable<string, array{array<string, mixed>}>
	 */
	public static function malformed(): iterable
	{
		yield 'missing id' => [array_diff_key(FakeDiscord::message(), ['id' => true])];
		yield 'non-string id' => [FakeDiscord::message(['id' => 12])];
		yield 'missing channel' => [array_diff_key(FakeDiscord::message(), ['channel_id' => true])];
		yield 'bad timestamp' => [FakeDiscord::message(['timestamp' => 'yesterday'])];
	}

	/**
	 * @param array<string, mixed> $body
	 */
	#[DataProvider('malformed')]
	public function testAMalformedResponseIsReported(array $body): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, $body));

		$this->expectException(UnexpectedResponseException::class);
		$discord->webhook()->executeAndWait('hello');
	}

	public function testAnEmptyResponseIsReported(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$this->expectException(UnexpectedResponseException::class);
		$discord->webhook()->executeAndWait('hello');
	}
}

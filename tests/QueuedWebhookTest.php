<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Internet\InterCord\Exception\DiscordApiException;
use Internet\InterCord\Exception\RateLimitedException;
use Internet\InterCord\Exception\TransportException;
use Internet\InterCord\QueuedWebhook;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\TestCase;

final class QueuedWebhookTest extends TestCase
{
	public function testQueueOrderAndCount(): void
	{
		$queue = new QueuedWebhook((new FakeDiscord())->webhook());

		$queue->append('second');
		$queue->append('third');
		$queue->prepend('first');

		self::assertCount(3, $queue);

		$taken = [];
		while (($payload = $queue->next()) !== null) {
			$taken[] = $payload->jsonSerialize();
		}

		self::assertSame([['content' => 'first'], ['content' => 'second'], ['content' => 'third']], $taken);
		self::assertCount(0, $queue);
	}

	public function testRunSendsEverythingInOrder(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent(), FakeDiscord::noContent());
		$queue = new QueuedWebhook($discord->webhook());
		$queue->append('one');
		$queue->append('two');

		$queue->run();

		self::assertCount(0, $queue);
		self::assertSame(['content' => 'one'], $discord->body(0));
		self::assertSame(['content' => 'two'], $discord->body(1));
	}

	public function testRunStopsAtMax(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());
		$queue = new QueuedWebhook($discord->webhook());
		$queue->append('one');
		$queue->append('two');

		$queue->run(1);

		self::assertCount(1, $queue);
		self::assertSame(1, $discord->requestCount());
	}

	public function testDeliverSendsAheadOfTheQueue(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());
		$queue = new QueuedWebhook($discord->webhook());
		$queue->append('queued');

		$queue->deliver('urgent');

		self::assertSame(['content' => 'urgent'], $discord->body());
		self::assertCount(1, $queue);
	}

	public function testARateLimitedPayloadGoesBackOnTheFront(): void
	{
		$limited = FakeDiscord::json(429, ['retry_after' => 0]);
		$discord = new FakeDiscord($limited, $limited, FakeDiscord::noContent(), FakeDiscord::noContent());
		$queue = new QueuedWebhook($discord->webhook(maxRetries: 1));
		$queue->append('one');
		$queue->append('two');

		try {
			$queue->run();
			self::fail('Expected RateLimitedException.');
		} catch (RateLimitedException) {
			self::assertCount(2, $queue);
		}

		$queue->run();

		self::assertCount(0, $queue);
		self::assertSame(['content' => 'one'], $discord->body(2));
		self::assertSame(['content' => 'two'], $discord->body(3));
	}

	public function testAPayloadWithNoResponseGoesBackOnTheFront(): void
	{
		$discord = new FakeDiscord(new ConnectException('timed out', new Request('POST', FakeDiscord::ENDPOINT)));
		$queue = new QueuedWebhook($discord->webhook());
		$queue->append('one');

		$this->expectException(TransportException::class);
		try {
			$queue->run();
		} finally {
			self::assertCount(1, $queue);
		}
	}

	public function testARejectedPayloadIsDropped(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(400, ['message' => 'Cannot send an empty message', 'code' => 50006]), FakeDiscord::noContent());
		$queue = new QueuedWebhook($discord->webhook());
		$queue->append('bad');
		$queue->append('good');

		try {
			$queue->run();
			self::fail('Expected DiscordApiException.');
		} catch (DiscordApiException) {
			self::assertCount(1, $queue);
		}

		$queue->run();
		self::assertSame(['content' => 'good'], $discord->body(1));
	}
}

<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\TestCase;

final class QueuedTest extends TestCase
{
	public function testQueuedWrapsTheWebhook(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());
		$queue = $discord->webhook()->queued();

		$queue->append('hello');
		$queue->run();

		self::assertSame(['content' => 'hello'], $discord->body());
	}

	public function testEachCallGivesASeparateQueue(): void
	{
		$webhook = (new FakeDiscord())->webhook();
		$first = $webhook->queued();
		$first->append('hello');

		self::assertCount(0, $webhook->queued());
		self::assertCount(1, $first);
	}
}

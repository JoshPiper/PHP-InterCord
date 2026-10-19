<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\DetachedMessageException;
use Internet\InterCord\Message;
use Internet\InterCord\Tests\Support\FakeDiscord;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MessageMethodsTest extends TestCase
{
	private const THREAD_ID = '1400000000000000003';
	private const MESSAGE_URI = FakeDiscord::ENDPOINT . '/messages/' . FakeDiscord::MESSAGE_ID;

	public function testEditReturnsANewMessageAndLeavesTheOriginal(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message(['content' => 'first'])),
			FakeDiscord::json(200, FakeDiscord::message(['content' => 'second'])),
		);
		$message = $discord->webhook()->executeAndWait('first');

		$edited = $message->edit('second');

		self::assertSame('PATCH', $discord->request(1)->getMethod());
		self::assertSame(self::MESSAGE_URI, (string) $discord->request(1)->getUri());
		self::assertSame(['content' => 'second'], $discord->body(1));
		self::assertSame('second', $edited->content);
		self::assertSame('first', $message->content);
	}

	public function testDelete(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()), FakeDiscord::noContent());

		$discord->webhook()->executeAndWait('hello')->delete();

		self::assertSame('DELETE', $discord->request(1)->getMethod());
		self::assertSame(self::MESSAGE_URI, (string) $discord->request(1)->getUri());
	}

	public function testEditAndDeleteStayInTheMessagesThread(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message()),
			FakeDiscord::json(200, FakeDiscord::message()),
			FakeDiscord::noContent(),
		);

		$message = $discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID, self::THREAD_ID);
		$message->edit('edited')->delete();

		self::assertSame(self::MESSAGE_URI . '?thread_id=' . self::THREAD_ID, (string) $discord->request(1)->getUri());
		self::assertSame(self::MESSAGE_URI . '?thread_id=' . self::THREAD_ID, (string) $discord->request(2)->getUri());
	}

	public function testDebugOutputLeavesOutTheWebhook(): void
	{
		$message = (new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message())))->webhook()->executeAndWait('hello');

		ob_start();
		var_dump($message);
		$dump = (string) ob_get_clean();

		self::assertStringNotContainsString(FakeDiscord::TOKEN, $dump);
		self::assertStringNotContainsString(FakeDiscord::TOKEN, print_r($message, true));
		self::assertStringContainsString(FakeDiscord::MESSAGE_ID, $dump);
	}

	public function testASerialisedMessageKeepsItsDataButNotTheWebhook(): void
	{
		$message = (new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message())))->webhook()->executeAndWait('hello', threadId: self::THREAD_ID);

		$serialised = serialize($message);
		self::assertStringNotContainsString(FakeDiscord::TOKEN, $serialised);

		$restored = unserialize($serialised);
		self::assertInstanceOf(Message::class, $restored);
		self::assertTrue($restored->id->equals(FakeDiscord::MESSAGE_ID));
		self::assertTrue($restored->threadId?->equals(self::THREAD_ID));
		self::assertSame('hello', $restored->content);
		self::assertEquals($message->timestamp, $restored->timestamp);

		$this->expectException(DetachedMessageException::class);
		$restored->edit('edited');
	}

	public function testADetachedMessageCannotBeDeletedEither(): void
	{
		$message = (new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message())))->webhook()->executeAndWait('hello');
		$restored = unserialize(serialize($message));
		self::assertInstanceOf(Message::class, $restored);

		$this->expectException(DetachedMessageException::class);
		$restored->delete();
	}

	public function testAWebhookRefusesToBeSerialised(): void
	{
		$webhook = (new FakeDiscord())->webhook();

		$this->expectException(LogicException::class);
		serialize($webhook);
	}
}

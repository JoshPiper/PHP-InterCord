<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\TestCase;

final class ThreadTest extends TestCase
{
	private const THREAD_ID = '1400000000000000003';
	private const MESSAGE_URI = FakeDiscord::ENDPOINT . '/messages/' . FakeDiscord::MESSAGE_ID;

	public function testExecuteIntoAThread(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute('hello', threadId: self::THREAD_ID);

		self::assertSame(FakeDiscord::ENDPOINT . '?thread_id=' . self::THREAD_ID, (string) $discord->request()->getUri());
	}

	public function testExecuteAndWaitIntoAThreadRemembersIt(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message(['channel_id' => self::THREAD_ID])));

		$message = $discord->webhook()->executeAndWait('hello', threadId: self::THREAD_ID);

		self::assertSame(FakeDiscord::ENDPOINT . '?wait=true&thread_id=' . self::THREAD_ID, (string) $discord->request()->getUri());
		self::assertTrue($message->threadId?->equals(self::THREAD_ID));
	}

	public function testAMessageOutsideAThreadHasNoThreadId(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		self::assertNull($discord->webhook()->executeAndWait('hello')->threadId);
	}

	public function testGetEditAndDeleteTakeAThread(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message()),
			FakeDiscord::json(200, FakeDiscord::message()),
			FakeDiscord::noContent(),
		);
		$webhook = $discord->webhook();

		$webhook->getMessage(FakeDiscord::MESSAGE_ID, self::THREAD_ID);
		$webhook->editMessage(FakeDiscord::MESSAGE_ID, 'edited', self::THREAD_ID);
		$webhook->deleteMessage(FakeDiscord::MESSAGE_ID, self::THREAD_ID);

		foreach ([0, 1, 2] as $request) {
			self::assertSame(self::MESSAGE_URI . '?thread_id=' . self::THREAD_ID, (string) $discord->request($request)->getUri());
		}
	}

	public function testAMessageCarriesItsThreadIntoLaterCalls(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message(['channel_id' => self::THREAD_ID])),
			FakeDiscord::json(200, FakeDiscord::message(['channel_id' => self::THREAD_ID])),
			FakeDiscord::noContent(),
		);
		$webhook = $discord->webhook();

		$message = $webhook->executeAndWait('hello', threadId: self::THREAD_ID);
		$edited = $webhook->editMessage($message, 'edited');
		$webhook->deleteMessage($edited);

		self::assertSame(self::MESSAGE_URI . '?thread_id=' . self::THREAD_ID, (string) $discord->request(1)->getUri());
		self::assertSame(self::MESSAGE_URI . '?thread_id=' . self::THREAD_ID, (string) $discord->request(2)->getUri());
		self::assertTrue($edited->threadId?->equals(self::THREAD_ID));
	}

	public function testAnInvalidThreadIdIsRejectedBeforeSending(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		try {
			$discord->webhook()->execute('hello', threadId: '1&wait=true');
		} finally {
			self::assertSame(0, $discord->requestCount());
		}
	}
}

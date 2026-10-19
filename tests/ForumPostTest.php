<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Payload;
use Internet\InterCord\Snowflake;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\TestCase;

final class ForumPostTest extends TestCase
{
	private const POST_ID = '1500000000000000004';
	private const TAG_A = '1600000000000000005';
	private const TAG_B = '1600000000000000006';

	public function testStartingAForumPost(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute(
			(new Payload())->setContent('hello')->setThreadName('Weekly report')->setAppliedTags(self::TAG_A, Snowflake::from(self::TAG_B)),
		);

		self::assertSame(FakeDiscord::ENDPOINT, (string) $discord->request()->getUri());
		self::assertSame(
			['content' => 'hello', 'thread_name' => 'Weekly report', 'applied_tags' => [self::TAG_A, self::TAG_B]],
			$discord->body(),
		);
	}

	public function testTheReturnedMessageLivesInTheNewPost(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message(['channel_id' => self::POST_ID])),
			FakeDiscord::json(200, FakeDiscord::message(['channel_id' => self::POST_ID])),
		);

		$message = $discord->webhook()->executeAndWait((new Payload())->setContent('hello')->setThreadName('Weekly report'));
		$message->edit('edited');

		self::assertTrue($message->threadId?->equals(self::POST_ID));
		self::assertSame(
			FakeDiscord::ENDPOINT . '/messages/' . FakeDiscord::MESSAGE_ID . '?thread_id=' . self::POST_ID,
			(string) $discord->request(1)->getUri(),
		);
	}

	public function testAThreadIdAndAThreadNameCannotBeCombined(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		try {
			$discord->webhook()->execute((new Payload())->setContent('hello')->setThreadName('Post'), threadId: self::POST_ID);
		} finally {
			self::assertSame(0, $discord->requestCount());
		}
	}

	public function testTagsNeedANewPost(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		$discord->webhook()->execute((new Payload())->setContent('hello')->setAppliedTags(self::TAG_A));
	}

	public function testAnEmptyThreadNameIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new Payload())->setThreadName('');
	}

	public function testAPostCannotBeStartedByAnEdit(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->setContent('x')->setThreadName('Post'));
	}
}

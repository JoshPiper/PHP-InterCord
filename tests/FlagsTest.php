<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Payload;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\TestCase;

final class FlagsTest extends TestCase
{
	private const SUPPRESS_EMBEDS = 1 << 2;
	private const SUPPRESS_NOTIFICATIONS = 1 << 12;

	public function testNoFlagsByDefault(): void
	{
		self::assertArrayNotHasKey('flags', (new Payload())->setContent('x')->toExecuteBody(false));
	}

	public function testSuppressEmbeds(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute((new Payload())->setContent('https://example.com')->suppressEmbeds());

		self::assertSame(['content' => 'https://example.com', 'flags' => self::SUPPRESS_EMBEDS], $discord->body());
	}

	public function testSuppressNotifications(): void
	{
		self::assertSame(
			['content' => 'quiet', 'flags' => self::SUPPRESS_NOTIFICATIONS],
			(new Payload())->setContent('quiet')->suppressNotifications()->toExecuteBody(false),
		);
	}

	public function testBothCombine(): void
	{
		self::assertSame(
			self::SUPPRESS_EMBEDS | self::SUPPRESS_NOTIFICATIONS,
			(new Payload())->setContent('x')->suppressEmbeds()->suppressNotifications()->toExecuteBody(false)['flags'] ?? null,
		);
	}

	public function testTurningAFlagBackOffOnANewMessageSendsNothing(): void
	{
		self::assertArrayNotHasKey('flags', (new Payload())->setContent('x')->suppressEmbeds()->suppressEmbeds(false)->toExecuteBody(false));
	}

	public function testAnEditCanSuppressEmbeds(): void
	{
		self::assertSame(['flags' => self::SUPPRESS_EMBEDS], (new Payload())->suppressEmbeds()->toEditBody());
	}

	public function testAnEditCanRestoreEmbeds(): void
	{
		self::assertSame(['flags' => 0], (new Payload())->suppressEmbeds(false)->toEditBody());
	}

	public function testAnEditLeavesFlagsAloneByDefault(): void
	{
		self::assertArrayNotHasKey('flags', (new Payload())->setContent('x')->toEditBody());
	}

	public function testNotificationsCannotBeChangedByAnEdit(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new Payload())->setContent('x')->suppressNotifications()->toEditBody();
	}
}

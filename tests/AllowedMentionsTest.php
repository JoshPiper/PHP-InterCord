<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\AllowedMentions;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Payload;
use Internet\InterCord\Snowflake;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AllowedMentionsTest extends TestCase
{
	private const USER = '1700000000000000007';
	private const ROLE = '1800000000000000008';

	/**
	 * @return iterable<string, array{AllowedMentions, array<string, mixed>}>
	 */
	public static function shapes(): iterable
	{
		yield 'none' => [AllowedMentions::none(), ['parse' => []]];
		yield 'all' => [AllowedMentions::all(), ['parse' => ['users', 'roles', 'everyone']]];
		yield 'only users' => [new AllowedMentions(users: true), ['parse' => ['users']]];
		yield 'only everyone' => [new AllowedMentions(everyone: true), ['parse' => ['everyone']]];
		yield 'specific users and roles' => [
			new AllowedMentions(users: [self::USER], roles: [Snowflake::from(self::ROLE)]),
			['parse' => [], 'users' => [self::USER], 'roles' => [self::ROLE]],
		];
		yield 'all roles, specific users' => [
			new AllowedMentions(users: [self::USER], roles: true),
			['parse' => ['roles'], 'users' => [self::USER]],
		];
		yield 'an empty list is the same as none' => [new AllowedMentions(users: []), ['parse' => []]];
	}

	/**
	 * @param array<string, mixed> $expected
	 */
	#[DataProvider('shapes')]
	public function testShapes(AllowedMentions $mentions, array $expected): void
	{
		self::assertSame($expected, $mentions->jsonSerialize());
	}

	public function testItIsSentWithTheMessage(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute((new Payload())->setContent('<@' . self::USER . '> hi @everyone')->setAllowedMentions(AllowedMentions::none()));

		self::assertSame(
			['content' => '<@' . self::USER . '> hi @everyone', 'allowed_mentions' => ['parse' => []]],
			$discord->body(),
		);
	}

	public function testItCanBeSentWithAnEdit(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->setContent('x')->setAllowedMentions(new AllowedMentions(users: true)));

		self::assertSame(['content' => 'x', 'allowed_mentions' => ['parse' => ['users']]], $discord->body());
	}

	public function testDiscordsDefaultIsKeptWhenUnset(): void
	{
		self::assertArrayNotHasKey('allowed_mentions', (new Payload())->setContent('x')->jsonSerialize());
	}

	public function testMoreThan100IdsAreRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		new AllowedMentions(users: array_map(static fn(int $i): string => (string) (1700000000000000000 + $i), range(1, 101)));
	}

	public function testInvalidIdsAreRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		new AllowedMentions(roles: ['everyone']);
	}
}

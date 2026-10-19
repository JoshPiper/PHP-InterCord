<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Snowflake;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SnowflakeTest extends TestCase
{
	public function testFromStringIntAndSnowflake(): void
	{
		$fromString = Snowflake::from('175928847299117063');

		self::assertSame('175928847299117063', (string) $fromString);
		self::assertSame('175928847299117063', $fromString->value);
		self::assertTrue($fromString->equals(Snowflake::from(175928847299117063)));
		self::assertSame($fromString, Snowflake::from($fromString));
	}

	public function testCreatedAtDecodesTheTimestampBits(): void
	{
		// Discord's documented example: 175928847299117063 was created at 2016-04-30 11:18:25.796 UTC.
		self::assertSame(
			'2016-04-30T11:18:25.796+00:00',
			Snowflake::from('175928847299117063')->createdAt()->format('Y-m-d\TH:i:s.vP'),
		);
	}

	public function testTheLargestSnowflakeIsAccepted(): void
	{
		self::assertSame('18446744073709551615', (string) Snowflake::from('18446744073709551615'));
	}

	public function testEqualsComparesAgainstStrings(): void
	{
		$id = Snowflake::from('1');

		self::assertTrue($id->equals('1'));
		self::assertFalse($id->equals('2'));
	}

	public function testItSerialisesAsAJsonString(): void
	{
		self::assertSame('{"id":"175928847299117063"}', json_encode(['id' => Snowflake::from('175928847299117063')]));
	}

	/**
	 * @return iterable<string, array{string|int}>
	 */
	public static function invalid(): iterable
	{
		yield 'empty' => [''];
		yield 'zero' => ['0'];
		yield 'negative int' => [-1];
		yield 'leading zero' => ['0123'];
		yield 'not digits' => ['12a'];
		yield 'whitespace' => [' 123'];
		yield 'above 2^64 - 1' => ['18446744073709551616'];
		yield 'too many digits' => ['123456789012345678901'];
	}

	#[DataProvider('invalid')]
	public function testInvalidIdsAreRejected(string|int $id): void
	{
		$this->expectException(InvalidArgumentException::class);
		Snowflake::from($id);
	}

	public function testWebhookIdIsASnowflake(): void
	{
		self::assertTrue((new FakeDiscord())->webhook()->id->equals(FakeDiscord::ID));
	}
}

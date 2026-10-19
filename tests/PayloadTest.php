<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Internal\Payload;
use Internet\InterCord\RichEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayloadTest extends TestCase
{
	/**
	 * @return iterable<string, array{string|int|float, string}>
	 */
	public static function scalars(): iterable
	{
		yield 'string' => ['hello', 'hello'];
		yield 'int' => [4321, '4321'];
		yield 'float' => [1.5, '1.5'];
	}

	#[DataProvider('scalars')]
	public function testScalarsBecomeContent(string|int|float $content, string $expected): void
	{
		self::assertSame(['content' => $expected], Payload::from($content)->jsonSerialize());
	}

	public function testAnEmbedOnItsOwn(): void
	{
		self::assertSame(
			['embeds' => [['title' => 'hi', 'type' => 'rich']]],
			Payload::from((new RichEmbed())->setTitle('hi'))->jsonSerialize(),
		);
	}

	public function testArraysKeepEmbedOrderAndTheLastString(): void
	{
		$payload = Payload::from(['ignored', (new RichEmbed())->setTitle('a'), 'kept', (new RichEmbed())->setTitle('b')]);

		self::assertSame(
			['content' => 'kept', 'embeds' => [['title' => 'a', 'type' => 'rich'], ['title' => 'b', 'type' => 'rich']]],
			$payload->jsonSerialize(),
		);
	}

	public function testAPayloadIsReturnedUnchanged(): void
	{
		$payload = (new Payload())->setContent('hello');

		self::assertSame($payload, Payload::from($payload, 'ignored', 'ignored'));
	}

	public function testOverridesAndTts(): void
	{
		$payload = Payload::from('hello', 'Bot', 'https://example.com/a.png')->setTTS(true);

		self::assertSame(
			['content' => 'hello', 'username' => 'Bot', 'avatar_url' => 'https://example.com/a.png', 'tts' => true],
			$payload->jsonSerialize(),
		);
	}

	public function testEmptyOverridesAreLeftOut(): void
	{
		self::assertSame(['content' => 'hello'], Payload::from('hello', '', '')->jsonSerialize());
	}
}

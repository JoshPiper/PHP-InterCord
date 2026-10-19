<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\InvalidColorException;
use Internet\InterCord\Internal\Color;
use Internet\InterCord\RichEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
	/**
	 * @return iterable<string, array{int|string|Color, int}>
	 */
	public static function colours(): iterable
	{
		foreach (['white' => [16777215, 'FFFFFF', 'FFF', [255, 255, 255]], 'red' => [16711680, 'FF0000', 'F00', [255, 0, 0]], 'green' => [65280, '00FF00', '0F0', [0, 255, 0]], 'blue' => [255, '0000FF', '00F', [0, 0, 255]], 'mixed' => [0x1A2B3C, '1a2b3c', null, [0x1A, 0x2B, 0x3C]]] as $name => [$decimal, $hex, $short, $rgb]) {
			yield "{$name} as int" => [$decimal, $decimal];
			yield "{$name} as numeric string" => [(string) $decimal, $decimal];
			yield "{$name} as hex" => ["#{$hex}", $decimal];
			yield "{$name} as hex without #" => [$hex, $decimal];
			if ($short !== null) {
				yield "{$name} as short hex" => ["#{$short}", $decimal];
			}
			yield "{$name} as Color" => [new Color(...$rgb), $decimal];
		}
	}

	#[DataProvider('colours')]
	public function testColours(int|string|Color $colour, int $expected): void
	{
		self::assertSame($expected, (new RichEmbed())->setColor($colour)->jsonSerialize()['color']);
	}

	/**
	 * Discord treats 0 as "no colour", so black is left out.
	 *
	 * @return iterable<string, array{int|string|Color}>
	 */
	public static function black(): iterable
	{
		yield 'int' => [0];
		yield 'numeric string' => ['0'];
		yield 'short hex' => ['#000'];
		yield 'hex' => ['#000000'];
		yield 'Color' => [new Color(0, 0, 0)];
	}

	#[DataProvider('black')]
	public function testBlackIsLeftOut(int|string|Color $colour): void
	{
		self::assertArrayNotHasKey('color', (new RichEmbed())->setColor($colour)->jsonSerialize());
	}

	/**
	 * @return iterable<string, array{int|string}>
	 */
	public static function outOfRange(): iterable
	{
		yield 'above white' => [0x1000000];
		yield 'negative' => [-1];
		yield 'above white as a string' => ['16777216'];
	}

	#[DataProvider('outOfRange')]
	public function testOutOfRangeNumbersAreRejected(int|string $colour): void
	{
		$this->expectException(InvalidColorException::class);
		(new RichEmbed())->setColor($colour);
	}

	/**
	 * @return iterable<string, array{int, int, int}>
	 */
	public static function outOfRangeChannels(): iterable
	{
		yield 'red above 255' => [256, 0, 0];
		yield 'green negative' => [0, -1, 0];
		yield 'blue above 255' => [0, 0, 300];
	}

	#[DataProvider('outOfRangeChannels')]
	public function testOutOfRangeChannelsAreRejected(int $r, int $g, int $b): void
	{
		$this->expectException(InvalidColorException::class);
		new Color($r, $g, $b);
	}
}

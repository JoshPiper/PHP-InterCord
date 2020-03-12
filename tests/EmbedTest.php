<?php

declare(strict_types=1);


use PHPUnit\Framework\TestCase;
use Internet\InterCord\RichEmbed;
use Internet\InterCord\Internal\Color;

class EmbedTest extends TestCase {
	/** Data provider for colours, and their corresponding decimal colour.
	 * @return array
	 */
	public function colours(){
		return [
			[16777215, 16777215], // White
			[16711680, 16711680], // Red
			[65280, 65280], // Green
			[255, 255], // Blue
			['16777215', 16777215],
			['16711680', 16711680],
			['65280', 65280],
			['255', 255],
			['#FFF', 16777215],
			['#F00', 16711680],
			['#0F0', 65280],
			['#00F', 255],
			['#FFFFFF', 16777215],
			['#FF0000', 16711680],
			['#00FF00', 65280],
			['#0000FF', 255],
			[new Color(255, 255, 255), 16777215],
			[new Color(255, 0, 0), 16711680],
			[new Color(0, 255, 0), 65280],
			[new Color(0, 0, 255), 255],
		];
	}

	/** Data provider for "empty" colours (which resolve to 0).
	 * @return array
	 */
	public function emptyColors(){
		return [
			0,
			'0',
			'#000',
			'#000000',
			new Color(0, 0, 0)
		];
	}

	/** Data provider for "bad" colours (which resolve should cause errors).
	 * @return array
	 */
	public function badColors(){
		return [
			16777216, // Above the largest value.
			-1, // Below 0.
			'#00', // Not a hex triplet.
			'#00000000' // Not a hex triplet either.
		];
	}

	/**
	 * @dataProvider colours
	 * @param int|string|Color $color
	 * @param int $expected
	 */
	public function testColorsWork($color, $expected){
		$embed = new RichEmbed();
		$embed->setColor($color);

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('color', $data);
		$this->assertEquals($expected, $data['color']);
	}

	/**
	 * @dataProvider @emptyColors
	 * @param int|string|Color $color
	 */
	public function testEmptyColoursAreEmpty($color){
		$embed = new RichEmbed();
		$embed->setColor($color);

		$data = $embed->jsonSerialize();
		$this->assertArrayNotHasKey('color', $data);
	}

	/**
	 * @dataProvider @badColors
	 * @param int|string|Color $color
	 */
	public function testBadColoursThrowExceptions($color){
		$this->expectException(Exception::class); // TODO: Change this to a proper exception class.
		$embed = new RichEmbed();
		$embed->setColor($color);
	}
}

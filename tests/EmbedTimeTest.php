<?php

declare(strict_types=1);


use PHPUnit\Framework\TestCase;
use Internet\InterCord\RichEmbed;

class EmbedTimeTest extends TestCase {
	public function timestamps(){
		return [
			[0, '1970-01-01T00:00:00+0000'],
			[1, '1970-01-01T00:00:01+0000'],
			[86400, '1970-01-02T00:00:00+0000'],
		];
	}

	/**
	 * @dataProvider timestamps
	 */
	public function testTimestampUnix($stamp, $expected){
		$embed = new RichEmbed();
		$embed->setTimestamp($stamp);

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('timestamp', $data);
		$this->assertEquals($expected, $data['timestamp']);
	}

	public function datetimes(){
		return [
			[DateTime::createFromFormat('U', '0'), '1970-01-01T00:00:00+0000'],
			[DateTime::createFromFormat('U', '1'), '1970-01-01T00:00:01+0000'],
			[DateTime::createFromFormat('U', '86400'), '1970-01-02T00:00:00+0000'],
		];
	}

	/**
	 * @dataProvider datetimes
	 */
	public function testDateTimeObject($time, $expected){
		$embed = new RichEmbed();
		$embed->setTimestamp($time);

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('timestamp', $data);
		$this->assertEquals($expected, $data['timestamp']);
	}

	public function formatpairs(){
		return [
			['0', 'U', '1970-01-01T00:00:00+0000'],
			['15-Feb-2009', 'j-M-Y', '2009-02-15T00:00:00+0000']
		];
	}

	/**
	 * @dataProvider formatpairs
	 */
	public function testTimeFromFormat($time, $format, $expected){
		$embed = new RichEmbed();
		$embed->setTimestamp($time, $format);

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('timestamp', $data);
		$this->assertEquals($expected, $data['timestamp']);
	}
}

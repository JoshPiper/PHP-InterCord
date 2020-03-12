<?php

declare(strict_types=1);


use PHPUnit\Framework\TestCase;
use Internet\InterCord\RichEmbed;

class EmbedElementsTest extends TestCase {
	public function testTitles(){
		$embed = new RichEmbed();
		$embed->setTitle('hello');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('title', $data);
		$this->assertEquals('hello', $data['title']);
	}

	public function testDescription(){
		$embed = new RichEmbed();
		$embed->setDescription('hello');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('description', $data);
		$this->assertEquals('hello', $data['description']);
	}

	public function testURL(){
		$embed = new RichEmbed();
		$embed->setURL('hello');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('url', $data);
		$this->assertEquals('hello', $data['url']);
	}
}

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

	public function testAuthor(){
		$embed = new RichEmbed();
		$embed->setAuthor('hello', 'google.com', 'imgur.com', 'proxy.imgur.com');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('author', $data);

		$author = $data['author'];
		$this->assertEquals('hello', $author['name']);
		$this->assertEquals('google.com', $author['url']);
		$this->assertEquals('imgur.com', $author['icon_url']);
		$this->assertEquals('proxy.imgur.com', $author['proxy_icon_url']);
	}

	public function testVideos(){
		$embed = new RichEmbed();
		$embed->setVideo('youtube.com', 1920, 1080);

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('video', $data);

		$author = $data['video'];
		$this->assertEquals('youtube.com', $author['url']);
		$this->assertEquals(1920, $author['width']);
		$this->assertEquals(1080, $author['height']);
	}

	public function testProvider(){
		$embed = new RichEmbed();
		$embed->setProvider('provider', 'provider.com');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('provider', $data);

		$author = $data['video'];
		$this->assertEquals('provider', $author['name']);
		$this->assertEquals('provider.com', $author['url']);
	}
}
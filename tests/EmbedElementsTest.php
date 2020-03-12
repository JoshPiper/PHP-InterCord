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

		$video = $data['video'];
		$this->assertEquals('youtube.com', $video['url']);
		$this->assertEquals(1920, $video['width']);
		$this->assertEquals(1080, $video['height']);
	}

	public function testImages(){
		$embed = new RichEmbed();
		$embed->setImage('imgur.com', 'proxy.imgur.com', 1920, 1080);
		$embed->setThumbnail('imgur.com', 'proxy.imgur.com', 1920, 1080);

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('image', $data);

		$image = $data['image'];
		$this->assertEquals('imgur.com', $image['url']);
		$this->assertEquals('proxy.imgur.com', $image['proxy_url']);
		$this->assertEquals(1920, $image['width']);
		$this->assertEquals(1080, $image['height']);

		$thumb = $data['thumbnail'];
		$this->assertEquals('imgur.com', $thumb['proxy_url']);
		$this->assertEquals('proxy.imgur.com', $image['proxy_url']);
		$this->assertEquals(1920, $thumb['width']);
		$this->assertEquals(1080, $thumb['height']);
	}

	public function testProvider(){
		$embed = new RichEmbed();
		$embed->setProvider('provider', 'provider.com');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('provider', $data);

		$provider = $data['provider'];
		$this->assertEquals('provider', $provider['name']);
		$this->assertEquals('provider.com', $provider['url']);
	}

	public function testFooter(){
		$embed = new RichEmbed();
		$embed->setFooter('footer text', 'imgur.com', 'proxy.imgur.com');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('footer', $data);

		$footer = $data['footer'];
		$this->assertEquals('footer text', $footer['text']);
		$this->assertEquals('imgur.com', $footer['icon_url']);
		$this->assertEquals('proxy.imgur.com', $footer['proxy_icon_url']);
	}
}

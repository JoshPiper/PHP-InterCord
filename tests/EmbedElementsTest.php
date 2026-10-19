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
		$embed->setAuthor('hello', 'google.com', 'imgur.com');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('author', $data);

		$author = $data['author'];
		$this->assertEquals('hello', $author['name']);
		$this->assertEquals('google.com', $author['url']);
		$this->assertEquals('imgur.com', $author['icon_url']);
		$this->assertArrayNotHasKey('proxy_icon_url', $author);
	}

	public function testImages(){
		$embed = new RichEmbed();
		$embed->setImage('imgur.com');
		$embed->setThumbnail('imgur.com/thumb');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('image', $data);
		$this->assertEquals(['url' => 'imgur.com'], $data['image']);

		$this->assertArrayHasKey('thumbnail', $data);
		$this->assertEquals(['url' => 'imgur.com/thumb'], $data['thumbnail']);
	}

	public function testFooter(){
		$embed = new RichEmbed();
		$embed->setFooter('footer text', 'imgur.com');

		$data = $embed->jsonSerialize();
		$this->assertArrayHasKey('footer', $data);

		$footer = $data['footer'];
		$this->assertEquals('footer text', $footer['text']);
		$this->assertEquals('imgur.com', $footer['icon_url']);
		$this->assertArrayNotHasKey('proxy_icon_url', $footer);
	}
}

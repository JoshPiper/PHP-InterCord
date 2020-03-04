<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\InterCord\Webhook;
use GuzzleHttp\Exception\RequestException;

class WebhookTest extends TestCase {
	protected $webhook;
	protected $url;
	protected $id;
	protected $token;
	protected function setUp(): void{
		parent::setUp();
		$this->webhook = new Webhook($_SERVER['WEBHOOK_URL']);
		['WEBHOOK_URL' => $this->url, 'WEBHOOK_ID' => $this->id, 'WEBHOOK_TOKEN' => $this->token] = $_SERVER;
	}

	public function testCreationFromURL(): void {
		$webhook = new Webhook($this->url);
		$this->assertInstanceOf(Webhook::class, $webhook);
	}

	public function testCreationFromTokenPair(): void {
		$webhook = new Webhook($this->id, $this->token);
		$this->assertInstanceOf(Webhook::class, $webhook);
	}

	public function testCreationFromReverseTokenPair(): void {
		$this->expectException(InvalidArgumentException::class);
		$webhook = new Webhook($this->token, $this->id);
	}

	public function testEmptyException(): void {
		$this->expectException(RequestException::class);
		$this->webhook->execute('');
	}
}
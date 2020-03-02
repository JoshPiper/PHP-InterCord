<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\InterCord\Webhook;

final class WebhookSetupTest extends TestCase {
	protected function setUp(): void{
		parent::setUp();

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
}
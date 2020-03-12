<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\InterCord\Webhook;
use Internet\InterCord\QueuedWebhook;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class WebhookTest extends TestCase {
	protected $webhook;
	protected $url;
	protected $id;
	protected $token;
	protected function setUp(): void{
		parent::setUp();
		$this->webhook = new QueuedWebhook($_SERVER['WEBHOOK_URL']);
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
		$this->expectException(ClientException::class);
		$this->webhook->execute('');
	}

	public function testEmptyAwaitingException(): void {
		$this->expectException(Exception::class);
		$this->webhook->execute('', 'test', '', true);
	}

	public function testAwaitingSend(): void {
		$ran = false;
		while (!$ran){
			try {
				$data = $this->webhook->execute('this is a string', 'test', '', true);
				$ran = true;
				$this->assertIsArray($data);
			} catch (ClientException $ex){
				if ($ex->getCode() == 429){
					$err = json_decode($ex->getResponse()->getBody()->getContents());
					usleep($err->retry_after * 2);
				} else {
					throw $ex;
				}
			}
		}


	}
}

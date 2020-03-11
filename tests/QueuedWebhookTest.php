<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\InterCord\QueuedWebhook;
use const GuzzleHttp\Exception\ClientException;

class QueuedWebhookTest extends TestCase {
	protected $webhook;
	public function setUp(): void{
		$this->webhook = new QueuedWebhook($_SERVER['WEBHOOK_URL']);
	}

	public function tearDown(): void{
		unset($this->webhook);
	}

	public function testExecution(){
		$contents = ['content', '1234', 4321, ['array with one arg', (new \Internet\InterCord\RichEmbed())->addField("hi", "yes")]];
		$usernames = ['', 'testUser'];
		$avatars = ['', 'https://images.theconversation.com/files/93616/original/image-20150902-6700-t2axrz.jpg?ixlib=rb-1.1.0&q=45&auto=format&w=1000&fit=clip'];

		foreach ($avatars as $avatar){
			foreach ($usernames as $username){
				foreach ($contents as $content){
					$this->webhook->append($content, $username, $avatar);
					$this->addToAssertionCount(1);
				}
			}
		}
		$this->webhook->run();
		$this->addToAssertionCount(1);
	}

	public function testEmptyMessageException(){
		$this->expectException(ClientException::class);
		$this->webhook->prepend('');
		$this->webhook->run(1);
	}
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\InterCord\QueuedWebhook;
use Internet\InterCord\Internal\Payload;
use GuzzleHttp\Exception\ClientException;

class QueuedWebhookTest extends TestCase {
	protected $webhook;
	public function setUp(): void{
		$this->webhook = new QueuedWebhook($_SERVER['WEBHOOK_URL']);
	}

	public function tearDown(): void{
		unset($this->webhook);
	}

	public function payloads(){
		$contents = ['content', '1234', 4321, ['array with one arg', (new \Internet\InterCord\RichEmbed())->addField("hi", "yes")]];
		$usernames = ['', 'testUser'];
		$avatars = ['', 'https://images.theconversation.com/files/93616/original/image-20150902-6700-t2axrz.jpg?ixlib=rb-1.1.0&q=45&auto=format&w=1000&fit=clip'];

		foreach ($contents as $content){
			foreach ($usernames as $username){
				foreach ($avatars as $avatar){
					yield [$content, $username, $avatar];
				}
			}
		}
	}

	/**
	 * @dataProvider payloads
	 * @param $content
	 * @param $username
	 * @param $avatar
	 * @throws Exception
	 */
	public function testExecution($content, $username, $avatar){
		$this->webhook->append($content, $username, $avatar);
		$this->webhook->run();
		$this->assertEquals($this->webhook->count(), 0, "empty");
	}

	/**
	 * @dataProvider payloads
	 * @param $content
	 * @param $username
	 * @param $avatar
	 * @throws Exception
	 */
	public function testDelivery($content, $username, $avatar){
		$this->webhook->deliver($content, $username, $avatar);
		$this->assertEquals($this->webhook->count(), 0, "empty");
	}

	public function testEmptyMessageException(){
		$this->expectException(ClientException::class);
		$this->webhook->prepend('');
		$this->webhook->run(1);
	}

	public function testSendingTTSMessage(){
		$this->webhook->prepend((new Payload())->setTTS(true)->setContent('hello'));
		$this->addToAssertionCount(1);
		$this->webhook->run(1);
		$this->addToAssertionCount(1);
	}
}

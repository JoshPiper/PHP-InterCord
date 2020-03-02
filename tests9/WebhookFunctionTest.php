<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\InterCord\Webhook;

class WebhookFunctionTest extends TestCase {
	protected $webhook;

	protected function setUp(): void{
		parent::setUp();
		$this->webhook = new Webhook($_SERVER['WEBHOOK_URL']);
	}

	public function executionProvider(){
		$contents = ['content', 'content 2', '1234', 4321, ['array with one arg'], ['array with', 'two args'], ['array with', 1234, 'types']];
		$usernames = ['', 'testUser', 1234];
		$avatars = ['', 'https://images.theconversation.com/files/93616/original/image-20150902-6700-t2axrz.jpg?ixlib=rb-1.1.0&q=45&auto=format&w=1000&fit=clip'];
		$awaits = [true, false];

		foreach ($avatars as $avatar){
			foreach ($usernames as $username){
				foreach ($contents as $content){
					foreach ($awaits as $await){
						yield [$content, $username, $avatar, $await];
					}
				}
			}
		}
	}

	/**
	 *
	 * @dataProvider executionProvider
	 */
	public function testExecution($content, $username, $avatar, $await){
		$this->expectNotToPerformAssertions();
		$this->webhook->execute($content, $username, $avatar, $await);
	}
}
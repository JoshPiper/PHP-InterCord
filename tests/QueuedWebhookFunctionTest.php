<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\InterCord\QueuedWebhook;

class QueuedWebhookFunctionTest extends TestCase {
	static $webhook;

	public static function setUpBeforeClass(): void{
		parent::setUpBeforeClass();
		static::$webhook = new QueuedWebhook($_SERVER['WEBHOOK_URL']);
	}

	public static function tearDownAfterClass(): void{
		parent::tearDownAfterClass();
		static::$webhook = null;
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
	 * @dataProvider executionProvider
	 */
	public function testExecution($content, $username, $avatar, $await){
		$this->webhook->append($content, $username, $avatar, $await);
		$this->webhook->run(1);

		$this->addToAssertionCount(1);
	}
}
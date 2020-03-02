<?php


namespace Internet\InterCord;


use Countable;
use Internet\InterCord\Internal\Payload;
use GuzzleHttp\Exception\ClientException;

class QueuedWebhook extends Webhook implements Countable {
	/** @var array Internal Queue used to hold awaiting payloads. */
	protected $queue = [];

	/** Append a Payload to the queue.
	 * @param array|string|RichEmbed|Payload $content
	 * @param string $username
	 * @param string $avatar
	 */
	public function append($content, string $username = '', string $avatar = ''){
		$payload = static::createPayload($content, $username, $avatar);
		array_push($this->queue, $payload);
	}

	/** Prepend a Payload to the queue.
	 * @param array|string|RichEmbed|Payload $content
	 * @param string $username
	 * @param string $avatar
	 */
	public function prepend($content, string $username = '', string $avatar = ''){
		$payload = static::createPayload($content, $username, $avatar);
		array_unshift($this->queue, $payload);
	}

	/** Get the next payload in the queue, or null if there is none.
	 * @return Payload|null
	 */
	public function next(): ?Payload {
		if ($this->count() == 0){return null;}

		return array_shift($this->queue);
	}

	/**
	 * Dispatch queued payloads.
	 * @param int $max Maxinimum number of payloads to send.
	 */
	public function run(int $max = 0){
		$ran = 0;

		while ($this->count() && ($max == 0 || $ran < $max)){
			$payload = $this->next();
			try {
				$this->execute($payload);
			} catch (ClientException $ex){
				switch ($ex->getCode()){
					case 429:
						// Rate Limiting

						// Re-add our payload to the front of the queue.
						$this->prepend($payload);

						// And delay for however long discord wants.
						$err = json_decode($ex->getResponse()->getBody()->getContents());
						usleep($err->retry_after);
						break;
					default:
						throw $ex;
				}
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function count(){
		return count($this->queue);
	}
}
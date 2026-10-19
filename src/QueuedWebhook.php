<?php

declare(strict_types=1);

namespace Internet\InterCord;

use Countable;
use Internet\InterCord\Exception\InterCordException;
use Internet\InterCord\Exception\RateLimitedException;
use Internet\InterCord\Exception\TransportException;
use Internet\InterCord\Internal\Payload;

/**
 * Queues payloads for a webhook and sends them in order.
 */
final class QueuedWebhook implements Countable
{
	/** @var list<Payload> */
	private array $queue = [];

	public function __construct(
		private readonly Webhook $webhook,
	) {}

	/**
	 * Add a payload to the back of the queue.
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 */
	public function append(array|string|int|float|RichEmbed|Payload $content, string $username = '', string $avatar = ''): void
	{
		$this->queue[] = Payload::from($content, $username, $avatar);
	}

	/**
	 * Add a payload to the front of the queue.
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 */
	public function prepend(array|string|int|float|RichEmbed|Payload $content, string $username = '', string $avatar = ''): void
	{
		array_unshift($this->queue, Payload::from($content, $username, $avatar));
	}

	/**
	 * Take the next payload off the queue, or null if it's empty.
	 */
	public function next(): ?Payload
	{
		return array_shift($this->queue);
	}

	/**
	 * Send queued payloads in order. Rate limits are waited out by the webhook itself.
	 *
	 * A payload that fails for a transient reason (still rate limited after every retry, or no response at all)
	 * goes back on the front of the queue before the exception is rethrown, so calling run() again resumes from it.
	 * A payload Discord rejects outright is dropped, since sending it again would fail the same way.
	 *
	 * @param int $max Maximum number of payloads to send; 0 sends them all.
	 * @throws InterCordException
	 */
	public function run(int $max = 0): void
	{
		$sent = 0;
		while ($this->queue !== [] && ($max === 0 || $sent < $max)) {
			$payload = array_shift($this->queue);
			try {
				$this->webhook->execute($payload);
			} catch (RateLimitedException|TransportException $ex) {
				array_unshift($this->queue, $payload);
				throw $ex;
			}
			$sent++;
		}
	}

	public function count(): int
	{
		return count($this->queue);
	}

	/**
	 * Put a payload at the front of the queue and send it straight away.
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 * @throws InterCordException
	 */
	public function deliver(array|string|int|float|RichEmbed|Payload $content, string $username = '', string $avatar = ''): void
	{
		$this->prepend($content, $username, $avatar);
		$this->run(1);
	}
}

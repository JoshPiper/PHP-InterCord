<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use GuzzleHttp\Psr7\Response;
use Internet\InterCord\Exception\RateLimitedException;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\TestCase;

final class RateLimitTest extends TestCase
{
	public function testMissingRateLimitHeadersAreFine(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent(), FakeDiscord::noContent());
		$webhook = $discord->webhook();

		$webhook->execute('one');
		$webhook->execute('two');

		self::assertSame([], $discord->sleeper->sleeps);
	}

	public function testRemainingRequestsDoNotWait(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::noContent(['X-RateLimit-Limit' => '5', 'X-RateLimit-Remaining' => '1', 'X-RateLimit-Reset-After' => '2.5']),
			FakeDiscord::noContent(),
		);
		$webhook = $discord->webhook();

		$webhook->execute('one');
		$webhook->execute('two');

		self::assertSame([], $discord->sleeper->sleeps);
	}

	public function testAnEmptyBucketWaitsForItsResetBeforeTheNextRequest(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::noContent(['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset-After' => '2.5']),
			FakeDiscord::noContent(),
		);
		$webhook = $discord->webhook();

		$webhook->execute('one');
		$webhook->execute('two');

		// One wait in total: the request that empties the bucket doesn't wait, the one after it does.
		self::assertCount(1, $discord->sleeper->sleeps);
		self::assertGreaterThan(2.0, $discord->sleeper->sleeps[0]);
		self::assertLessThanOrEqual(2.5, $discord->sleeper->sleeps[0]);
	}

	public function testTooManyRequestsWaitsRetryAfterSecondsAndRetries(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(429, ['message' => 'You are being rate limited.', 'retry_after' => 0.25, 'global' => false]),
			FakeDiscord::noContent(),
		);

		$discord->webhook()->execute('hello');

		self::assertSame([0.25], $discord->sleeper->sleeps);
		self::assertSame(2, $discord->requestCount());
		self::assertSame(['content' => 'hello'], $discord->body(1), 'The retried request should carry the full body again.');
	}

	public function testRetryAfterHeaderIsUsedWhenTheBodyHasNone(): void
	{
		$discord = new FakeDiscord(new Response(429, ['Retry-After' => '2']), FakeDiscord::noContent());

		$discord->webhook()->execute('hello');

		self::assertSame([2.0], $discord->sleeper->sleeps);
	}

	public function testAOneSecondWaitIsAssumedWithoutAnyRetryHint(): void
	{
		$discord = new FakeDiscord(new Response(429), FakeDiscord::noContent());

		$discord->webhook()->execute('hello');

		self::assertSame([1.0], $discord->sleeper->sleeps);
	}

	public function testGivingUpAfterMaxRetriesThrowsRateLimitedException(): void
	{
		$limited = FakeDiscord::json(429, ['message' => 'You are being rate limited.', 'retry_after' => 3, 'global' => true, 'code' => 0]);
		$discord = new FakeDiscord($limited, $limited, $limited);

		try {
			$discord->webhook(maxRetries: 2)->execute('hello');
			self::fail('Expected RateLimitedException.');
		} catch (RateLimitedException $ex) {
			self::assertSame(429, $ex->status);
			self::assertSame(3.0, $ex->retryAfter);
			self::assertTrue($ex->global);
			self::assertStringNotContainsString(FakeDiscord::TOKEN, $ex->getMessage());
		}

		self::assertSame(3, $discord->requestCount());
		self::assertSame([3.0, 3.0], $discord->sleeper->sleeps);
	}

	public function testGlobalLimitIsReadFromTheHeaderToo(): void
	{
		$discord = new FakeDiscord(new Response(429, ['X-RateLimit-Global' => 'true', 'Retry-After' => '1']));

		try {
			$discord->webhook(maxRetries: 0)->execute('hello');
			self::fail('Expected RateLimitedException.');
		} catch (RateLimitedException $ex) {
			self::assertTrue($ex->global);
		}

		self::assertSame([], $discord->sleeper->sleeps);
	}
}

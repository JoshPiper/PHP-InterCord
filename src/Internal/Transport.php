<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

use Internet\InterCord\Exception\DiscordApiException;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Exception\RateLimitedException;
use Internet\InterCord\Exception\TransportException;
use Internet\InterCord\Sleeper;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends requests to one webhook's endpoints, waiting out Discord's rate limits and turning failures into
 * library exceptions that never contain the webhook token.
 *
 * @internal
 */
final class Transport
{
	private const BASE_URL = 'https://discord.com/api/v10/webhooks/';
	private const REDACTED = '[redacted]';

	/** hrtime() seconds before which the bucket is known to be empty. */
	private ?float $blockedUntil = null;

	public function __construct(
		private readonly ClientInterface $client,
		private readonly RequestFactoryInterface $requests,
		private readonly StreamFactoryInterface $streams,
		private readonly Sleeper $sleeper,
		private readonly string $id,
		#[\SensitiveParameter]
		private readonly string $token,
		private readonly int $maxRetries,
	) {
	}

	/**
	 * @param string $path Appended after the token, e.g. '/messages/123'.
	 * @param array<string, string> $query
	 * @param ?array<mixed> $body Encoded as JSON when given.
	 * @return ?array<mixed> The decoded response body, or null when there wasn't one.
	 */
	public function send(string $method, string $path = '', array $query = [], ?array $body = null): ?array
	{
		$uri = self::BASE_URL . $this->id . '/' . $this->token . $path;
		if ($query !== []) {
			$uri .= '?' . http_build_query($query);
		}

		$json = null;
		if ($body !== null) {
			try {
				$json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			} catch (JsonException $ex) {
				throw new InvalidArgumentException('Payload could not be encoded as JSON: ' . $ex->getMessage(), 0, $ex);
			}
		}

		for ($attempt = 0; ; $attempt++) {
			$this->waitForBucket();

			// Rebuilt on every attempt, since a sent body stream has already been read to the end.
			$request = $this->requests->createRequest($method, $uri);
			if ($json !== null) {
				$request = $request
					->withHeader('Content-Type', 'application/json')
					->withBody($this->streams->createStream($json));
			}

			try {
				$response = $this->client->sendRequest($request);
			} catch (ClientExceptionInterface $ex) {
				// Deliberately not chained: client exceptions embed the full request URL, token included.
				throw new TransportException(sprintf(
					'%s %s failed (%s): %s',
					$method,
					$this->describe($path),
					$ex::class,
					$this->redact($ex->getMessage()),
				));
			}

			$this->recordBucket($response);
			$status = $response->getStatusCode();
			$data = $this->decode($response);

			if ($status === 429) {
				$retryAfter = $this->retryAfter($response, $data);
				if ($attempt >= $this->maxRetries) {
					throw new RateLimitedException(
						sprintf('%s %s was still rate limited after %d retries.', $method, $this->describe($path), $this->maxRetries),
						$retryAfter,
						($data['global'] ?? false) === true || $response->getHeaderLine('X-RateLimit-Global') === 'true',
						is_int($data['code'] ?? null) ? $data['code'] : null,
					);
				}

				$this->sleeper->sleep($retryAfter);
				continue;
			}

			if ($status >= 400) {
				$code = is_int($data['code'] ?? null) ? $data['code'] : null;
				$message = is_string($data['message'] ?? null) ? $data['message'] : $response->getReasonPhrase();
				throw new DiscordApiException(
					sprintf(
						'Discord rejected %s %s with %d%s: %s',
						$method,
						$this->describe($path),
						$status,
						$code === null ? '' : " (code {$code})",
						$this->redact($message),
					),
					$status,
					$code,
					is_array($data['errors'] ?? null) ? $data['errors'] : [],
				);
			}

			return $data;
		}
	}

	private function waitForBucket(): void
	{
		if ($this->blockedUntil === null) {
			return;
		}

		$wait = $this->blockedUntil - self::now();
		$this->blockedUntil = null;
		if ($wait > 0) {
			$this->sleeper->sleep($wait);
		}
	}

	private function recordBucket(ResponseInterface $response): void
	{
		$remaining = $response->getHeaderLine('X-RateLimit-Remaining');
		$resetAfter = $response->getHeaderLine('X-RateLimit-Reset-After');
		if ($remaining === '0' && is_numeric($resetAfter)) {
			$this->blockedUntil = self::now() + (float) $resetAfter;
		}
	}

	/**
	 * @param ?array<mixed> $data
	 */
	private function retryAfter(ResponseInterface $response, ?array $data): float
	{
		if (is_int($data['retry_after'] ?? null) || is_float($data['retry_after'] ?? null)) {
			return (float) $data['retry_after'];
		}

		$header = $response->getHeaderLine('Retry-After');
		return is_numeric($header) ? (float) $header : 1.0;
	}

	/**
	 * @return ?array<mixed>
	 */
	private function decode(ResponseInterface $response): ?array
	{
		$body = (string) $response->getBody();
		if ($body === '') {
			return null;
		}

		try {
			$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			// Error pages from Discord's edge (Cloudflare) aren't JSON; the status code still says enough.
			return null;
		}

		return is_array($data) ? $data : null;
	}

	private function describe(string $path): string
	{
		return '/webhooks/' . $this->id . '/' . self::REDACTED . $path;
	}

	private function redact(string $text): string
	{
		return str_replace($this->token, self::REDACTED, $text);
	}

	private static function now(): float
	{
		return hrtime(true) / 1e9;
	}
}

<?php

declare(strict_types=1);

namespace Internet\InterCord;

use GuzzleHttp\Psr7\Utils;
use Internet\InterCord\Exception\InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * A file to upload with a message. Files given by path are read when the request is sent, and again on a retry,
 * so queued messages don't hold their files in memory.
 */
final class Attachment
{
	private const MAX_DESCRIPTION = 1024;

	private function __construct(
		private readonly ?string $path,
		private readonly ?string $contents,
		public readonly string $filename,
		public readonly ?string $description,
	) {}

	/**
	 * @param ?string $filename The name Discord shows. Defaults to the file's own name.
	 * @param ?string $description Alt text, up to 1024 characters.
	 * @param bool $spoiler Blur the file until it's clicked.
	 * @throws InvalidArgumentException
	 */
	public static function fromPath(string $path, ?string $filename = null, ?string $description = null, bool $spoiler = false): self
	{
		if (!is_file($path) || !is_readable($path)) {
			throw new InvalidArgumentException("'{$path}' isn't a readable file.");
		}

		return new self($path, null, self::filename($filename ?? basename($path), $spoiler), self::description($description));
	}

	/**
	 * @param ?string $description Alt text, up to 1024 characters.
	 * @param bool $spoiler Blur the file until it's clicked.
	 * @throws InvalidArgumentException
	 */
	public static function fromString(string $contents, string $filename, ?string $description = null, bool $spoiler = false): self
	{
		return new self(null, $contents, self::filename($filename, $spoiler), self::description($description));
	}

	/**
	 * Reference this file from an embed or component in the same message, e.g. RichEmbed::setImage($file->url()).
	 */
	public function url(): string
	{
		return 'attachment://' . $this->filename;
	}

	/**
	 * A fresh stream of the file's contents, for one request attempt.
	 *
	 * @internal
	 */
	public function open(): StreamInterface
	{
		return $this->path === null ? Utils::streamFor($this->contents) : Utils::streamFor(Utils::tryFopen($this->path, 'r'));
	}

	private static function filename(string $filename, bool $spoiler): string
	{
		// Path separators, quotes and control characters would break out of the multipart header.
		if ($filename === '' || preg_match('/[\/\\\\"\x00-\x1F\x7F]/', $filename) === 1) {
			throw new InvalidArgumentException("'{$filename}' can't be used as an attachment filename.");
		}

		return $spoiler ? 'SPOILER_' . $filename : $filename;
	}

	private static function description(?string $description): ?string
	{
		if ($description !== null && preg_match('/^.{0,' . self::MAX_DESCRIPTION . '}$/us', $description) !== 1) {
			throw new InvalidArgumentException('An attachment description must be valid UTF-8 of at most ' . self::MAX_DESCRIPTION . ' characters.');
		}

		return $description;
	}
}

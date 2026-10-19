<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * Markdown text. In a Components V2 message, this takes the place of the content.
 */
final class TextDisplay implements TopLevelComponent, ContainerComponent
{
	private const MAX_CONTENT = 4000;

	/**
	 * @throws InvalidArgumentException When the text is empty or over 4000 characters.
	 */
	public function __construct(
		private readonly string $content,
	) {
		if (preg_match('/^.{1,' . self::MAX_CONTENT . '}$/us', $content) !== 1) {
			throw new InvalidArgumentException('A text display holds 1 to ' . self::MAX_CONTENT . ' characters of valid UTF-8.');
		}
	}

	public function jsonSerialize(): array
	{
		return ['type' => 10, 'content' => $this->content];
	}

	public function componentCount(): int
	{
		return 1;
	}

	public function requiresComponentsV2(): bool
	{
		return true;
	}
}

<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * One to three blocks of text with a thumbnail or button beside them.
 */
final class Section implements TopLevelComponent, ContainerComponent
{
	private const MAX_TEXTS = 3;

	/** @var list<TextDisplay> */
	private readonly array $texts;

	/**
	 * @throws InvalidArgumentException When there isn't 1 to 3 text displays.
	 */
	public function __construct(
		private readonly SectionAccessory $accessory,
		TextDisplay ...$texts,
	) {
		if ($texts === [] || count($texts) > self::MAX_TEXTS) {
			throw new InvalidArgumentException('A section holds 1 to ' . self::MAX_TEXTS . ' text displays.');
		}

		$this->texts = array_values($texts);
	}

	public function jsonSerialize(): array
	{
		return [
			'type' => 9,
			'components' => array_map(static fn(TextDisplay $text): array => $text->jsonSerialize(), $this->texts),
			'accessory' => $this->accessory->jsonSerialize(),
		];
	}

	public function componentCount(): int
	{
		return 1 + count($this->texts) + $this->accessory->componentCount();
	}

	public function requiresComponentsV2(): bool
	{
		return true;
	}
}

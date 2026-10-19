<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * A horizontal row of up to five buttons.
 */
final class ActionRow implements TopLevelComponent
{
	private const MAX_ITEMS = 5;

	/** @var list<ActionRowComponent> */
	private readonly array $items;

	/**
	 * @throws InvalidArgumentException When the row is empty or holds more than five items.
	 */
	public function __construct(ActionRowComponent ...$items)
	{
		if ($items === [] || count($items) > self::MAX_ITEMS) {
			throw new InvalidArgumentException('An action row holds 1 to ' . self::MAX_ITEMS . ' components.');
		}

		$this->items = array_values($items);
	}

	public function jsonSerialize(): array
	{
		return ['type' => 1, 'components' => array_map(static fn(ActionRowComponent $item): array => $item->jsonSerialize(), $this->items)];
	}

	public function componentCount(): int
	{
		return 1 + array_sum(array_map(static fn(ActionRowComponent $item): int => $item->componentCount(), $this->items));
	}

	public function requiresComponentsV2(): bool
	{
		return false;
	}
}

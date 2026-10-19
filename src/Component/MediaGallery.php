<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * A grid of one to ten images or videos.
 */
final class MediaGallery implements TopLevelComponent, ContainerComponent
{
	private const MAX_ITEMS = 10;

	/** @var list<MediaGalleryItem> */
	private readonly array $items;

	/**
	 * @throws InvalidArgumentException When there isn't 1 to 10 items.
	 */
	public function __construct(MediaGalleryItem ...$items)
	{
		if ($items === [] || count($items) > self::MAX_ITEMS) {
			throw new InvalidArgumentException('A media gallery holds 1 to ' . self::MAX_ITEMS . ' items.');
		}

		$this->items = array_values($items);
	}

	public function jsonSerialize(): array
	{
		return ['type' => 12, 'items' => array_map(static fn(MediaGalleryItem $item): array => $item->jsonSerialize(), $this->items)];
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

<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * Any component, sent exactly as given. The escape hatch for what InterCord has no builder for, chiefly interactive
 * buttons and select menus, which only webhooks owned by an application can send (and only that application can
 * answer).
 */
final class RawComponent implements TopLevelComponent, ActionRowComponent, ContainerComponent, SectionAccessory
{
	/** Component types that only render with the IS_COMPONENTS_V2 flag. */
	private const V2_TYPES = [9, 10, 11, 12, 13, 14, 17];

	/**
	 * @param array<string, mixed> $data A component object as Discord documents it, including its numeric type.
	 * @throws InvalidArgumentException When there's no integer type.
	 */
	public function __construct(
		private readonly array $data,
	) {
		if (!is_int($data['type'] ?? null)) {
			throw new InvalidArgumentException("A raw component needs Discord's integer 'type'.");
		}
	}

	public function jsonSerialize(): array
	{
		return $this->data;
	}

	public function componentCount(): int
	{
		return self::count($this->data);
	}

	public function requiresComponentsV2(): bool
	{
		return in_array($this->data['type'], self::V2_TYPES, true);
	}

	/**
	 * @param array<mixed> $component
	 */
	private static function count(array $component): int
	{
		$total = 1;
		if (is_array($component['accessory'] ?? null)) {
			$total += self::count($component['accessory']);
		}
		foreach (is_array($component['components'] ?? null) ? $component['components'] : [] as $child) {
			$total += is_array($child) ? self::count($child) : 0;
		}

		return $total;
	}
}

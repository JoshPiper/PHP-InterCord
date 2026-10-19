<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Color;
use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * A box around other components, with an optional coloured edge, like an embed's.
 */
final class Container implements TopLevelComponent
{
	/** @var list<ContainerComponent> */
	private readonly array $children;
	private ?Color $accentColor = null;
	private bool $spoiler = false;

	/**
	 * @param ContainerComponent ...$children Action rows, text, sections, galleries, files and separators.
	 * @throws InvalidArgumentException When there are no children.
	 */
	public function __construct(ContainerComponent ...$children)
	{
		if ($children === []) {
			throw new InvalidArgumentException('A container needs at least one component.');
		}

		$this->children = array_values($children);
	}

	/**
	 * A copy with a coloured edge.
	 *
	 * @param int|string|Color $color Any form RichEmbed::setColor() accepts.
	 * @throws InvalidArgumentException When the colour is invalid.
	 */
	public function withAccentColor(int|string|Color $color): self
	{
		$copy = clone $this;
		$copy->accentColor = Color::from($color);
		return $copy;
	}

	/**
	 * A copy that's blurred until clicked.
	 */
	public function asSpoiler(bool $spoiler = true): self
	{
		$copy = clone $this;
		$copy->spoiler = $spoiler;
		return $copy;
	}

	public function jsonSerialize(): array
	{
		return [
			'type' => 17,
			'components' => array_map(static fn(ContainerComponent $child): array => $child->jsonSerialize(), $this->children),
		]
			+ ($this->accentColor === null ? [] : ['accent_color' => $this->accentColor->jsonSerialize()])
			+ ($this->spoiler ? ['spoiler' => true] : []);
	}

	public function componentCount(): int
	{
		return 1 + array_sum(array_map(static fn(ContainerComponent $child): int => $child->componentCount(), $this->children));
	}

	public function requiresComponentsV2(): bool
	{
		return true;
	}
}

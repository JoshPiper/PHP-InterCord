<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

/**
 * Vertical space between components, optionally with a dividing line.
 */
final class Separator implements TopLevelComponent, ContainerComponent
{
	public function __construct(
		private readonly bool $divider = true,
		private readonly SeparatorSpacing $spacing = SeparatorSpacing::Small,
	) {}

	public function jsonSerialize(): array
	{
		return ['type' => 14, 'divider' => $this->divider, 'spacing' => $this->spacing->value];
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

<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use JsonSerializable;

/**
 * A message component. The narrower interfaces say where each kind may be placed.
 */
interface Component extends JsonSerializable
{
	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array;

	/**
	 * How many components this counts as towards Discord's limit of 40 per message, including any it holds.
	 *
	 * @internal
	 */
	public function componentCount(): int;

	/**
	 * Whether the message needs the IS_COMPONENTS_V2 flag to show this.
	 *
	 * @internal
	 */
	public function requiresComponentsV2(): bool;
}

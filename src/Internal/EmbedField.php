<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

use JsonSerializable;

/**
 * @internal
 */
final class EmbedField implements JsonSerializable
{
	public function __construct(
		private readonly string $name = '',
		private readonly string $value = '',
		private readonly bool $inline = false,
	) {
	}

	/**
	 * @return array<string, string|bool>
	 */
	public function jsonSerialize(): array
	{
		return Json::withoutEmpty([
			'name' => $this->name,
			'value' => $this->value,
			'inline' => $this->inline,
		]);
	}
}

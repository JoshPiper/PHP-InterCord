<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

use JsonSerializable;

/**
 * @internal
 */
final class EmbedFooter implements JsonSerializable
{
	public function __construct(
		private readonly string $text = '',
		private readonly string $iconUrl = '',
	) {}

	/**
	 * @return array<string, string>
	 */
	public function jsonSerialize(): array
	{
		return Json::withoutEmpty([
			'text' => $this->text,
			'icon_url' => $this->iconUrl,
		]);
	}
}

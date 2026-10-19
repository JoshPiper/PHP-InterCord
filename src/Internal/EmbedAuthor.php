<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

use JsonSerializable;

/**
 * @internal
 */
final class EmbedAuthor implements JsonSerializable
{
	/**
	 * @param string $name Author's name.
	 * @param string $url Link for the author's name.
	 * @param string $iconUrl Author's icon.
	 */
	public function __construct(
		private readonly string $name = '',
		private readonly string $url = '',
		private readonly string $iconUrl = '',
	) {
	}

	/**
	 * @return array<string, string>
	 */
	public function jsonSerialize(): array
	{
		return array_filter([
			'name' => $this->name,
			'url' => $this->url,
			'icon_url' => $this->iconUrl,
		]);
	}
}

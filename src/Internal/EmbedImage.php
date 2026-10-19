<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

use JsonSerializable;

/**
 * An embed's image or thumbnail. Only the URL is settable; Discord fills in the proxy URL and dimensions itself.
 *
 * @internal
 */
final class EmbedImage implements JsonSerializable
{
	public function __construct(
		private readonly string $url,
	) {
	}

	/**
	 * @return array<string, string>
	 */
	public function jsonSerialize(): array
	{
		return Json::withoutEmpty(['url' => $this->url]);
	}
}

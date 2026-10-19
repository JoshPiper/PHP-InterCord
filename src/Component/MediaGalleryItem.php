<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Attachment;
use Internet\InterCord\Exception\InvalidArgumentException;
use JsonSerializable;

/**
 * One image or video in a MediaGallery.
 */
final class MediaGalleryItem implements JsonSerializable
{
	/** @var array{url: string} */
	private readonly array $media;
	private readonly ?string $description;

	/**
	 * @param string|Attachment $media An http or https URL, or a file uploaded with the same message.
	 * @param ?string $description Alt text, up to 1024 characters.
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		string|Attachment $media,
		?string $description = null,
		private readonly bool $spoiler = false,
	) {
		$this->media = Media::item($media);
		$this->description = Media::description($description);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		return ['media' => $this->media]
			+ ($this->description === null ? [] : ['description' => $this->description])
			+ ($this->spoiler ? ['spoiler' => true] : []);
	}
}

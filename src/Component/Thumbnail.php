<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Attachment;
use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * A small image beside a Section's text.
 */
final class Thumbnail implements SectionAccessory
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

	public function jsonSerialize(): array
	{
		return ['type' => 11, 'media' => $this->media]
			+ ($this->description === null ? [] : ['description' => $this->description])
			+ ($this->spoiler ? ['spoiler' => true] : []);
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

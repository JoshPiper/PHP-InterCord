<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Attachment;

/**
 * Shows an uploaded file. In a Components V2 message, files only appear where a component shows them, so add the
 * same Attachment to the Payload with addAttachment() as well.
 */
final class File implements TopLevelComponent, ContainerComponent
{
	public function __construct(
		private readonly Attachment $file,
		private readonly bool $spoiler = false,
	) {}

	public function jsonSerialize(): array
	{
		return ['type' => 13, 'file' => ['url' => $this->file->url()]] + ($this->spoiler ? ['spoiler' => true] : []);
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

<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

use Internet\InterCord\Snowflake;

/**
 * An existing attachment an edit keeps. The filename, when known, lets components in the same edit go on
 * referencing it as attachment://filename.
 *
 * @internal
 */
final class KeptAttachment
{
	public function __construct(
		public readonly Snowflake $id,
		public readonly ?string $filename = null,
	) {}

	/**
	 * @return array{id: string, filename?: string}
	 */
	public function toArray(): array
	{
		return ['id' => $this->id->value] + ($this->filename === null ? [] : ['filename' => $this->filename]);
	}
}

<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

/**
 * @internal
 */
final class Json
{
	/**
	 * Drop the keys that were never set, so Discord applies its own defaults for them.
	 *
	 * Unlike a bare array_filter(), this keeps "0" and 0: they're real values that Discord would otherwise never see.
	 *
	 * @template T
	 * @param array<string, T> $data
	 * @return array<string, T>
	 */
	public static function withoutEmpty(array $data): array
	{
		return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== false && $value !== []);
	}
}

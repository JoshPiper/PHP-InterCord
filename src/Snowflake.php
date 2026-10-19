<?php

declare(strict_types=1);

namespace Internet\InterCord;

use DateTimeImmutable;
use Internet\InterCord\Exception\InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A Discord ID: an unsigned 64-bit integer, carried as a string because it doesn't fit PHP's signed int.
 */
final class Snowflake implements JsonSerializable, Stringable
{
	private const MAX = '18446744073709551615';
	private const DISCORD_EPOCH_MS = 1420070400000;
	private const TIMESTAMP_SHIFT = 4194304; // 2 ** 22: the low 22 bits are worker, process and increment.

	private function __construct(
		public readonly string $value,
	) {}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function from(self|string|int $id): self
	{
		if ($id instanceof self) {
			return $id;
		}

		$id = (string) $id;
		if (
			preg_match('/^[1-9][0-9]{0,19}$/', $id) !== 1
			|| (strlen($id) === strlen(self::MAX) && strcmp($id, self::MAX) > 0)
		) {
			throw new InvalidArgumentException("'{$id}' is not a Discord ID; expected a positive integer below 2^64.");
		}

		return new self($id);
	}

	/**
	 * When Discord created the object this ID belongs to.
	 */
	public function createdAt(): DateTimeImmutable
	{
		$milliseconds = self::divide($this->value, self::TIMESTAMP_SHIFT) + self::DISCORD_EPOCH_MS;
		$created = DateTimeImmutable::createFromFormat('U.v', sprintf('%d.%03d', intdiv($milliseconds, 1000), $milliseconds % 1000));
		assert($created !== false);

		return $created;
	}

	public function equals(self|string $other): bool
	{
		return $this->value === ($other instanceof self ? $other->value : $other);
	}

	public function __toString(): string
	{
		return $this->value;
	}

	public function jsonSerialize(): string
	{
		return $this->value;
	}

	/**
	 * Integer division of a decimal string, so IDs above PHP_INT_MAX still decode.
	 */
	private static function divide(string $dividend, int $divisor): int
	{
		$quotient = 0;
		$remainder = 0;
		foreach (str_split($dividend) as $digit) {
			$remainder = $remainder * 10 + (int) $digit;
			$quotient = $quotient * 10 + intdiv($remainder, $divisor);
			$remainder %= $divisor;
		}

		return $quotient;
	}
}

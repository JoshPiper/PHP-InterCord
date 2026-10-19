<?php

declare(strict_types=1);

namespace Internet\InterCord\Internal;

use Internet\InterCord\Exception\InvalidColorException;
use JsonSerializable;

/**
 * Converts the colour forms RichEmbed::setColor() accepts into Discord's decimal integer.
 */
final class Color implements JsonSerializable
{
	/**
	 * @param int $r Amount of red, from 0 to 255.
	 * @param int $g Amount of green, from 0 to 255.
	 * @param int $b Amount of blue, from 0 to 255.
	 * @throws InvalidColorException
	 */
	public function __construct(
		private readonly int $r,
		private readonly int $g,
		private readonly int $b,
	) {
		foreach (['Red' => $r, 'Green' => $g, 'Blue' => $b] as $channel => $value) {
			if ($value < 0 || $value > 255) {
				throw new InvalidColorException("{$channel} must be between 0 and 255, got {$value}.");
			}
		}
	}

	/**
	 * Create a colour from a hex triplet (#RRGGBB) or shorthand (#RGB).
	 */
	public static function fromHex(string $code): self
	{
		$code = str_replace('#', '', $code);
		$colors = array_map(static fn (string $part): int => (int) hexdec($part), str_split($code, intdiv(strlen($code), 3)));

		if (strlen($code) === 3) {
			$colors = array_map(static fn (int $color): int => $color + ($color * 16), $colors);
		}

		return new self(...$colors);
	}

	/**
	 * Create a colour from a decimal number.
	 *
	 * @throws InvalidColorException
	 */
	public static function fromDecimal(int $number): self
	{
		if ($number < 0 || $number > 0xFFFFFF) {
			throw new InvalidColorException("Colour must be between 0 and 16777215 (0xFFFFFF), got {$number}.");
		}

		return new self(($number & 0xFF0000) >> 16, ($number & 0x00FF00) >> 8, $number & 0x0000FF);
	}

	public function jsonSerialize(): int
	{
		return ($this->r << 16) + ($this->g << 8) + $this->b;
	}
}

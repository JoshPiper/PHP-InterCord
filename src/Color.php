<?php

declare(strict_types=1);

namespace Internet\InterCord;

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
	 * Read any of the forms RichEmbed::setColor() accepts: a decimal number, a hex code, or a Color.
	 * A string of only digits is read as decimal, so prefix hex codes with #.
	 *
	 * @throws InvalidColorException
	 */
	public static function from(int|string|self $color): self
	{
		if ($color instanceof self) {
			return $color;
		}

		return is_numeric($color) ? self::fromDecimal((int) $color) : self::fromHex($color);
	}

	/**
	 * Create a colour from a hex triplet (#RRGGBB) or shorthand (#RGB). The # is optional.
	 *
	 * @throws InvalidColorException
	 */
	public static function fromHex(string $code): self
	{
		if (preg_match('/^#?([[:xdigit:]]{3}|[[:xdigit:]]{6})$/', $code, $matches) !== 1) {
			throw new InvalidColorException("'{$code}' is not a hex colour; expected #RRGGBB or #RGB.");
		}

		$digits = $matches[1];
		if (strlen($digits) === 3) {
			$digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
		}

		return self::fromDecimal((int) hexdec($digits));
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

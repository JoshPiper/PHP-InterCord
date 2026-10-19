<?php

declare(strict_types=1);

namespace Internet\InterCord;

use DateTime;
use DateTimeInterface;
use Internet\InterCord\Exception\InvalidColorException;
use Internet\InterCord\Internal\Color;
use Internet\InterCord\Internal\EmbedAuthor;
use Internet\InterCord\Internal\EmbedField;
use Internet\InterCord\Internal\EmbedFooter;
use Internet\InterCord\Internal\EmbedImage;
use JsonSerializable;

/**
 * A chainable builder for Discord's rich embeds.
 */
final class RichEmbed implements JsonSerializable
{
	private ?string $title = null;
	private ?string $description = null;
	private ?string $url = null;
	private DateTimeInterface|false|null $timestamp = null;
	private ?Color $color = null;
	private ?EmbedFooter $footer = null;
	private ?EmbedImage $image = null;
	private ?EmbedImage $thumbnail = null;
	private ?EmbedAuthor $author = null;
	/** @var list<EmbedField> */
	private array $fields = [];

	/**
	 * Sets the title of the embed.
	 */
	public function setTitle(string $title): self
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * Sets the description.
	 */
	public function setDescription(string $description): self
	{
		$this->description = $description;
		return $this;
	}

	/**
	 * Sets the URL the title links to.
	 */
	public function setURL(string $url): self
	{
		$this->url = $url;
		return $this;
	}

	/**
	 * Sets the timestamp of the embed.
	 *
	 * @param int|string|DateTimeInterface $timestamp A Unix timestamp, a DateTimeInterface, or a string in $format.
	 * @param string $format A DateTime::createFromFormat() format, for string timestamps.
	 */
	public function setTimestamp(int|string|DateTimeInterface $timestamp, string $format = ''): self
	{
		if ($timestamp instanceof DateTimeInterface) {
			$this->timestamp = clone $timestamp;
		} elseif (is_numeric($timestamp)) {
			$this->timestamp = DateTime::createFromFormat('U', (string) $timestamp);
		} elseif ($format !== '') {
			$this->timestamp = DateTime::createFromFormat($format, $timestamp);
		}

		return $this;
	}

	/**
	 * Sets the colour of the embed's side bar.
	 *
	 * @param int|string|Color $color A decimal number, a hex code (#RRGGBB or #RGB), or a Color.
	 *                                A string of only digits is read as decimal, so prefix hex codes with #.
	 * @throws InvalidColorException
	 */
	public function setColor(int|string|Color $color): self
	{
		if (is_numeric($color)) {
			$this->color = Color::fromDecimal((int) $color);
		} elseif (is_string($color)) {
			$this->color = Color::fromHex($color);
		} else {
			$this->color = clone $color;
		}

		return $this;
	}

	/**
	 * Sets the embed footer.
	 */
	public function setFooter(string $text, string $iconUrl = ''): self
	{
		$this->footer = new EmbedFooter($text, $iconUrl);
		return $this;
	}

	/**
	 * Sets the author of the embed.
	 *
	 * @param string $name Author's name.
	 * @param string $url Link for the author's name.
	 * @param string $iconUrl Author's icon.
	 */
	public function setAuthor(string $name = '', string $url = '', string $iconUrl = ''): self
	{
		$this->author = new EmbedAuthor($name, $url, $iconUrl);
		return $this;
	}

	/**
	 * Sets the large image shown in the embed.
	 */
	public function setImage(string $url): self
	{
		$this->image = new EmbedImage($url);
		return $this;
	}

	/**
	 * Sets the thumbnail shown in the embed's corner.
	 */
	public function setThumbnail(string $url): self
	{
		$this->thumbnail = new EmbedImage($url);
		return $this;
	}

	/**
	 * Adds a field to this embed.
	 */
	public function addField(string $name, string $value, bool $inline = false): self
	{
		$this->fields[] = new EmbedField($name, $value, $inline);
		return $this;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		return array_filter([
			'title' => $this->title,
			'type' => 'rich',
			'description' => $this->description,
			'url' => $this->url,
			'timestamp' => $this->timestamp?->format(DATE_ATOM),
			'color' => $this->color?->jsonSerialize(),
			'footer' => $this->footer?->jsonSerialize(),
			'image' => $this->image?->jsonSerialize(),
			'thumbnail' => $this->thumbnail?->jsonSerialize(),
			'author' => $this->author?->jsonSerialize(),
			'fields' => array_map(static fn (EmbedField $field): array => $field->jsonSerialize(), $this->fields),
		]);
	}
}

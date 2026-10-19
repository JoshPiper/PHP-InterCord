<?php

declare(strict_types=1);

namespace Internet\InterCord\Component;

use Internet\InterCord\Exception\InvalidArgumentException;

/**
 * A button that opens a URL. The one button any webhook can send, since clicking it needs no application to answer.
 */
final class LinkButton implements ActionRowComponent, SectionAccessory
{
	private const MAX_URL = 512;
	private const MAX_LABEL = 80;

	/**
	 * @param string $url An http or https URL, up to 512 characters.
	 * @param ?string $label Up to 80 characters. Needed unless there's an emoji.
	 * @param ?string $emoji A Unicode emoji shown before the label.
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		private readonly string $url,
		private readonly ?string $label = null,
		private readonly ?string $emoji = null,
		private readonly bool $disabled = false,
	) {
		if (preg_match('~^https?://~i', $url) !== 1 || strlen($url) > self::MAX_URL) {
			throw new InvalidArgumentException('A link button needs an http or https URL of at most ' . self::MAX_URL . ' characters.');
		}
		if (($label === null || $label === '') && ($emoji === null || $emoji === '')) {
			throw new InvalidArgumentException('A link button needs a label, an emoji, or both.');
		}
		if ($label !== null && preg_match('/^.{0,' . self::MAX_LABEL . '}$/us', $label) !== 1) {
			throw new InvalidArgumentException('A button label must be valid UTF-8 of at most ' . self::MAX_LABEL . ' characters.');
		}
	}

	public function jsonSerialize(): array
	{
		return ['type' => 2, 'style' => 5, 'url' => $this->url]
			+ ($this->label === null || $this->label === '' ? [] : ['label' => $this->label])
			+ ($this->emoji === null || $this->emoji === '' ? [] : ['emoji' => ['name' => $this->emoji]])
			+ ($this->disabled ? ['disabled' => true] : []);
	}

	public function componentCount(): int
	{
		return 1;
	}

	public function requiresComponentsV2(): bool
	{
		return false;
	}
}

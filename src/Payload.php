<?php

declare(strict_types=1);

namespace Internet\InterCord;

use Internet\InterCord\Internal\Json;
use JsonSerializable;

/**
 * A message being sent to Discord. Build one directly to send the same message to several webhooks, or to set
 * options the shorthand content forms can't, such as TTS.
 */
final class Payload implements JsonSerializable
{
	private ?string $content = null;
	private ?string $username = null;
	private ?string $avatar = null;
	private ?bool $tts = null;
	/** @var list<RichEmbed> */
	private array $embeds = [];

	/**
	 * Build a payload from a string, an embed, or an array of strings and embeds. A Payload is returned as is.
	 * Within an array, embeds are added in order and the last string becomes the content.
	 *
	 * @param array<int, string|int|float|RichEmbed>|string|int|float|RichEmbed|Payload $content
	 */
	public static function from(array|string|int|float|RichEmbed|Payload $content, string $username = '', string $avatar = ''): self
	{
		if ($content instanceof self) {
			return $content;
		}

		$payload = (new self())
			->setUsername($username)
			->setAvatar($avatar);

		foreach (is_array($content) ? $content : [$content] as $part) {
			if ($part instanceof RichEmbed) {
				$payload->addEmbed($part);
			} else {
				$payload->setContent((string) $part);
			}
		}

		return $payload;
	}

	/**
	 * Sets the message text.
	 */
	public function setContent(string $content): self
	{
		$this->content = $content;
		return $this;
	}

	/**
	 * Overrides the webhook's display name for this message.
	 */
	public function setUsername(string $username): self
	{
		$this->username = $username;
		return $this;
	}

	/**
	 * Overrides the webhook's avatar for this message.
	 */
	public function setAvatar(string $avatar): self
	{
		$this->avatar = $avatar;
		return $this;
	}

	/**
	 * Sets whether the message is read out with text-to-speech.
	 */
	public function setTTS(bool $tts): self
	{
		$this->tts = $tts;
		return $this;
	}

	/**
	 * Adds an embed to the message.
	 */
	public function addEmbed(RichEmbed $embed): self
	{
		$this->embeds[] = $embed;
		return $this;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		return Json::withoutEmpty([
			'content' => $this->content,
			'username' => $this->username,
			'avatar_url' => $this->avatar,
			'tts' => $this->tts,
			'embeds' => array_map(static fn(RichEmbed $embed): array => $embed->jsonSerialize(), $this->embeds),
		]);
	}
}

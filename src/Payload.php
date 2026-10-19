<?php

declare(strict_types=1);

namespace Internet\InterCord;

use Internet\InterCord\Exception\InvalidArgumentException;
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
	private ?string $threadName = null;
	/** @var list<Snowflake> */
	private array $appliedTags = [];

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
	 * Start a new post with this message, when the webhook belongs to a forum or media channel.
	 *
	 * @throws InvalidArgumentException When the name is empty or over Discord's 100 characters.
	 */
	public function setThreadName(string $name): self
	{
		if (preg_match('/^.{1,100}$/us', $name) !== 1) {
			throw new InvalidArgumentException('A thread name must be 1 to 100 characters of valid UTF-8.');
		}

		$this->threadName = $name;
		return $this;
	}

	/**
	 * Tag the new forum or media post. Only applies alongside setThreadName().
	 *
	 * @throws InvalidArgumentException When a tag isn't a valid ID.
	 */
	public function setAppliedTags(Snowflake|string ...$tags): self
	{
		$this->appliedTags = array_values(array_map(Snowflake::from(...), $tags));
		return $this;
	}

	/**
	 * Whether sending this payload starts a new forum or media post.
	 *
	 * @internal
	 */
	public function startsThread(): bool
	{
		return $this->threadName !== null;
	}

	/**
	 * The body for sending this payload as a new message.
	 *
	 * @internal
	 * @param bool $intoThread Whether it's being sent into an existing thread.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When the payload's options contradict each other.
	 */
	public function toExecuteBody(bool $intoThread): array
	{
		if ($intoThread && $this->threadName !== null) {
			throw new InvalidArgumentException('A message can go into an existing thread or start a new post, not both.');
		}
		if ($this->appliedTags !== [] && $this->threadName === null) {
			throw new InvalidArgumentException('Tags can only be applied when starting a new post with setThreadName().');
		}

		return $this->jsonSerialize();
	}

	/**
	 * The body for editing an existing message with this payload.
	 *
	 * @internal
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When the payload sets something Discord can't change after sending.
	 */
	public function toEditBody(): array
	{
		foreach (['username' => $this->username, 'avatar' => $this->avatar] as $field => $value) {
			if ($value !== null && $value !== '') {
				throw new InvalidArgumentException("A message's {$field} can't be changed by editing it.");
			}
		}
		if ($this->tts !== null) {
			throw new InvalidArgumentException("A message's TTS setting can't be changed by editing it.");
		}
		if ($this->threadName !== null || $this->appliedTags !== []) {
			throw new InvalidArgumentException('A post can only be started, and tagged, when the message is first sent.');
		}

		return $this->jsonSerialize();
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
			'thread_name' => $this->threadName,
			'applied_tags' => array_map(static fn(Snowflake $tag): string => $tag->value, $this->appliedTags),
		]);
	}
}

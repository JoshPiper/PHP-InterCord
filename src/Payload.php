<?php

declare(strict_types=1);

namespace Internet\InterCord;

use Internet\InterCord\Component\TopLevelComponent;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Internal\Json;
use Internet\InterCord\Internal\KeptAttachment;
use JsonSerializable;

/**
 * A message being sent to Discord. Build one directly to send the same message to several webhooks, or to set
 * options the shorthand content forms can't, such as TTS.
 */
final class Payload implements JsonSerializable
{
	private const SUPPRESS_EMBEDS = 1 << 2;
	private const SUPPRESS_NOTIFICATIONS = 1 << 12;
	private const IS_COMPONENTS_V2 = 1 << 15;
	private const MAX_ATTACHMENTS = 10;
	private const MAX_COMPONENTS = 40;
	private const MAX_ACTION_ROWS = 5;

	private ?string $content = null;
	private ?string $username = null;
	private ?string $avatar = null;
	private ?bool $tts = null;
	/** @var list<RichEmbed> */
	private array $embeds = [];
	/** @var list<TopLevelComponent> */
	private array $components = [];
	/** @var list<Attachment> */
	private array $attachments = [];
	/** @var ?list<KeptAttachment> Existing attachments an edit keeps; null when the edit doesn't say. */
	private ?array $keptAttachments = null;
	private ?AllowedMentions $allowedMentions = null;
	private ?bool $suppressEmbeds = null;
	private ?bool $suppressNotifications = null;
	/** @var array<string, PayloadField> Fields an edit should remove, keyed by case name. */
	private array $cleared = [];
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
		unset($this->cleared[PayloadField::Content->name]);
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
		unset($this->cleared[PayloadField::Embeds->name]);
		return $this;
	}

	/**
	 * Add a component, such as an ActionRow of LinkButtons.
	 *
	 * A webhook that no application owns can only send components that need nobody to answer them: link buttons
	 * and display components. RawComponent sends anything else, for webhooks an application owns.
	 */
	public function addComponent(TopLevelComponent $component): self
	{
		$this->components[] = $component;
		unset($this->cleared[PayloadField::Components->name]);
		return $this;
	}

	/**
	 * Whether Discord has to be told to read this payload's components.
	 *
	 * @internal
	 */
	public function hasComponents(): bool
	{
		return $this->components !== [];
	}

	/**
	 * Upload a file with the message, up to 10. Reference it from an embed or component with $attachment->url().
	 *
	 * When editing, new files are added alongside the message's existing ones. To remove existing files, say which
	 * to keep with keepAttachments(), or clear(PayloadField::Attachments) to remove them all.
	 *
	 * @throws InvalidArgumentException When this would be the 11th file.
	 */
	public function addAttachment(Attachment $attachment): self
	{
		if (count($this->attachments) >= self::MAX_ATTACHMENTS) {
			throw new InvalidArgumentException('Discord allows at most ' . self::MAX_ATTACHMENTS . ' files per message.');
		}

		$this->attachments[] = $attachment;
		return $this;
	}

	/**
	 * When editing, keep only these of the message's existing attachments; the rest are removed.
	 *
	 * Editing through a Message keeps all of its attachments by default, so this is only needed to remove some, or
	 * to describe new files when editing by ID.
	 *
	 * @throws InvalidArgumentException When an ID isn't valid.
	 */
	public function keepAttachments(Snowflake|string ...$ids): self
	{
		$this->keptAttachments = array_values(array_map(static fn(Snowflake|string $id): KeptAttachment => new KeptAttachment(Snowflake::from($id)), $ids));
		return $this;
	}

	/**
	 * The files to upload with this payload.
	 *
	 * @internal
	 * @return list<Attachment>
	 */
	public function files(): array
	{
		return $this->attachments;
	}

	/**
	 * Remove these parts of the message when editing it. Without this, an edit leaves anything it doesn't set alone.
	 *
	 * Setting a field again afterwards takes it back off the list; clearing it after setting it discards what was
	 * set. Only applies to edits: sending a cleared payload as a new message throws.
	 */
	public function clear(PayloadField ...$fields): self
	{
		foreach ($fields as $field) {
			match ($field) {
				PayloadField::Content => $this->content = null,
				PayloadField::Embeds => $this->embeds = [],
				PayloadField::Components => $this->components = [],
				PayloadField::Attachments => $this->keptAttachments = [],
			};
			$this->cleared[$field->name] = $field;
		}

		return $this;
	}

	/**
	 * Hide the link previews Discord would otherwise generate for URLs in the content. On an edit, false brings
	 * them back.
	 */
	public function suppressEmbeds(bool $suppress = true): self
	{
		$this->suppressEmbeds = $suppress;
		return $this;
	}

	/**
	 * Send without push or desktop notifications ("@silent"). Only applies when the message is first sent.
	 */
	public function suppressNotifications(bool $suppress = true): self
	{
		$this->suppressNotifications = $suppress;
		return $this;
	}

	/**
	 * Limit which mentions in the content notify anyone. Discord's default, when this isn't set, is that all do.
	 */
	public function setAllowedMentions(AllowedMentions $allowedMentions): self
	{
		$this->allowedMentions = $allowedMentions;
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
		if ($this->cleared !== []) {
			throw new InvalidArgumentException('clear() only applies when editing a message; a new message has nothing to clear.');
		}
		if ($this->keptAttachments !== null) {
			throw new InvalidArgumentException('keepAttachments() only applies when editing a message.');
		}
		$this->checkComponents();
		if ($this->isComponentsV2() && (($this->content ?? '') !== '' || $this->embeds !== [])) {
			throw new InvalidArgumentException(
				'A message with Components V2 components (text displays, sections, containers and the like) can only hold components; use a TextDisplay instead of content or embeds.',
			);
		}

		return $this->jsonSerialize() + $this->attachmentList([]);
	}

	/**
	 * The body for editing an existing message with this payload.
	 *
	 * @internal
	 * @param ?list<KeptAttachment> $existing Every file the message holds, when the caller knows them.
	 * @param bool $messageIsComponentsV2 Whether the message being edited already uses Components V2.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When the payload sets something Discord can't change after sending.
	 */
	public function toEditBody(?array $existing = null, bool $messageIsComponentsV2 = false): array
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
		if ($this->suppressNotifications !== null) {
			throw new InvalidArgumentException('Notifications can only be suppressed when the message is first sent.');
		}
		$this->checkComponents();

		$body = $this->jsonSerialize();
		// On an edit, an explicit false has to be sent so Discord turns link previews back on.
		unset($body['flags']);
		if ($this->isComponentsV2()) {
			if (($this->content ?? '') !== '' || $this->embeds !== []) {
				throw new InvalidArgumentException('A message with Components V2 components can only hold components; use a TextDisplay instead of content or embeds.');
			}
			// Discord only switches an existing message to Components V2 once its content and embeds are gone.
			$body = ['content' => null, 'embeds' => []] + $body;
			$body['flags'] = self::IS_COMPONENTS_V2 | ($this->suppressEmbeds === true ? self::SUPPRESS_EMBEDS : 0);
		} elseif ($this->suppressEmbeds !== null) {
			// Flags replace the message's flags, and Discord refuses any that drop Components V2 from a V2 message.
			$body['flags'] = ($this->suppressEmbeds ? self::SUPPRESS_EMBEDS : 0)
				| ($messageIsComponentsV2 ? self::IS_COMPONENTS_V2 : 0);
		}
		foreach ($this->cleared as $field) {
			match ($field) {
				PayloadField::Content => $body['content'] = null,
				PayloadField::Embeds => $body['embeds'] = [],
				PayloadField::Components => $body['components'] = [],
				PayloadField::Attachments => null,
			};
		}

		// Discord's attachments list on an edit is everything that should remain, so it's only sent when what to
		// keep is known: said explicitly, or the message's own attachments when new files are being added.
		$keep = $this->keptAttachments ?? ($this->attachments !== [] ? $existing : null);
		if ($keep !== null) {
			return $body + $this->attachmentList($keep);
		}
		foreach ($this->attachments as $attachment) {
			if ($attachment->description !== null) {
				throw new InvalidArgumentException(
					'Describing a new file on an edit needs the full attachment list: edit through the Message, or say what to keep with keepAttachments().',
				);
			}
		}

		return $body;
	}

	/**
	 * @throws InvalidArgumentException When the components break Discord's limits.
	 */
	private function checkComponents(): void
	{
		$total = array_sum(array_map(static fn(TopLevelComponent $component): int => $component->componentCount(), $this->components));
		if ($total > self::MAX_COMPONENTS) {
			throw new InvalidArgumentException('Discord allows at most ' . self::MAX_COMPONENTS . " components per message, counting nested ones; this has {$total}.");
		}
		if (!$this->isComponentsV2() && count($this->components) > self::MAX_ACTION_ROWS) {
			throw new InvalidArgumentException('Discord allows at most ' . self::MAX_ACTION_ROWS . ' rows of components per message.');
		}
	}

	private function isComponentsV2(): bool
	{
		foreach ($this->components as $component) {
			if ($component->requiresComponentsV2()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<KeptAttachment> $keep
	 * @return array{attachments?: list<array{id: string|int, filename?: string, description?: string}>}
	 */
	private function attachmentList(array $keep): array
	{
		$list = array_map(static fn(KeptAttachment $kept): array => $kept->toArray(), $keep);
		foreach ($this->attachments as $index => $attachment) {
			$list[] = ['id' => $index, 'filename' => $attachment->filename]
				+ ($attachment->description === null ? [] : ['description' => $attachment->description]);
		}

		return $list === [] && $this->keptAttachments === null ? [] : ['attachments' => $list];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array
	{
		$flags = ($this->suppressEmbeds === true ? self::SUPPRESS_EMBEDS : 0)
			| ($this->suppressNotifications === true ? self::SUPPRESS_NOTIFICATIONS : 0)
			| ($this->isComponentsV2() ? self::IS_COMPONENTS_V2 : 0);

		return Json::withoutEmpty([
			'content' => $this->content,
			'username' => $this->username,
			'avatar_url' => $this->avatar,
			'tts' => $this->tts,
			'embeds' => array_map(static fn(RichEmbed $embed): array => $embed->jsonSerialize(), $this->embeds),
			'components' => array_map(static fn(TopLevelComponent $component): array => $component->jsonSerialize(), $this->components),
			'allowed_mentions' => $this->allowedMentions?->jsonSerialize(),
			'flags' => $flags === 0 ? null : $flags,
			'thread_name' => $this->threadName,
			'applied_tags' => array_map(static fn(Snowflake $tag): string => $tag->value, $this->appliedTags),
		]);
	}
}

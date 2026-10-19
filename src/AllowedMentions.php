<?php

declare(strict_types=1);

namespace Internet\InterCord;

use Internet\InterCord\Exception\InvalidArgumentException;
use JsonSerializable;

/**
 * Which mentions in a message actually notify anyone. Without one, Discord's default applies: every mention in the
 * content pings. Set one whenever content comes from users, so nobody can slip @everyone into it.
 */
final class AllowedMentions implements JsonSerializable
{
	private const MAX_IDS = 100;

	/** @var bool|list<Snowflake> */
	private readonly bool|array $users;
	/** @var bool|list<Snowflake> */
	private readonly bool|array $roles;

	/**
	 * @param bool|list<Snowflake|string> $users true for any user mention, or only these user IDs.
	 * @param bool|list<Snowflake|string> $roles true for any role mention, or only these role IDs.
	 * @param bool $everyone Whether @everyone and @here notify.
	 * @throws InvalidArgumentException When a list has more than 100 IDs or an invalid one.
	 */
	public function __construct(
		bool|array $users = false,
		bool|array $roles = false,
		private readonly bool $everyone = false,
	) {
		$this->users = self::ids($users, 'users');
		$this->roles = self::ids($roles, 'roles');
	}

	/**
	 * No mention notifies anyone.
	 */
	public static function none(): self
	{
		return new self();
	}

	/**
	 * Every mention notifies, as Discord does by default.
	 */
	public static function all(): self
	{
		return new self(true, true, true);
	}

	/**
	 * @return array{parse: list<string>, users?: list<string>, roles?: list<string>}
	 */
	public function jsonSerialize(): array
	{
		$data = ['parse' => array_keys(array_filter(
			['users' => $this->users === true, 'roles' => $this->roles === true, 'everyone' => $this->everyone],
			static fn(bool $parsed): bool => $parsed,
		))];

		foreach (['users' => $this->users, 'roles' => $this->roles] as $key => $ids) {
			if (is_array($ids) && $ids !== []) {
				$data[$key] = array_map(static fn(Snowflake $id): string => $id->value, $ids);
			}
		}

		return $data;
	}

	/**
	 * @param bool|list<Snowflake|string> $ids
	 * @return bool|list<Snowflake>
	 */
	private static function ids(bool|array $ids, string $kind): bool|array
	{
		if (is_bool($ids)) {
			return $ids;
		}
		if (count($ids) > self::MAX_IDS) {
			throw new InvalidArgumentException('Discord allows at most ' . self::MAX_IDS . " {$kind} in allowed mentions.");
		}

		return array_map(Snowflake::from(...), $ids);
	}
}

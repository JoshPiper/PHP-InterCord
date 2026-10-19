# Contributing to InterCord

## Purpose

This document exists to:
- Explain how commit messages drive versioning and releases.
- Set out what CI gates a PR on.
- Save you rediscovering the local lint/analyse/test setup by trial and error.

---

## Conventional Commits

release-please reads commit messages to drive versioning, changelogs, and releases.

Format: `<type>[optional scope]: <description>`

Types:

| Type | Effect |
|---|---|
| `fix:` | Patch release (`x.y.Z`) |
| `feat:` | Minor release (`x.Y.0`) |
| `feat!:` or a `BREAKING CHANGE:` footer | Major release (`X.0.0`) |
| `chore:`, `build:`, `ci:`, `docs:`, `test:`, `refactor:`, `style:` | No release triggered on their own |

Examples:

```
fix: wait for retry_after in seconds, not microseconds

feat: add Webhook::editMessage()

feat!: stop extending GuzzleHttp\Client

BREAKING CHANGE: Webhook is now built with Webhook::fromUrl() or
Webhook::fromCredentials() instead of its constructor.
```

If a PR mixes several logical changes, split the commits by type rather than reaching for whichever tag sounds biggest. release-please aggregates every commit into the changelog regardless, so there's no upside to lumping it all under one `feat:` — and smaller commits review better besides.

---

## What CI actually gates

`ci.yml` runs, on every PR:
- `composer validate --strict` and `composer audit`
- PHP-CS-Fixer in check mode
- PHPStan at `max`, with strict rules
- The test suite on PHP 8.3, 8.4 and 8.5, each against the lowest and the highest dependency versions `composer.json` allows

All of it needs to pass. There's no merging around a red check. Codecov reports coverage on the PR too, but doesn't block it.

---

## What you don't need to do

- Bump a version. There's no `version` field in `composer.json`, and there shouldn't be — Packagist reads versions from tags.
- Touch `CHANGELOG.md`.
- Create a tag.

release-please does all of it off your commit messages once your PR's on `main`, by maintaining a release PR. A maintainer merges that when it's time to ship; merging it tags the release and publishes it to Packagist.

---

## Local setup

PHP 8.3 or later and Composer, nothing else.

```bash
composer update          # no lock file is committed; this resolves the newest allowed versions
composer test            # PHPUnit
composer analyse         # PHPStan
composer lint            # PHP-CS-Fixer, check only
composer fix             # PHP-CS-Fixer, rewriting files
```

To check the oldest versions CI tests against as well:

```bash
composer update --prefer-lowest --prefer-stable
composer test
```

Coverage needs pcov or Xdebug: `composer test:coverage` writes `coverage.xml`.

The tests never talk to Discord. `tests/Support/FakeDiscord.php` builds a `Webhook` on Guzzle's `MockHandler` with canned responses and records every request it sends; `RecordingSleeper` stands in for real rate limit waits. A new test should go through those rather than a real webhook.

---

## Code style

- PER Coding Style 3.0, indented with tabs. `composer fix` applies it; CI won't fix it for you.
- No comments explaining *what* the code does — the names already do that. A comment earns its place only when it captures a non-obvious *why*: a hidden constraint, a workaround, a decision that would otherwise look like a mistake to a reviewer.
- Anything that handles the webhook URL or token keeps it out of exception messages, chained exceptions and debug output. Tests check this; extend them when adding a new error path.
- Every exception the library throws implements `Exception\InterCordException`.

---

## Reporting a security issue

Don't put it in a PR or an issue. See [SECURITY.md](SECURITY.md).

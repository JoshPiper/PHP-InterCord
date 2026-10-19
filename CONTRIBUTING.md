# Contributing to InterCord

## Purpose

This document exists to:
- Explain how commit messages drive versioning and releases.
- Say what you don't need to do any more.

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

## What you don't need to do

- Bump a version. There's no `version` field in `composer.json`, and there shouldn't be — Packagist reads versions from tags.
- Touch `CHANGELOG.md`.
- Create a tag.

release-please does all of it off your commit messages once your PR's on `main`, by maintaining a release PR. A maintainer merges that when it's time to ship; merging it tags the release and publishes it to Packagist.

---

## Reporting a security issue

Don't put it in a PR or an issue. See [SECURITY.md](SECURITY.md).

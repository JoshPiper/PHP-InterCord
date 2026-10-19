# Security Policy

## Purpose

This document exists to:
- Say where to send a vulnerability report.
- Define what's in scope and what isn't.
- Explain how to verify that what you installed is what was released.
- Set honest expectations for response time.

---

## Supported versions

Latest release only. No maintained LTS branch — a fix ships on top of current `main` in the next release.

---

## Reporting a vulnerability

**Don't open a public issue for this.**

**[Report a vulnerability](https://github.com/JoshPiper/PHP-InterCord/security/advisories/new)** — GitHub's private advisory flow, visible only to me and you until it's resolved. No GitHub? Open a normal issue asking for another contact, without report details in it.

Include, if you can:
- The InterCord version (`composer show internet/intercord`) and PHP version.
- Steps to reproduce, or a minimal repro.
- The impact — a leaked webhook token, a request sent somewhere it shouldn't be, whatever it is.

Worth noting up front: a webhook URL *is* a credential. Anyone holding it can post as, edit, or delete that webhook's messages. InterCord leaking it — in an exception message, a log line, a `var_dump` — is in scope. You committing it to a public repository isn't; rotate it in Discord's channel settings.

---

## Scope

In scope:
- The library (`src/`).
- The release pipeline (`.github/workflows/`) — a tag or Packagist version under this project's name that doesn't match the reviewed source.
- A vulnerable dependency with an exploitation path through InterCord.

Out of scope:
- Discord itself, Guzzle, PHP, or a dependency vulnerability with no exploitation path through this library. Report upstream. Flag it here too if unsure — widening a constraint is a one-line fix on this end regardless.

---

## Verifying a release

Packagist doesn't host build artifacts. `composer install` fetches GitHub's archive of the release tag, and your `composer.lock` pins the exact commit that tag pointed at. So the integrity of an install rests on two things:
- **The commit in your lock file.** Check `source.reference` for `internet/intercord` in `composer.lock` against the [release's tag](https://github.com/JoshPiper/PHP-InterCord/tags).
- **Immutable releases.** This repository has GitHub's immutable releases enabled, so once a release is published its tag can't be moved or deleted. A commit you've locked can't be swapped out from under you by retagging.

What isn't provided, and why:
- **Build provenance attestations.** There's no artifact for them to attest — Composer installs the tag archive, not anything this pipeline builds, and doesn't verify attestations. Packagist has [Trusted Publishing and Sigstore attestations on its roadmap](https://blog.packagist.com/an-update-on-composer-packagist-supply-chain-security/); this project will adopt them once they ship.
- **An SBOM.** A library doesn't fix its dependency tree — your `composer.lock` does. Generate an SBOM for your own project (for example with the CycloneDX Composer plugin) and it'll include InterCord at the version you actually resolved.

---

## What to expect

No SLA — spare-time project. Credible reports get triaged fast. Give a fair window before public disclosure; credit goes in the release notes if you want it.

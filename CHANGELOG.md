# Changelog

All notable changes to this project will be documented in this file. From 3.0.0 onwards it is maintained by release-please from conventional commits; see [CONTRIBUTING.md](CONTRIBUTING.md).

## [3.0.0](https://github.com/JoshPiper/PHP-InterCord/compare/2.1.1...3.0.0) (2026-10-20)


### ⚠ BREAKING CHANGES

* Internet\InterCord\Internal\Payload is now Internet\InterCord\Payload, and Internet\InterCord\Internal\Color is now Internet\InterCord\Color.
* RichEmbed::setVideo() and RichEmbed::setProvider() are removed. setImage() and setThumbnail() take only a URL. setFooter() and setAuthor() no longer take a proxy icon URL. RichEmbed and Color are final.
* PHP 8.3 and Guzzle 7 are required. Construct webhooks with Webhook::fromUrl() or Webhook::fromCredentials(); Webhook no longer extends GuzzleHttp\Client. QueuedWebhook takes a Webhook in its constructor. execute() returns void and no longer takes $await. Webhook::createPayload() is now Payload::from(). Failures throw InterCord exceptions instead of Guzzle's ClientException.

### Features

* add executeAndWait() returning the created Message ([1f4e10c](https://github.com/JoshPiper/PHP-InterCord/commit/1f4e10c9286062ddcc537d0e05dae06230bc0303))
* add link buttons in action rows, plus raw components ([2475984](https://github.com/JoshPiper/PHP-InterCord/commit/2475984ef9c0ae5655a149091d77d1e022ff455e))
* add Snowflake for Discord IDs ([079a6de](https://github.com/JoshPiper/PHP-InterCord/commit/079a6de0c70219d48bdef1d7c681e574ba5499c8))
* add Webhook::queued() to start a queue from a webhook ([71e369c](https://github.com/JoshPiper/PHP-InterCord/commit/71e369c5f85b510cea6651b55ec24f776a92e93f))
* build Components V2 messages ([8f2ddc5](https://github.com/JoshPiper/PHP-InterCord/commit/8f2ddc59c7309faa09323643b926c5b8b0cc819e))
* clear message content or embeds when editing ([4d1caef](https://github.com/JoshPiper/PHP-InterCord/commit/4d1caefc0928dca8d32b9016d926b1caa86af50c))
* control which mentions notify with AllowedMentions ([f2fe621](https://github.com/JoshPiper/PHP-InterCord/commit/f2fe621aaaed59fad76807fd2e65f310ca2a3baa))
* drop embed fields webhooks can't set ([38fe387](https://github.com/JoshPiper/PHP-InterCord/commit/38fe387e697fcc1f261c911d93a971f1a96d89cd))
* get, edit and delete messages the webhook sent ([687c3e4](https://github.com/JoshPiper/PHP-InterCord/commit/687c3e4b111dc0d4184e9aca74dc703963b56a41))
* let a Message edit and delete itself ([1186385](https://github.com/JoshPiper/PHP-InterCord/commit/1186385db2eaf60ad957a0d03c2b2b4fd841c36b))
* move Payload and Color out of the Internal namespace ([bbd9406](https://github.com/JoshPiper/PHP-InterCord/commit/bbd9406af803ca8e8454b4f2f01452ec69ca6194))
* post into threads and manage thread messages ([f98a4e5](https://github.com/JoshPiper/PHP-InterCord/commit/f98a4e58da95b667ee77aa21db018ffb6f2d2eee))
* rebuild Webhook around a PSR-18 transport ([6dde6b4](https://github.com/JoshPiper/PHP-InterCord/commit/6dde6b4c232b7fc53958e00a38e97f9c81981cf1))
* start and tag forum and media channel posts ([f010462](https://github.com/JoshPiper/PHP-InterCord/commit/f0104625d41c74d0a12572cb90933231df53266f))
* suppress link previews and notifications ([94e73df](https://github.com/JoshPiper/PHP-InterCord/commit/94e73dfb953120a2cf4199e7c78a300bd18fa9e6))
* upload files with messages and edits ([e14f5da](https://github.com/JoshPiper/PHP-InterCord/commit/e14f5da79b815c4aa99ed9412137a43aa142c0b5))


### Bug Fixes

* keep files used by embeds and components when editing a Message ([a62eaee](https://github.com/JoshPiper/PHP-InterCord/commit/a62eaeeaba87e3cd2db80ab8639b51758e4f376d))
* keep the Components V2 flag when editing a V2 message ([6490541](https://github.com/JoshPiper/PHP-InterCord/commit/6490541066df9a9821c542f4e8ff86844c0cf073))
* keep the webhook token out of exception messages and var_dump output ([6dde6b4](https://github.com/JoshPiper/PHP-InterCord/commit/6dde6b4c232b7fc53958e00a38e97f9c81981cf1))
* only wait for a rate limit reset once the bucket is empty ([6dde6b4](https://github.com/JoshPiper/PHP-InterCord/commit/6dde6b4c232b7fc53958e00a38e97f9c81981cf1))
* refuse uploads on an edit that would delete existing files ([05531f9](https://github.com/JoshPiper/PHP-InterCord/commit/05531f93e30d97b9c60927734e242fe83086733c))
* reject colours outside 0x000000-0xFFFFFF ([07f70b5](https://github.com/JoshPiper/PHP-InterCord/commit/07f70b52bc2f9153fe97d229c8005cda3cb42497))
* reject malformed hex colour codes ([a3b605a](https://github.com/JoshPiper/PHP-InterCord/commit/a3b605af624a38c70be60642133c71fcc83b9905))
* reject timestamps that can't be parsed ([e368eaa](https://github.com/JoshPiper/PHP-InterCord/commit/e368eaa9e0ecbfe64da607cfe61ba11bb7b627c4))
* send an empty request body as a JSON object ([8025355](https://github.com/JoshPiper/PHP-InterCord/commit/8025355ab874b29c37a66c9dc83e5992442222cf))
* send webhooks to discord.com/api/v10 instead of discordapp.com ([6dde6b4](https://github.com/JoshPiper/PHP-InterCord/commit/6dde6b4c232b7fc53958e00a38e97f9c81981cf1))
* stop dropping "0" from messages and embeds ([52384cc](https://github.com/JoshPiper/PHP-InterCord/commit/52384cc5e3e909193c46398060b24d59abcd240e))
* stop failing when Discord omits rate limit headers ([6dde6b4](https://github.com/JoshPiper/PHP-InterCord/commit/6dde6b4c232b7fc53958e00a38e97f9c81981cf1))
* wait for Discord's retry_after in seconds, not microseconds ([6dde6b4](https://github.com/JoshPiper/PHP-InterCord/commit/6dde6b4c232b7fc53958e00a38e97f9c81981cf1))

## [2.1.1 and earlier](https://github.com/JoshPiper/PHP-InterCord/tags)

These versions predate this changelog. Their history is in the git tags.

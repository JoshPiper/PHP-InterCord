# InterCord

[![CI](https://github.com/JoshPiper/PHP-InterCord/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/JoshPiper/PHP-InterCord/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/JoshPiper/PHP-InterCord/branch/main/graph/badge.svg)](https://codecov.io/gh/JoshPiper/PHP-InterCord)
[![Packagist version](https://img.shields.io/packagist/v/internet/intercord)](https://packagist.org/packages/internet/intercord)
[![PHP version](https://img.shields.io/packagist/dependency-v/internet/intercord/php)](https://packagist.org/packages/internet/intercord)
[![Downloads](https://img.shields.io/packagist/dt/internet/intercord)](https://packagist.org/packages/internet/intercord)
[![License: MIT](https://img.shields.io/packagist/l/internet/intercord)](LICENSE)

Discord webhooks for PHP: messages, rich embeds, files, components, threads and forum posts, with rate limits handled for you.

Upgrading from 2.x? See [UPGRADE-3.0.md](UPGRADE-3.0.md).

## Installation

```bash
composer require internet/intercord
```

Requires PHP 8.3 or later. Guzzle is the default HTTP client; any [PSR-18](https://www.php-fig.org/psr/psr-18/) client can be used instead.

## Quick start

```php
use Internet\InterCord\RichEmbed;
use Internet\InterCord\Webhook;

$webhook = Webhook::fromUrl($url);

$webhook->execute('Deploy finished.');

$webhook->execute([
    'Deploy finished.',
    (new RichEmbed())
        ->setTitle('api v4.2.0')
        ->setDescription('Rolled out to all regions.')
        ->setColor('#2ECC71')
        ->addField('Duration', '3m 12s', inline: true)
        ->addField('Errors', '0', inline: true)
        ->setTimestamp(time()),
], username: 'Deploy Bot');
```

`$url` is the webhook URL from the channel's *Integrations → Webhooks* settings. **Treat it as a password**: anyone holding it can post, edit and delete as that webhook. Keep it in an environment variable or secret store, not in source control.

`Webhook::fromCredentials($id, $token)` builds the same thing from the two halves of the URL.

## Messages

`execute()` sends and returns nothing. `executeAndWait()` waits for Discord to confirm, and returns the `Message` it created:

```php
$message = $webhook->executeAndWait('Build #812 started…');

$message->id;          // Snowflake
$message->content;     // 'Build #812 started…'
$message->timestamp;   // DateTimeImmutable
$message->embeds;      // as Discord returned them, proxy URLs and dimensions included
$message->raw;         // the full response body

$message = $message->edit('Build #812 passed.');   // returns the edited Message
$message->delete();
```

The same is available by ID, for messages you only stored the ID of:

```php
$webhook->getMessage($id);
$webhook->editMessage($id, 'Build #812 passed.');
$webhook->deleteMessage($id);
```

Edits change only what you set. To remove something, clear it:

```php
use Internet\InterCord\Payload;
use Internet\InterCord\PayloadField;

$message->edit((new Payload())->setContent('Embeds removed.')->clear(PayloadField::Embeds));
```

`PayloadField` covers `Content`, `Embeds`, `Components` and `Attachments`.

A webhook's username, avatar and text-to-speech setting can't be changed after a message is sent, so setting them on an edit throws.

### Payloads

Strings, embeds and arrays of both are shorthand for a `Payload`. Build one directly for everything else, or to send the same message more than once:

```php
use Internet\InterCord\AllowedMentions;
use Internet\InterCord\Payload;

$payload = (new Payload())
    ->setContent("New ticket from {$name}")
    ->setUsername('Helpdesk')
    ->setAvatar('https://example.com/helpdesk.png')
    ->setAllowedMentions(AllowedMentions::none())
    ->suppressEmbeds()
    ->suppressNotifications();

$webhook->execute($payload);
```

**Set `AllowedMentions` whenever the content includes user input.** Without it, Discord's default applies and every mention in the text pings, `@everyone` included. `AllowedMentions::none()` silences them all. `new AllowedMentions(users: [$userId], roles: true, everyone: false)` lets through only what you list.

`suppressEmbeds()` hides link previews; on an edit, `suppressEmbeds(false)` brings them back. `suppressNotifications()` sends silently, and only applies to new messages.

### Embeds

```php
use Internet\InterCord\RichEmbed;

$embed = (new RichEmbed())
    ->setTitle('Incident resolved')
    ->setURL('https://status.example.com/incidents/42')
    ->setDescription('Elevated error rates on checkout.')
    ->setColor(0xE74C3C)                // or '#E74C3C', '#E7C', or new Color(231, 76, 60)
    ->setAuthor('Status Page', 'https://status.example.com', 'https://example.com/icon.png')
    ->setThumbnail('https://example.com/thumb.png')
    ->setImage('https://example.com/graph.png')
    ->addField('Started', '14:02 UTC', inline: true)
    ->addField('Resolved', '14:37 UTC', inline: true)
    ->setFooter('On-call: Platform')
    ->setTimestamp('2026-10-19 14:37', 'Y-m-d H:i');
```

Up to 10 embeds can go in one message. Invalid colours and timestamps that can't be parsed throw when they're set. A string of only digits is read as a decimal colour, so prefix hex codes with `#`.

## Threads and forum posts

Pass a thread ID to post into an existing thread. A `Message` remembers its thread, so `edit()` and `delete()` go to the right place:

```php
$message = $webhook->executeAndWait('Nightly results', threadId: $threadId);
$message->edit('Nightly results: all green');
```

When the webhook belongs to a forum or media channel, start a new post with a thread name and optional tags:

```php
$post = $webhook->executeAndWait(
    (new Payload())
        ->setContent('This week: 14 deploys, 0 rollbacks.')
        ->setThreadName('Weekly report: 2026-W42')
        ->setAppliedTags($reportsTagId),
);

$post->threadId;   // the new post
```

## Files

```php
use Internet\InterCord\Attachment;

$chart = Attachment::fromPath('/tmp/latency.png', description: 'p99 latency, last 24 hours');

$webhook->execute(
    (new Payload())
        ->addEmbed((new RichEmbed())->setTitle('Latency')->setImage($chart->url()))
        ->addAttachment($chart)
        ->addAttachment(Attachment::fromString($csv, 'latency.csv'))
        ->addAttachment(Attachment::fromString($log, 'debug.log', spoiler: true)),
);
```

Up to 10 files per message. `$attachment->url()` is the `attachment://` reference embeds and components use to show an uploaded file. Files from a path are read when the request is sent, so queued messages don't hold them in memory.

When **editing**, Discord deletes every existing file that an upload doesn't list, including files that embeds and components show:

- Editing **through a `Message`** keeps all of its files, including those embeds and components show, and adds the new ones.
- `->keepAttachments($id, …)` keeps only those existing files.
- `->clear(PayloadField::Attachments)` removes all existing files; files added in the same edit replace them.
- Editing **by ID** and uploading files without either of the above throws, since Discord would delete every existing file.

Edits that don't upload anything leave a message's files alone.

## Components

### Link buttons

Any webhook can send link buttons, up to five per row:

```php
use Internet\InterCord\Component\ActionRow;
use Internet\InterCord\Component\LinkButton;

$webhook->execute(
    (new Payload())
        ->setContent('Release 4.2.0 is out.')
        ->addComponent(new ActionRow(
            new LinkButton('https://example.com/changelog', 'Changelog'),
            new LinkButton('https://example.com/download', 'Download', emoji: '⬇️'),
        )),
);
```

Interactive components (buttons that send events, select menus) only work on webhooks an application owns, and only that application receives the clicks. InterCord has no builders for them; send them as a `RawComponent` with Discord's component array.

### Components V2

Discord's newer layout components replace content and embeds entirely:

```php
use Internet\InterCord\Component\Container;
use Internet\InterCord\Component\File;
use Internet\InterCord\Component\MediaGallery;
use Internet\InterCord\Component\MediaGalleryItem;
use Internet\InterCord\Component\Section;
use Internet\InterCord\Component\Separator;
use Internet\InterCord\Component\TextDisplay;
use Internet\InterCord\Component\Thumbnail;

$report = Attachment::fromPath('/tmp/report.pdf');

$webhook->execute(
    (new Payload())
        ->addComponent((new Container(
            new TextDisplay("## Weekly report\nAll services healthy."),
            new Section(
                new Thumbnail('https://example.com/uptime.png', 'Uptime chart'),
                new TextDisplay('**Uptime** 99.98%'),
                new TextDisplay('**Deploys** 14'),
            ),
            new Separator(),
            new MediaGallery(
                new MediaGalleryItem('https://example.com/latency.png', 'Latency'),
                new MediaGalleryItem('https://example.com/errors.png', 'Errors'),
            ),
            new File($report),
            new ActionRow(new LinkButton('https://example.com/dashboard', 'Dashboard')),
        ))->withAccentColor('#5865F2'))
        ->addAttachment($report),
);
```

InterCord sets the Components V2 flag for you. Content and embeds can't be combined with these components, so mixing them throws before anything is sent. Editing a message into Components V2 clears its content and embeds, which Discord requires. A message holds at most 40 components, counting nested ones.

In a Components V2 message, uploaded files only appear where a `File`, `Thumbnail` or `MediaGallery` shows them; a file nothing references is accepted but not shown. Once a message uses Components V2, Discord won't let an edit turn it off: editing through the `Message` keeps the flag for you, while an edit by ID that changes flags (such as `suppressEmbeds()`) is rejected by Discord.

## Queues

`QueuedWebhook` sends payloads in order:

```php
$queue = $webhook->queued();

$queue->append('First');
$queue->append('Second');
$queue->prepend('Before everything');

$queue->run();      // send them all
$queue->run(1);     // or just the next one
$queue->deliver('Urgent');   // jump the queue and send now
```

A payload that's still rate limited after every retry, or that gets no response, goes back on the front of the queue before the exception is rethrown, so `run()` resumes where it stopped. A payload Discord rejects is dropped, since sending it again would fail the same way.

## Errors

Every exception InterCord throws implements `Internet\InterCord\Exception\InterCordException`:

| Exception | When |
|---|---|
| `DiscordApiException` | Discord answered with an error. Has `$status`, `$discordCode` and `$errors`. |
| `RateLimitedException` | Still rate limited after every retry. Extends `DiscordApiException`; has `$retryAfter` and `$global`. |
| `TransportException` | No response at all: DNS, connection, TLS or timeout. |
| `UnexpectedResponseException` | Discord reported success, but the response wasn't a valid message. |
| `InvalidArgumentException` | Something was rejected before sending. Subclasses: `InvalidWebhookUrlException`, `InvalidColorException`, `InvalidTimestampException`. |
| `DetachedMessageException` | `edit()` or `delete()` on a `Message` restored from serialised data. |

No exception message contains the webhook token. Client exceptions aren't chained, because their messages include the full URL.

## Rate limits

InterCord waits out Discord's rate limits itself. It waits before a request when the previous one emptied the bucket. On a `429`, it waits Discord's `retry_after` and tries again, up to `maxRetries` times (3 by default), before throwing `RateLimitedException`.

## Configuration

```php
$webhook = Webhook::fromUrl(
    $url,
    client: $psr18Client,    // any PSR-18 client; defaults to Guzzle with 10s connect / 30s total timeouts
    sleeper: $sleeper,       // an Internet\InterCord\Sleeper, to wait inside an event loop instead of usleep()
    maxRetries: 5,
);
```

## Security

- The webhook token never appears in exception messages, `var_dump()` or `print_r()` output, or a serialised `Message`.
- `Webhook` refuses to be serialised. Store the URL securely and rebuild with `Webhook::fromUrl()`.
- Report vulnerabilities privately; see [SECURITY.md](SECURITY.md), which also covers verifying a release.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Releases are automated from conventional commits.

## License

[MIT](LICENSE)

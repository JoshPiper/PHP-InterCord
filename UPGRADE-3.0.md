# Upgrading from 2.x to 3.0

3.0 rebuilds InterCord around an injectable HTTP client, fixes a run of long-standing bugs, and adds wait mode, message editing, threads, files and components. Most upgrades only touch where webhooks are created and where errors are caught.

## Requirements

| | 2.x | 3.0 |
|---|---|---|
| PHP | 7.2+ | **8.3+** |
| Guzzle | 6.5 | **7.9+** |
| Development branch | `dev-master` | **`dev-main`** (aliased as `3.x-dev`) |

## Creating webhooks

`Webhook` no longer extends `GuzzleHttp\Client`, and is created with named constructors:

```php
// 2.x
$webhook = new Webhook($url);
$webhook = new Webhook($id, $token);

// 3.0
$webhook = Webhook::fromUrl($url);
$webhook = Webhook::fromCredentials($id, $token);
```

URLs are now validated. `discord.com` and `discordapp.com`, with or without `canary.`/`ptb.` and an `/api/v{n}` segment, are accepted. Anything else throws `InvalidWebhookUrlException`, including a URL with a query string after the token. Requests always go to `discord.com/api/v10`.

`$webhook->id` is a `Snowflake`; use `(string) $webhook->id` or `$webhook->id->value` for the string.

Anything that relied on `Webhook` being a Guzzle client (calling `get()`/`post()` on it, or `instanceof Client` checks) needs changing. To customise HTTP behaviour, pass your own PSR-18 client:

```php
$webhook = Webhook::fromUrl($url, client: new \GuzzleHttp\Client(['proxy' => $proxy]));
```

## Sending

`execute()` no longer waits for or returns Discord's response. Use `executeAndWait()` for that; it returns a `Message` instead of a `stdClass`:

```php
// 2.x
$response = $webhook->execute('hello', '', '', true);
$response->id;

// 3.0
$message = $webhook->executeAndWait('hello');
$message->id;   // Snowflake
```

## Queues

`QueuedWebhook` wraps a `Webhook` instead of extending it:

```php
// 2.x
$queue = new QueuedWebhook($url);

// 3.0
$queue = Webhook::fromUrl($url)->queued();
// or: new QueuedWebhook(Webhook::fromUrl($url));
```

`QueuedWebhook` only exposes queue operations now, and no longer handles rate limits itself: the webhook does, for every request. A payload that's still rate limited after every retry goes back on the front of the queue, and the exception is rethrown.

## Moved and renamed

| 2.x | 3.0 |
|---|---|
| `Internet\InterCord\Internal\Payload` | `Internet\InterCord\Payload` |
| `Internet\InterCord\Internal\Color` | `Internet\InterCord\Color` |
| `Webhook::createPayload($content, $username, $avatar)` | `Payload::from($content, $username, $avatar)` |

`RichEmbed`, `Color`, `Payload`, `Webhook` and `QueuedWebhook` are `final`.

## Embeds

Discord fills in proxy URLs and image dimensions itself, and only sets `video` and `provider` on embeds it builds from links. Values sent for any of them were always ignored, so the parameters are gone:

| 2.x | 3.0 |
|---|---|
| `setImage($url, $proxy, $width, $height)` | `setImage($url)` |
| `setThumbnail($url, $proxy, $width, $height)` | `setThumbnail($url)` |
| `setFooter($text, $icon, $proxyIcon)` | `setFooter($text, $icon)` |
| `setAuthor($name, $url, $icon, $proxyIcon)` | `setAuthor($name, $url, $icon)` |
| `setVideo(...)` | removed |
| `setProvider(...)` | removed |

Discord's values for all of these are still available on a returned `Message`, in `$message->embeds`.

## Stricter validation

Input that 2.x quietly mangled now throws when it's set:

- **Colours** outside `0x000000`–`0xFFFFFF`, and hex codes that aren't `#RGB` or `#RRGGBB`, throw `InvalidColorException`. Before, `-1` became white, `0x1000000` became no colour, `#00` raised a `ValueError`, and non-hex characters became `0`.
- **Timestamps** that don't match their format, impossible dates such as 30 February, and non-numeric strings with no format throw `InvalidTimestampException`. Before, they caused a fatal error when the embed was sent, or were silently ignored.

Timestamps are serialised with a colon in the offset (`+00:00`), as before; only tests that expected `+0000` need updating.

## Errors

Guzzle's exceptions no longer escape. Catch InterCord's instead:

```php
// 2.x
try {
    $webhook->execute($content);
} catch (\GuzzleHttp\Exception\ClientException $ex) {
    $status = $ex->getCode();
}

// 3.0
use Internet\InterCord\Exception\DiscordApiException;
use Internet\InterCord\Exception\InterCordException;

try {
    $webhook->execute($content);
} catch (DiscordApiException $ex) {
    $status = $ex->status;
    $code = $ex->discordCode;   // Discord's JSON error code
} catch (InterCordException $ex) {
    // anything else from InterCord: no response, invalid input, ...
}
```

Rate limits no longer surface as a 429 `ClientException` for you to handle; they're retried, and only throw `RateLimitedException` once retries run out. See the [error table in the README](README.md#errors).

## Behaviour fixes you may notice

- A message or embed field of `"0"` is sent; it used to be dropped, so Discord rejected the message.
- Rate limits are waited out correctly: in seconds, not microseconds, and only once the bucket is empty, rather than slowing every send once half of it was used.
- A response without rate limit headers no longer causes an error.
- The default HTTP client times out: 10 seconds to connect, 30 in total. 2.x could block forever.
- The webhook token no longer appears in exception messages or debug output.

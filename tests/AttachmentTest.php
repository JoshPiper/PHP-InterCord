<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Attachment;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Payload;
use Internet\InterCord\PayloadField;
use Internet\InterCord\RichEmbed;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
	private const PIXEL = __DIR__ . '/Fixtures/pixel.png';
	private const EXISTING_A = '1900000000000000009';
	private const EXISTING_B = '1900000000000000010';

	public function testUploadingFilesSendsMultipart(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute(
			(new Payload())
				->setContent('two files')
				->addAttachment(Attachment::fromString('a,b' . "\n" . '1,2', 'report.csv', 'Weekly numbers'))
				->addAttachment(Attachment::fromPath(self::PIXEL)),
		);

		$parts = $discord->parts();
		self::assertSame(['payload_json', 'files[0]', 'files[1]'], array_keys($parts));
		self::assertSame(
			[
				'content' => 'two files',
				'attachments' => [
					['id' => 0, 'filename' => 'report.csv', 'description' => 'Weekly numbers'],
					['id' => 1, 'filename' => 'pixel.png'],
				],
			],
			$discord->sentJson(),
		);
		self::assertSame('report.csv', $parts['files[0]']['filename']);
		self::assertSame("a,b\n1,2", $parts['files[0]']['body']);
		self::assertSame('pixel.png', $parts['files[1]']['filename']);
		self::assertSame('image/png', $parts['files[1]']['type']);
		self::assertSame((string) file_get_contents(self::PIXEL), $parts['files[1]']['body']);
	}

	public function testWithoutFilesTheRequestStaysJson(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute('hello');

		self::assertSame('application/json', $discord->request()->getHeaderLine('Content-Type'));
	}

	public function testFilesAreSentAgainWhenRetried(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(429, ['retry_after' => 0]), FakeDiscord::noContent());

		$discord->webhook()->execute((new Payload())->addAttachment(Attachment::fromPath(self::PIXEL)));

		self::assertSame((string) file_get_contents(self::PIXEL), $discord->parts(1)['files[0]']['body']);
	}

	public function testEmbedsCanShowAnUploadedImage(): void
	{
		$image = Attachment::fromPath(self::PIXEL, 'chart.png');

		self::assertSame('attachment://chart.png', $image->url());
		self::assertSame(
			['url' => 'attachment://chart.png'],
			(new RichEmbed())->setImage($image->url())->jsonSerialize()['image'],
		);
	}

	public function testSpoilersArePrefixed(): void
	{
		$attachment = Attachment::fromString('secret', 'plot.txt', spoiler: true);

		self::assertSame('SPOILER_plot.txt', $attachment->filename);
		self::assertSame('attachment://SPOILER_plot.txt', $attachment->url());
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function badFilenames(): iterable
	{
		yield 'empty' => [''];
		yield 'path' => ['../etc/passwd'];
		yield 'backslash' => ['a\\b.txt'];
		yield 'control character' => ["a\nb.txt"];
		yield 'quote' => ['a"b.txt'];
	}

	#[DataProvider('badFilenames')]
	public function testBadFilenamesAreRejected(string $filename): void
	{
		$this->expectException(InvalidArgumentException::class);
		Attachment::fromString('x', $filename);
	}

	public function testAMissingFileIsRejectedUpFront(): void
	{
		$this->expectException(InvalidArgumentException::class);
		Attachment::fromPath(__DIR__ . '/Fixtures/missing.png');
	}

	public function testAnOverlongDescriptionIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		Attachment::fromString('x', 'x.txt', str_repeat('a', 1025));
	}

	public function testMoreThanTenFilesAreRejected(): void
	{
		$payload = new Payload();
		for ($i = 0; $i < 10; $i++) {
			$payload->addAttachment(Attachment::fromString('x', "{$i}.txt"));
		}

		$this->expectException(InvalidArgumentException::class);
		$payload->addAttachment(Attachment::fromString('x', '10.txt'));
	}

	/**
	 * Live, an edit that uploads files without listing what to keep deletes every existing file, despite Discord's
	 * documentation saying new files are appended.
	 */
	public function testUploadingByIdWithoutSayingWhatToKeepIsRefused(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		try {
			$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->addAttachment(Attachment::fromString('x', 'new.txt')));
		} finally {
			self::assertSame(0, $discord->requestCount());
		}
	}

	public function testUploadingByIdAfterClearingReplacesTheFiles(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(
			FakeDiscord::MESSAGE_ID,
			(new Payload())->clear(PayloadField::Attachments)->addAttachment(Attachment::fromString('x', 'new.txt')),
		);

		self::assertSame(['attachments' => [['id' => 0, 'filename' => 'new.txt']]], $discord->sentJson());
	}

	public function testKeepingNamedAttachments(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(
			FakeDiscord::MESSAGE_ID,
			(new Payload())->keepAttachments(self::EXISTING_A)->addAttachment(Attachment::fromString('x', 'new.txt', 'described')),
		);

		self::assertSame(
			['attachments' => [['id' => self::EXISTING_A], ['id' => 0, 'filename' => 'new.txt', 'description' => 'described']]],
			$discord->sentJson(),
		);
	}

	public function testKeepingOnlySomeAttachmentsRemovesTheRest(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->keepAttachments(self::EXISTING_B));

		self::assertSame('application/json', $discord->request()->getHeaderLine('Content-Type'));
		self::assertSame(['attachments' => [['id' => self::EXISTING_B]]], $discord->sentJson());
	}

	public function testClearingAttachmentsRemovesThemAll(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->clear(PayloadField::Attachments));

		self::assertSame(['attachments' => []], $discord->sentJson());
	}

	public function testEditingAMessageKeepsItsAttachmentsAutomatically(): void
	{
		$existing = [
			['id' => self::EXISTING_A, 'filename' => 'a.png', 'size' => 1, 'url' => 'https://cdn.discordapp.com/a.png'],
			['id' => self::EXISTING_B, 'filename' => 'b.png', 'size' => 1, 'url' => 'https://cdn.discordapp.com/b.png'],
		];
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message(['attachments' => $existing])),
			FakeDiscord::json(200, FakeDiscord::message()),
		);
		$message = $discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID);

		$message->edit((new Payload())->addAttachment(Attachment::fromString('x', 'new.txt', 'described')));

		self::assertSame(
			['attachments' => [
				['id' => self::EXISTING_A, 'filename' => 'a.png'],
				['id' => self::EXISTING_B, 'filename' => 'b.png'],
				['id' => 0, 'filename' => 'new.txt', 'description' => 'described'],
			]],
			$discord->sentJson(1),
		);
	}

	/**
	 * Live, Discord leaves files an embed or component uses out of 'attachments', and an edit that doesn't list
	 * them deletes them. A Message has to find them where Discord does put them.
	 */
	public function testAMessageFindsFilesUsedByEmbedsAndComponents(): void
	{
		$channel = FakeDiscord::CHANNEL_ID;
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message([
			'attachments' => [['id' => self::EXISTING_A, 'filename' => 'data.csv']],
			'embeds' => [[
				'type' => 'rich',
				'image' => ['url' => "https://cdn.discordapp.com/attachments/{$channel}/1900000000000000011/chart%20one.png?ex=1&is=2&hm=3", 'width' => 1, 'height' => 1],
				'thumbnail' => ['url' => 'https://cdn.discordapp.com/attachments/1111111111111111111/1900000000000000012/elsewhere.png'],
				'footer' => ['text' => 'f', 'icon_url' => "https://media.discordapp.net/attachments/{$channel}/1900000000000000013/icon.png"],
			]],
			'components' => [[
				'type' => 17,
				'components' => [
					['type' => 13, 'file' => ['id' => '9', 'url' => "https://cdn.discordapp.com/attachments/{$channel}/1900000000000000014/report.csv", 'attachment_id' => '1900000000000000014']],
					['type' => 12, 'items' => [['media' => ['url' => 'https://example.com/external.png']]]],
				],
			]],
		])));

		$message = $discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID);

		self::assertSame(
			[self::EXISTING_A, '1900000000000000011', '1900000000000000013', '1900000000000000014'],
			array_map(strval(...), $message->attachmentIds()),
		);
	}

	public function testEditingThroughAMessageKeepsFilesUsedByEmbedsAndComponents(): void
	{
		$channel = FakeDiscord::CHANNEL_ID;
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message([
				'attachments' => [['id' => self::EXISTING_A, 'filename' => 'data.csv']],
				'embeds' => [['type' => 'rich', 'image' => ['url' => "https://cdn.discordapp.com/attachments/{$channel}/1900000000000000011/chart%20one.png?ex=1"]]],
				'components' => [['type' => 13, 'file' => ['url' => "https://cdn.discordapp.com/attachments/{$channel}/1900000000000000014/report.csv", 'attachment_id' => '1900000000000000014']]],
			])),
			FakeDiscord::json(200, FakeDiscord::message()),
		);
		$message = $discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID);

		$message->edit((new Payload())->addAttachment(Attachment::fromString('x', 'new.txt')));

		self::assertSame(
			['attachments' => [
				['id' => self::EXISTING_A, 'filename' => 'data.csv'],
				['id' => '1900000000000000011', 'filename' => 'chart one.png'],
				['id' => '1900000000000000014', 'filename' => 'report.csv'],
				['id' => 0, 'filename' => 'new.txt'],
			]],
			$discord->sentJson(1),
		);
	}

	public function testAnExplicitKeepListOverridesTheMessagesAttachments(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message(['attachments' => [['id' => self::EXISTING_A, 'filename' => 'a.png']]])),
			FakeDiscord::json(200, FakeDiscord::message()),
		);

		$discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID)->edit((new Payload())->clear(PayloadField::Attachments));

		self::assertSame(['attachments' => []], $discord->sentJson(1));
	}

	public function testANewMessageCannotKeepAttachments(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		$discord->webhook()->execute((new Payload())->setContent('x')->keepAttachments(self::EXISTING_A));
	}
}

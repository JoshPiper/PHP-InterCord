<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use DateTimeImmutable;
use DateTimeInterface;
use Internet\InterCord\RichEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RichEmbedTest extends TestCase
{
	public function testAnEmptyEmbedIsJustItsType(): void
	{
		self::assertSame(['type' => 'rich'], (new RichEmbed())->jsonSerialize());
	}

	public function testTextElements(): void
	{
		$embed = (new RichEmbed())
			->setTitle('title')
			->setDescription('description')
			->setURL('https://example.com');

		self::assertSame(
			['title' => 'title', 'type' => 'rich', 'description' => 'description', 'url' => 'https://example.com'],
			$embed->jsonSerialize(),
		);
	}

	public function testAuthor(): void
	{
		$embed = (new RichEmbed())->setAuthor('name', 'https://example.com', 'https://example.com/icon.png');

		self::assertSame(
			['name' => 'name', 'url' => 'https://example.com', 'icon_url' => 'https://example.com/icon.png'],
			$embed->jsonSerialize()['author'],
		);
	}

	public function testAuthorLeavesOutUnsetParts(): void
	{
		self::assertSame(['name' => 'name'], (new RichEmbed())->setAuthor('name')->jsonSerialize()['author']);
	}

	public function testFooter(): void
	{
		$embed = (new RichEmbed())->setFooter('footer', 'https://example.com/icon.png');

		self::assertSame(['text' => 'footer', 'icon_url' => 'https://example.com/icon.png'], $embed->jsonSerialize()['footer']);
	}

	public function testImageAndThumbnail(): void
	{
		$data = (new RichEmbed())
			->setImage('https://example.com/image.png')
			->setThumbnail('https://example.com/thumb.png')
			->jsonSerialize();

		self::assertSame(['url' => 'https://example.com/image.png'], $data['image']);
		self::assertSame(['url' => 'https://example.com/thumb.png'], $data['thumbnail']);
	}

	public function testFields(): void
	{
		$data = (new RichEmbed())
			->addField('first', 'one')
			->addField('second', 'two', true)
			->jsonSerialize();

		self::assertSame(
			[['name' => 'first', 'value' => 'one'], ['name' => 'second', 'value' => 'two', 'inline' => true]],
			$data['fields'],
		);
	}

	/**
	 * @return iterable<string, array{int|string|DateTimeInterface, string, string}>
	 */
	public static function timestamps(): iterable
	{
		yield 'unix epoch' => [0, '', '1970-01-01T00:00:00+00:00'];
		yield 'unix seconds' => [86400, '', '1970-01-02T00:00:00+00:00'];
		yield 'numeric string' => ['1', '', '1970-01-01T00:00:01+00:00'];
		yield 'DateTimeInterface' => [new DateTimeImmutable('2009-02-15T12:30:00+01:00'), '', '2009-02-15T12:30:00+01:00'];
		yield 'custom format' => ['15-Feb-2009 00:00 +0000', 'j-M-Y H:i O', '2009-02-15T00:00:00+00:00'];
	}

	#[DataProvider('timestamps')]
	public function testTimestamps(int|string|DateTimeInterface $timestamp, string $format, string $expected): void
	{
		$embed = (new RichEmbed())->setTimestamp($timestamp, $format);

		self::assertSame($expected, $embed->jsonSerialize()['timestamp']);
	}

	public function testDateTimeIsCopied(): void
	{
		$time = new \DateTime('@0');
		$embed = (new RichEmbed())->setTimestamp($time);
		$time->modify('+1 day');

		self::assertSame('1970-01-01T00:00:00+00:00', $embed->jsonSerialize()['timestamp']);
	}
}

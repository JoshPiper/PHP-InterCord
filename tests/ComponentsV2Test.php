<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Attachment;
use Internet\InterCord\Component\ActionRow;
use Internet\InterCord\Component\Container;
use Internet\InterCord\Component\File;
use Internet\InterCord\Component\LinkButton;
use Internet\InterCord\Component\MediaGallery;
use Internet\InterCord\Component\MediaGalleryItem;
use Internet\InterCord\Component\Section;
use Internet\InterCord\Component\Separator;
use Internet\InterCord\Component\SeparatorSpacing;
use Internet\InterCord\Component\TextDisplay;
use Internet\InterCord\Component\Thumbnail;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Payload;
use Internet\InterCord\RichEmbed;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ComponentsV2Test extends TestCase
{
	private const IS_COMPONENTS_V2 = 1 << 15;

	public function testAComponentsV2Message(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());
		$report = Attachment::fromString('a,b', 'report.csv');
		$chart = Attachment::fromString('png', 'chart.png');

		$discord->webhook()->execute(
			(new Payload())
				->addComponent((new Container(
					new TextDisplay('# Weekly report'),
					new Section(new Thumbnail($chart, 'Chart'), new TextDisplay('Up 4%'), new TextDisplay('since last week')),
					new Separator(spacing: SeparatorSpacing::Large),
					new MediaGallery(new MediaGalleryItem('https://example.com/a.png'), new MediaGalleryItem($chart, spoiler: true)),
					new File($report),
					new ActionRow(new LinkButton('https://example.com', 'Open')),
				))->withAccentColor('#5865F2'))
				->addComponent(new Section(new LinkButton('https://example.com/more', 'More'), new TextDisplay('Details')))
				->addAttachment($report)
				->addAttachment($chart),
		);

		self::assertSame(FakeDiscord::ENDPOINT . '?with_components=true', (string) $discord->request()->getUri());
		self::assertSame(
			[
				'components' => [
					[
						'type' => 17,
						'components' => [
							['type' => 10, 'content' => '# Weekly report'],
							[
								'type' => 9,
								'components' => [['type' => 10, 'content' => 'Up 4%'], ['type' => 10, 'content' => 'since last week']],
								'accessory' => ['type' => 11, 'media' => ['url' => 'attachment://chart.png'], 'description' => 'Chart'],
							],
							['type' => 14, 'divider' => true, 'spacing' => 2],
							['type' => 12, 'items' => [
								['media' => ['url' => 'https://example.com/a.png']],
								['media' => ['url' => 'attachment://chart.png'], 'spoiler' => true],
							]],
							['type' => 13, 'file' => ['url' => 'attachment://report.csv']],
							['type' => 1, 'components' => [['type' => 2, 'style' => 5, 'url' => 'https://example.com', 'label' => 'Open']]],
						],
						'accent_color' => 0x5865F2,
					],
					[
						'type' => 9,
						'components' => [['type' => 10, 'content' => 'Details']],
						'accessory' => ['type' => 2, 'style' => 5, 'url' => 'https://example.com/more', 'label' => 'More'],
					],
				],
				'flags' => self::IS_COMPONENTS_V2,
				'attachments' => [['id' => 0, 'filename' => 'report.csv'], ['id' => 1, 'filename' => 'chart.png']],
			],
			$discord->sentJson(),
		);
	}

	public function testTheFlagCombinesWithOthers(): void
	{
		$body = (new Payload())->addComponent(new TextDisplay('hi'))->suppressNotifications()->toExecuteBody(false);

		self::assertSame(self::IS_COMPONENTS_V2 | (1 << 12), $body['flags'] ?? null);
	}

	public function testClassicComponentsDontSetTheFlag(): void
	{
		$body = (new Payload())->setContent('x')->addComponent(new ActionRow(new LinkButton('https://example.com', 'x')))->toExecuteBody(false);

		self::assertArrayNotHasKey('flags', $body);
	}

	/**
	 * @return iterable<string, array{Payload}>
	 */
	public static function mixedWithV2(): iterable
	{
		yield 'content' => [(new Payload())->setContent('x')->addComponent(new TextDisplay('hi'))];
		yield 'embeds' => [(new Payload())->addEmbed((new RichEmbed())->setTitle('x'))->addComponent(new TextDisplay('hi'))];
	}

	#[DataProvider('mixedWithV2')]
	public function testV2CannotBeMixedWithContentOrEmbeds(Payload $payload): void
	{
		$this->expectException(InvalidArgumentException::class);
		$payload->toExecuteBody(false);
	}

	public function testEditingIntoV2ClearsContentAndEmbeds(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->addComponent(new TextDisplay('now V2')));

		self::assertSame(
			['content' => null, 'embeds' => [], 'components' => [['type' => 10, 'content' => 'now V2']], 'flags' => self::IS_COMPONENTS_V2],
			$discord->body(),
		);
	}

	public function testAMessageKnowsWhetherItIsComponentsV2(): void
	{
		$discord = new FakeDiscord(
			FakeDiscord::json(200, FakeDiscord::message(['flags' => self::IS_COMPONENTS_V2 | 4])),
			FakeDiscord::json(200, FakeDiscord::message(['flags' => 4])),
		);
		$webhook = $discord->webhook();

		self::assertTrue($webhook->getMessage(FakeDiscord::MESSAGE_ID)->isComponentsV2());
		self::assertFalse($webhook->getMessage(FakeDiscord::MESSAGE_ID)->isComponentsV2());
	}

	public function testEditingAV2MessageKeepsItsFlagWhenOnlyFlagsChange(): void
	{
		// Live, Discord rejects flags without IS_COMPONENTS_V2 on a V2 message: MESSAGE_CANNOT_REMOVE_COMPONENTS_V2_FLAG.
		$v2 = FakeDiscord::json(200, FakeDiscord::message(['content' => '', 'flags' => self::IS_COMPONENTS_V2]));
		$discord = new FakeDiscord($v2, $v2, $v2);
		$message = $discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID);

		$message->edit((new Payload())->suppressEmbeds());
		$message->edit((new Payload())->suppressEmbeds(false));

		self::assertSame(['flags' => self::IS_COMPONENTS_V2 | 4], $discord->body(1));
		self::assertSame(['flags' => self::IS_COMPONENTS_V2], $discord->body(2));
	}

	public function testEditingAClassicMessageSendsOnlyItsOwnFlags(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()), FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->getMessage(FakeDiscord::MESSAGE_ID)->edit((new Payload())->suppressEmbeds());

		self::assertSame(['flags' => 4], $discord->body(1));
	}

	public function testV2AllowsMoreThanFiveTopLevelComponents(): void
	{
		$payload = new Payload();
		for ($i = 0; $i < 8; $i++) {
			$payload->addComponent(new TextDisplay((string) $i));
		}

		self::assertCount(8, (array) ($payload->toExecuteBody(false)['components'] ?? []));
	}

	public function testNestedComponentsCountTowardsTheLimit(): void
	{
		// 1 container + 13 sections, each section being itself + 1 text + 1 accessory: 40 in total.
		$sections = array_map(static fn(int $i): Section => new Section(new LinkButton('https://example.com', 'x'), new TextDisplay((string) $i)), range(1, 13));
		(new Payload())->addComponent(new Container(...$sections))->toExecuteBody(false);

		$this->expectException(InvalidArgumentException::class);
		(new Payload())->addComponent(new Container(...$sections))->addComponent(new TextDisplay('41st'))->toExecuteBody(false);
	}

	/**
	 * @return iterable<string, array{callable(): mixed}>
	 */
	public static function invalid(): iterable
	{
		yield 'empty text' => [static fn() => new TextDisplay('')];
		yield 'text over 4000' => [static fn() => new TextDisplay(str_repeat('a', 4001))];
		yield 'section without text' => [static fn() => new Section(new LinkButton('https://example.com', 'x'))];
		yield 'section with four texts' => [static fn() => new Section(new LinkButton('https://example.com', 'x'), ...array_fill(0, 4, new TextDisplay('x')))];
		yield 'empty gallery' => [static fn() => new MediaGallery()];
		yield 'gallery of eleven' => [static fn() => new MediaGallery(...array_fill(0, 11, new MediaGalleryItem('https://example.com/a.png')))];
		yield 'media that is not a URL' => [static fn() => new MediaGalleryItem('ftp://example.com/a.png')];
		yield 'thumbnail description over 1024' => [static fn() => new Thumbnail('https://example.com/a.png', str_repeat('a', 1025))];
		yield 'empty container' => [static fn() => new Container()];
		yield 'bad accent colour' => [static fn() => (new Container(new TextDisplay('x')))->withAccentColor('#GGG')];
	}

	/**
	 * @param callable(): mixed $build
	 */
	#[DataProvider('invalid')]
	public function testInvalidComponentsAreRejected(callable $build): void
	{
		$this->expectException(InvalidArgumentException::class);
		$build();
	}

	public function testSeparatorDefaults(): void
	{
		self::assertSame(['type' => 14, 'divider' => true, 'spacing' => 1], (new Separator())->jsonSerialize());
		self::assertSame(['type' => 14, 'divider' => false, 'spacing' => 1], (new Separator(divider: false))->jsonSerialize());
	}

	public function testContainerCopiesAreIndependent(): void
	{
		$plain = new Container(new TextDisplay('x'));
		$plain->withAccentColor(0xFF0000)->asSpoiler();

		self::assertSame(['type' => 17, 'components' => [['type' => 10, 'content' => 'x']]], $plain->jsonSerialize());
	}

	public function testSpoilerFileAndContainer(): void
	{
		$file = Attachment::fromString('x', 'x.txt');

		self::assertSame(['type' => 13, 'file' => ['url' => 'attachment://x.txt'], 'spoiler' => true], (new File($file, spoiler: true))->jsonSerialize());
		self::assertSame(
			['type' => 17, 'components' => [['type' => 10, 'content' => 'x']], 'spoiler' => true],
			(new Container(new TextDisplay('x')))->asSpoiler()->jsonSerialize(),
		);
	}
}

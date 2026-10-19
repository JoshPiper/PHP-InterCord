<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Component\ActionRow;
use Internet\InterCord\Component\LinkButton;
use Internet\InterCord\Component\RawComponent;
use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Payload;
use Internet\InterCord\PayloadField;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ComponentTest extends TestCase
{
	public function testLinkButtonsInARow(): void
	{
		$discord = new FakeDiscord(FakeDiscord::noContent());

		$discord->webhook()->execute(
			(new Payload())->setContent('links')->addComponent(new ActionRow(
				new LinkButton('https://example.com/docs', 'Docs'),
				new LinkButton('https://example.com/status', 'Status', emoji: '🟢', disabled: true),
			)),
		);

		self::assertSame(FakeDiscord::ENDPOINT . '?with_components=true', (string) $discord->request()->getUri());
		self::assertSame(
			[
				'content' => 'links',
				'components' => [[
					'type' => 1,
					'components' => [
						['type' => 2, 'style' => 5, 'url' => 'https://example.com/docs', 'label' => 'Docs'],
						['type' => 2, 'style' => 5, 'url' => 'https://example.com/status', 'label' => 'Status', 'emoji' => ['name' => '🟢'], 'disabled' => true],
					],
				]],
			],
			$discord->body(),
		);
	}

	public function testWithComponentsIsOnlySentWhenThereAreComponents(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->executeAndWait('hello');

		self::assertSame(FakeDiscord::ENDPOINT . '?wait=true', (string) $discord->request()->getUri());
	}

	public function testEditsSendComponentsToo(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->addComponent(new ActionRow(new LinkButton('https://example.com', 'x'))));

		self::assertSame(
			FakeDiscord::ENDPOINT . '/messages/' . FakeDiscord::MESSAGE_ID . '?with_components=true',
			(string) $discord->request()->getUri(),
		);
	}

	public function testClearingComponents(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->clear(PayloadField::Components));

		self::assertSame(['components' => []], $discord->body());
	}

	public function testRawComponentsPassThroughForApplicationOwnedWebhooks(): void
	{
		$button = ['type' => 2, 'style' => 1, 'label' => 'Approve', 'custom_id' => 'approve'];

		$payload = (new Payload())->setContent('x')->addComponent(new ActionRow(new RawComponent($button)));

		self::assertSame([['type' => 1, 'components' => [$button]]], $payload->jsonSerialize()['components'] ?? null);
	}

	public function testRawComponentsCountTheirNestedComponents(): void
	{
		$section = new RawComponent([
			'type' => 9,
			'components' => [['type' => 10, 'content' => 'a'], ['type' => 10, 'content' => 'b']],
			'accessory' => ['type' => 11, 'media' => ['url' => 'https://example.com/a.png']],
		]);

		self::assertSame(4, $section->componentCount());
		self::assertSame(1, (new RawComponent(['type' => 10, 'content' => 'x']))->componentCount());
	}

	public function testARawV2ComponentSetsTheFlag(): void
	{
		$body = (new Payload())->addComponent(new RawComponent(['type' => 10, 'content' => 'x']))->toExecuteBody(false);

		self::assertSame(1 << 15, $body['flags'] ?? null);
	}

	public function testARawClassicComponentDoesNot(): void
	{
		$body = (new Payload())->setContent('x')->addComponent(new RawComponent(['type' => 1, 'components' => []]))->toExecuteBody(false);

		self::assertArrayNotHasKey('flags', $body);
	}

	/**
	 * @return iterable<string, array{callable(): mixed}>
	 */
	public static function invalid(): iterable
	{
		yield 'empty row' => [static fn() => new ActionRow()];
		yield 'six buttons' => [static fn() => new ActionRow(...array_fill(0, 6, new LinkButton('https://example.com', 'x')))];
		yield 'not a web URL' => [static fn() => new LinkButton('javascript:alert(1)', 'x')];
		yield 'URL over 512' => [static fn() => new LinkButton('https://example.com/' . str_repeat('a', 500), 'x')];
		yield 'no label or emoji' => [static fn() => new LinkButton('https://example.com')];
		yield 'label over 80' => [static fn() => new LinkButton('https://example.com', str_repeat('a', 81))];
		yield 'raw without a type' => [static fn() => new RawComponent(['label' => 'x'])];
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

	public function testMoreThanFiveRowsAreRejected(): void
	{
		$discord = new FakeDiscord();
		$payload = (new Payload())->setContent('x');
		for ($i = 0; $i < 6; $i++) {
			$payload->addComponent(new ActionRow(new LinkButton('https://example.com', (string) $i)));
		}

		$this->expectException(InvalidArgumentException::class);
		try {
			$discord->webhook()->execute($payload);
		} finally {
			self::assertSame(0, $discord->requestCount());
		}
	}
}

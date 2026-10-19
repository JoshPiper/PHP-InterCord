<?php

declare(strict_types=1);

namespace Internet\InterCord\Tests;

use Internet\InterCord\Exception\InvalidArgumentException;
use Internet\InterCord\Payload;
use Internet\InterCord\PayloadField;
use Internet\InterCord\RichEmbed;
use Internet\InterCord\Tests\Support\FakeDiscord;
use PHPUnit\Framework\TestCase;

final class ClearTest extends TestCase
{
	public function testClearingContentSendsNull(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->clear(PayloadField::Content));

		self::assertSame(['content' => null], $discord->body());
	}

	public function testClearingEmbedsSendsAnEmptyList(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->editMessage(FakeDiscord::MESSAGE_ID, (new Payload())->setContent('text only')->clear(PayloadField::Embeds));

		self::assertSame(['content' => 'text only', 'embeds' => []], $discord->body());
	}

	public function testSeveralFieldsAtOnce(): void
	{
		$payload = (new Payload())->clear(PayloadField::Content, PayloadField::Embeds);

		self::assertSame(['content' => null, 'embeds' => []], $payload->toEditBody());
	}

	public function testSettingAFieldAfterClearingItWins(): void
	{
		$payload = (new Payload())
			->clear(PayloadField::Content, PayloadField::Embeds)
			->setContent('back')
			->addEmbed((new RichEmbed())->setTitle('new'));

		self::assertSame(['content' => 'back', 'embeds' => [['title' => 'new', 'type' => 'rich']]], $payload->toEditBody());
	}

	public function testClearingAFieldAfterSettingItWins(): void
	{
		$payload = (new Payload())->setContent('gone')->clear(PayloadField::Content);

		self::assertSame(['content' => null], $payload->toEditBody());
	}

	public function testClearWorksThroughMessageEdit(): void
	{
		$discord = new FakeDiscord(FakeDiscord::json(200, FakeDiscord::message()), FakeDiscord::json(200, FakeDiscord::message()));

		$discord->webhook()->executeAndWait('hello')->edit((new Payload())->clear(PayloadField::Embeds));

		self::assertSame(['embeds' => []], $discord->body(1));
	}

	public function testANewMessageCannotClearAnything(): void
	{
		$discord = new FakeDiscord();

		$this->expectException(InvalidArgumentException::class);
		try {
			$discord->webhook()->execute((new Payload())->setContent('hello')->clear(PayloadField::Embeds));
		} finally {
			self::assertSame(0, $discord->requestCount());
		}
	}
}

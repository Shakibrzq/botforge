<?php

declare(strict_types=1);

namespace BotForge\Tests\Unit\Keyboard;

use Botforge\Keyboard\Button;
use Botforge\Keyboard\ForceReply;
use Botforge\Keyboard\InlineKeyboard;
use Botforge\Keyboard\Keyboard;
use Botforge\Keyboard\RemoveKeyboard;
use Botforge\Keyboard\ReplyKeyboard;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class KeyboardTest extends TestCase
{
    public function testCallbackButtonSerializesCorrectly(): void
    {
        self::assertSame(
            ['text' => 'Open', 'callback_data' => 'menu'],
            Button::callback('Open', 'menu')->toArray()
        );
    }

    public function testCallbackButtonEnforcesTelegramByteLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Button::callback('Open', str_repeat('a', 65));
    }

    public function testCallbackButtonRejectsEmptyData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Button::callback('Open', '');
    }

    public function testButtonRejectsEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Button::plain('   ');
    }

    public function testUrlButtonAcceptsSupportedSchemes(): void
    {
        self::assertSame(
            ['text' => 'Website', 'url' => 'https://example.com'],
            Button::url('Website', 'https://example.com')->toArray()
        );
        self::assertSame(
            ['text' => 'Telegram', 'url' => 'tg://resolve?domain=example'],
            Button::url('Telegram', 'tg://resolve?domain=example')->toArray()
        );
    }

    public function testUrlButtonRejectsUnsupportedScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Button::url('Website', 'javascript:alert(1)');
    }

    public function testWebAppButtonRequiresHttps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Button::webApp('Open app', 'http://example.com');
    }

    public function testWebAppButtonSerializesCorrectly(): void
    {
        self::assertSame(
            ['text' => 'Open app', 'web_app' => ['url' => 'https://example.com']],
            Button::webApp('Open app', 'https://example.com')->toArray()
        );
    }

    public function testReplyButtonTypesSerializeCorrectly(): void
    {
        self::assertSame(['text' => 'Yes'], Button::plain('Yes')->toArray());
        self::assertSame(
            ['text' => 'Share phone', 'request_contact' => true],
            Button::requestContact('Share phone')->toArray()
        );
        self::assertSame(
            ['text' => 'Share location', 'request_location' => true],
            Button::requestLocation('Share location')->toArray()
        );
    }

    public function testInlineKeyboardBuildsRows(): void
    {
        $keyboard = Keyboard::inline()
            ->row(Button::callback('One', 'one'), Button::callback('Two', 'two'))
            ->row(Button::url('Site', 'https://example.com'));

        self::assertSame([
            'inline_keyboard' => [
                [
                    ['text' => 'One', 'callback_data' => 'one'],
                    ['text' => 'Two', 'callback_data' => 'two'],
                ],
                [
                    ['text' => 'Site', 'url' => 'https://example.com'],
                ],
            ],
        ], $keyboard->toArray());
    }

    public function testInlineKeyboardRejectsEmptyRow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InlineKeyboard())->row();
    }

    public function testInlineKeyboardRejectsMoreThanEightButtonsInRow(): void
    {
        $buttons = array_fill(0, 9, Button::plain('Button'));

        $this->expectException(InvalidArgumentException::class);
        (new InlineKeyboard())->row(...$buttons);
    }

    public function testReplyKeyboardSupportsOptions(): void
    {
        $keyboard = Keyboard::reply()
            ->row(Button::plain('Yes'), Button::plain('No'))
            ->resize(false)
            ->oneTime()
            ->placeholder('Choose an option');

        self::assertSame([
            'keyboard' => [[['text' => 'Yes'], ['text' => 'No']]],
            'resize_keyboard' => false,
            'one_time_keyboard' => true,
            'input_field_placeholder' => 'Choose an option',
        ], $keyboard->toArray());
    }

    public function testReplyKeyboardRejectsEmptyRow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReplyKeyboard())->row();
    }

    public function testRemoveKeyboardSerialization(): void
    {
        self::assertInstanceOf(RemoveKeyboard::class, Keyboard::remove());
        self::assertSame(['remove_keyboard' => true], Keyboard::remove()->toArray());
    }

    public function testForceReplySerialization(): void
    {
        self::assertInstanceOf(ForceReply::class, Keyboard::forceReply());
        self::assertSame(
            ['force_reply' => true, 'input_field_placeholder' => 'Your name?'],
            Keyboard::forceReply('Your name?')->toArray()
        );
        self::assertSame(['force_reply' => true], Keyboard::forceReply()->toArray());
    }

    public function testPaginationBuildsItemsAndNavigation(): void
    {
        $keyboard = Keyboard::paginate(
            [10 => 'Ten', 20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty'],
            2,
            2,
            'products',
            2,
        );

        self::assertSame([
            'inline_keyboard' => [
                [
                    ['text' => 'Thirty', 'callback_data' => 'products:item:30'],
                    ['text' => 'Forty', 'callback_data' => 'products:item:40'],
                ],
                [
                    ['text' => '« Prev', 'callback_data' => 'products:page:1'],
                    ['text' => 'Next »', 'callback_data' => 'products:page:3'],
                ],
            ],
        ], $keyboard->toArray());
    }

    public function testPaginationClampsPageToValidRange(): void
    {
        $firstPage = Keyboard::paginate(['A', 'B', 'C'], 0, 2, 'items');
        $lastPage = Keyboard::paginate(['A', 'B', 'C'], 99, 2, 'items');

        self::assertSame(
            ['inline_keyboard' => [[['text' => 'A', 'callback_data' => 'items:item:0']], [['text' => 'B', 'callback_data' => 'items:item:1']], [['text' => 'Next »', 'callback_data' => 'items:page:2']]]],
            $firstPage->toArray()
        );
        self::assertSame(
            ['inline_keyboard' => [[['text' => 'C', 'callback_data' => 'items:item:2']], [['text' => '« Prev', 'callback_data' => 'items:page:1']]]],
            $lastPage->toArray()
        );
    }

    public function testPaginationRejectsInvalidPageSizeOrColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Keyboard::paginate(['A'], 1, 0, 'items');
    }
}

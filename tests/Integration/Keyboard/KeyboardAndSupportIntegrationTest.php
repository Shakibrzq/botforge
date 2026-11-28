<?php

declare(strict_types=1);

namespace BotForge\Tests\Integration\Keyboard;

use Botforge\Api\TelegramClient;
use Botforge\Keyboard\Button;
use Botforge\Keyboard\Keyboard;
use Botforge\Support\Html;
use Botforge\Support\MarkdownV2;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class KeyboardAndSupportIntegrationTest extends TestCase
{
    public function testKeyboardAndFormattedTextAreSerializedIntoTelegramRequest(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['ok' => true, 'result' => ['message_id' => 1]], JSON_THROW_ON_ERROR),
            ),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $telegram = new TelegramClient(
            token: 'TEST_TOKEN',
            defaultParseMode: 'HTML',
            http: new Client(['handler' => $stack]),
        );

        $telegram->sendMessage(
            123,
            'Welcome ' . Html::bold('<Admin>'),
            [
                'keyboard' => Keyboard::inline()->row(
                    Button::callback('Open profile', 'profile:open')
                ),
            ],
        );

        self::assertCount(1, $history);
        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(123, $payload['chat_id']);
        self::assertSame('Welcome <b>&lt;Admin&gt;</b>', $payload['text']);
        self::assertSame('HTML', $payload['parse_mode']);
        self::assertSame([
            'inline_keyboard' => [[
                ['text' => 'Open profile', 'callback_data' => 'profile:open'],
            ]],
        ], $payload['reply_markup']);
    }

    public function testMarkdownV2FormattedTextCanBeSentAsTelegramMessage(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['ok' => true, 'result' => ['message_id' => 2]], JSON_THROW_ON_ERROR),
            ),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $telegram = new TelegramClient(
            token: 'TEST_TOKEN',
            defaultParseMode: 'MarkdownV2',
            http: new Client(['handler' => $stack]),
        );

        $telegram->sendMessage(123, 'Price: ' . MarkdownV2::bold('10.5!'));

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('MarkdownV2', $payload['parse_mode']);
        self::assertSame('Price: *10\\.5\\!*', $payload['text']);
    }
}

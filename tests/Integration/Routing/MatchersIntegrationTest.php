<?php

declare(strict_types=1);

namespace BotForge\Tests\Integration;

use Botforge\Api\TelegramClient;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use Botforge\Routing\Matchers;
use Botforge\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Integration checks for the real Telegram-update -> DTO -> Context -> Matcher flow.
 */
final class MatchersIntegrationTest extends TestCase
{
    private function contextFromTelegramPayload(array $payload): Context
    {
        $update = Update::fromArray($payload);
        $telegram = (new ReflectionClass(TelegramClient::class))->newInstanceWithoutConstructor();
        $storage = $this->createMock(StorageInterface::class);

        return new Context($update, $telegram, $storage);
    }

    public function testTelegramStartUpdateFlowsThroughContextIntoCommandMatcher(): void
    {
        $context = $this->contextFromTelegramPayload([
            'update_id' => 1001,
            'message' => [
                'message_id' => 50,
                'date' => 1_700_000_000,
                'chat' => ['id' => 12345, 'type' => 'private'],
                'from' => [
                    'id' => 12345,
                    'is_bot' => false,
                    'first_name' => 'Test User',
                ],
                'text' => '/start referral_abc',
            ],
        ]);

        self::assertSame('message', $context->update()->type());
        self::assertSame('start', $context->command());
        self::assertSame(['referral_abc'], $context->args());
        self::assertSame(
            ['payload' => 'referral_abc'],
            (Matchers::command('start'))($context),
        );
    }

    public function testTelegramCallbackUpdateFlowsIntoParameterizedCallbackMatcher(): void
    {
        $context = $this->contextFromTelegramPayload([
            'update_id' => 1002,
            'callback_query' => [
                'id' => 'callback-abc',
                'from' => [
                    'id' => 12345,
                    'is_bot' => false,
                    'first_name' => 'Test User',
                ],
                'data' => 'product:987',
            ],
        ]);

        self::assertSame('callback_query', $context->update()->type());
        self::assertSame('product:987', $context->callbackData());
        self::assertSame(
            ['id' => '987'],
            (Matchers::callback('product:{id}'))($context),
        );
    }

    public function testTelegramInlineQueryUpdateFlowsIntoInlineQueryMatcher(): void
    {
        $context = $this->contextFromTelegramPayload([
            'update_id' => 1003,
            'inline_query' => [
                'id' => 'inline-abc',
                'from' => [
                    'id' => 12345,
                    'is_bot' => false,
                    'first_name' => 'Test User',
                ],
                'query' => 'telegram framework',
                'offset' => '',
            ],
        ]);

        self::assertSame('inline_query', $context->update()->type());
        self::assertSame(
            ['query' => 'telegram framework'],
            (Matchers::inlineQuery())($context),
        );
    }
}
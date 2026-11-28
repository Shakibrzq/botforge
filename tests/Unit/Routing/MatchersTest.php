<?php

declare(strict_types=1);

namespace BotForge\Tests\Unit\Routing;

use Botforge\Api\TelegramClient;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use Botforge\Routing\Matchers;
use Botforge\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MatchersTest extends TestCase
{
    private function createContext(string $text): Context
    {
        $update = Update::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'date' => time(),
                'chat' => [
                    'id' => 123,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 456,
                    'is_bot' => false,
                    'first_name' => 'Test',
                ],
                'text' => $text,
            ],
        ]);

        $telegram = (new ReflectionClass(TelegramClient::class))->newInstanceWithoutConstructor();

        $storage = $this->createMock(StorageInterface::class);

        return new Context(
            update: $update,
            telegram: $telegram,
            storage: $storage,
        );
    }

    public function testItMatchesStartCommand(): void
    {
        $context = $this->createContext('/start');

        $matcher = Matchers::command('start');

        $result = $matcher($context);

        self::assertSame([], $result);
    }

    public function testItDoesNotMatchDifferentCommand(): void
    {
        $context = $this->createContext('/help');

        $matcher = Matchers::command('start');

        $result = $matcher($context);

        self::assertNull($result);
    }

    public function testItExtractsCommandPayload(): void
    {
        $context = $this->createContext('/start ref_123');

        $matcher = Matchers::command('start');

        $result = $matcher($context);

        self::assertSame(
            ['payload' => 'ref_123'],
            $result
        );
    }
}
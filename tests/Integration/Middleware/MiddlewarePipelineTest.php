<?php

declare(strict_types=1);

namespace BotForge\Tests\Integration\Middleware;

use Botforge\Api\TelegramClient;
use Botforge\Container\Container;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use Botforge\Middleware\ChatTypeMiddleware;
use Botforge\Middleware\Middleware;
use Botforge\Middleware\Pipeline;
use Botforge\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MiddlewarePipelineTest extends TestCase
{
    private function createContext(string $chatType): Context
    {
        $update = Update::fromArray([
            'update_id' => 100,
            'message' => [
                'message_id' => 10,
                'date' => 1_700_000_000,
                'chat' => ['id' => 123, 'type' => $chatType],
                'from' => [
                    'id' => 456,
                    'is_bot' => false,
                    'first_name' => 'Integration',
                ],
                'text' => '/start',
            ],
        ]);

        $telegram = (new ReflectionClass(TelegramClient::class))->newInstanceWithoutConstructor();
        $storage = $this->createMock(StorageInterface::class);

        return new Context($update, $telegram, $storage);
    }

    public function testPipelineCombinesChatTypeGateAndHandler(): void
    {
        $pipeline = new Pipeline(new Container());
        $context = $this->createContext('private');
        $handlerCalled = false;

        $result = $pipeline->run(
            $context,
            [new ChatTypeMiddleware('private')],
            function (Context $ctx) use (&$handlerCalled): string {
                $handlerCalled = true;
                return 'start handled';
            },
        );

        self::assertTrue($handlerCalled);
        self::assertSame('start handled', $result);
    }

    public function testPipelinePreventsHandlerForDisallowedChat(): void
    {
        $pipeline = new Pipeline(new Container());
        $context = $this->createContext('group');
        $handlerCalled = false;

        $result = $pipeline->run(
            $context,
            [new ChatTypeMiddleware('private')],
            function (Context $ctx) use (&$handlerCalled): void {
                $handlerCalled = true;
            },
        );

        self::assertFalse($handlerCalled);
        self::assertNull($result);
    }

    public function testPipelineSupportsMiddlewareObjectsAndClosuresTogether(): void
    {
        $pipeline = new Pipeline(new Container());
        $context = $this->createContext('private');
        $order = [];

        $objectMiddleware = new class($order) implements Middleware {
            /** @var list<string> */
            private array $order;

            /** @param list<string> $order */
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function process(Context $ctx, \Closure $next): mixed
            {
                $this->order[] = 'object-before';
                $result = $next($ctx);
                $this->order[] = 'object-after';
                return $result;
            }
        };

        $closureMiddleware = static function (Context $ctx, \Closure $next) use (&$order): mixed {
            $order[] = 'closure-before';
            $result = $next($ctx);
            $order[] = 'closure-after';
            return $result;
        };

        $pipeline->run(
            $context,
            [$objectMiddleware, $closureMiddleware],
            static function (Context $ctx) use (&$order): void {
                $order[] = 'handler';
            },
        );

        self::assertSame([
            'object-before',
            'closure-before',
            'handler',
            'closure-after',
            'object-after',
        ], $order);
    }
}

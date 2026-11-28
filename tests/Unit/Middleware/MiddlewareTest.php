<?php

declare(strict_types=1);

namespace BotForge\Tests\Unit\Middleware;

use Botforge\Api\TelegramClient;
use Botforge\Config;
use Botforge\Container\Container;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use Botforge\Middleware\AdminOnlyMiddleware;
use Botforge\Middleware\ChannelMembershipMiddleware;
use Botforge\Middleware\ChatTypeMiddleware;
use Botforge\Middleware\LoggingMiddleware;
use Botforge\Middleware\Middleware;
use Botforge\Middleware\Pipeline;
use Botforge\Middleware\RegisteredUserMiddleware;
use Botforge\Middleware\ThrottleMiddleware;
use Botforge\Storage\StorageInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

final class MiddlewareTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    private function createContext(array $payload, ?StorageInterface $storage = null, ?TelegramClient $telegram = null): Context
    {
        $update = Update::fromArray($payload);
        $telegram ??= (new ReflectionClass(TelegramClient::class))->newInstanceWithoutConstructor();
        $storage ??= $this->createMock(StorageInterface::class);

        return new Context($update, $telegram, $storage);
    }

    private function messageContext(
        string $chatType = 'private',
        ?int $userId = 456,
        ?StorageInterface $storage = null,
        ?TelegramClient $telegram = null,
    ): Context {
        $message = [
            'message_id' => 10,
            'date' => 1_700_000_000,
            'chat' => ['id' => 123, 'type' => $chatType],
            'text' => 'hello',
        ];

        if ($userId !== null) {
            $message['from'] = [
                'id' => $userId,
                'is_bot' => false,
                'first_name' => 'Test',
            ];
        }

        return $this->createContext(
            ['update_id' => 1, 'message' => $message],
            $storage,
            $telegram,
        );
    }

    private function createTelegramClient(array $responses): TelegramClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);

        return new TelegramClient(
            token: '123456:TEST_TOKEN',
            http: new Client(['handler' => $stack]),
        );
    }

    public function testRegisteredUserMiddlewarePassesRegisteredUser(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('get')->willReturn(['registered' => true]);
        $context = $this->messageContext(storage: $storage);
        $called = false;

        $result = (new RegisteredUserMiddleware())->process($context, function (Context $ctx) use (&$called): string {
            $called = true;
            return 'continued';
        });

        self::assertTrue($called);
        self::assertSame('continued', $result);
    }

    public function testRegisteredUserMiddlewareStopsUnregisteredUser(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('get')->willReturn([]);
        $context = $this->messageContext(storage: $storage);
        $called = false;

        $result = (new RegisteredUserMiddleware(denyMessage: ''))->process(
            $context,
            function (Context $ctx) use (&$called): void {
                $called = true;
            },
        );

        self::assertFalse($called);
        self::assertNull($result);
    }

    public function testRegisteredUserMiddlewareStopsUpdateWithoutUserOrChat(): void
    {
        $context = $this->createContext(['update_id' => 2]);
        $called = false;

        $result = (new RegisteredUserMiddleware())->process(
            $context,
            function (Context $ctx) use (&$called): void {
                $called = true;
            },
        );

        self::assertFalse($called);
        self::assertNull($result);
    }

    public function testRegisteredUserMiddlewareRejectsEmptySessionKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RegisteredUserMiddleware(sessionKey: '');
    }

    public function testThrottleMiddlewareIncrementsAttemptsAndContinues(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects(self::once())->method('get')->with(self::callback(
            static fn (string $key): bool => str_starts_with($key, 'test-throttle:user:456:window:'),
        ))->willReturn(1);
        $storage->expects(self::once())->method('set')->with(
            self::callback(static fn (string $key): bool => str_starts_with($key, 'test-throttle:user:456:window:')),
            2,
            60,
        );

        $context = $this->messageContext(storage: $storage);
        $called = false;
        $result = (new ThrottleMiddleware($storage, 3, 60, 'test-throttle', ''))->process(
            $context,
            function (Context $ctx) use (&$called): string {
                $called = true;
                return 'allowed';
            },
        );

        self::assertTrue($called);
        self::assertSame('allowed', $result);
    }

    public function testThrottleMiddlewareStopsWhenLimitHasBeenReached(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects(self::once())->method('get')->willReturn(3);
        $storage->expects(self::never())->method('set');
        $context = $this->messageContext(storage: $storage);
        $called = false;

        $result = (new ThrottleMiddleware($storage, 3, 60, 'test-throttle', ''))->process(
            $context,
            function (Context $ctx) use (&$called): void {
                $called = true;
            },
        );

        self::assertFalse($called);
        self::assertNull($result);
    }

    public function testThrottleMiddlewareLetsUpdatesWithoutUserContinue(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects(self::never())->method('get');
        $storage->expects(self::never())->method('set');
        $context = $this->messageContext(userId: null, storage: $storage);
        $called = false;

        $result = (new ThrottleMiddleware($storage))->process(
            $context,
            function (Context $ctx) use (&$called): string {
                $called = true;
                return 'continued';
            },
        );

        self::assertTrue($called);
        self::assertSame('continued', $result);
    }

    public function testThrottleMiddlewareRejectsInvalidConfiguration(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        try {
            new ThrottleMiddleware($storage, maxAttempts: 0);
            self::fail('Expected maxAttempts validation to throw.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        new ThrottleMiddleware($storage, windowSeconds: 0);
    }

    public function testAdminOnlyMiddlewarePassesConfiguredAdmin(): void
    {
        $config = Config::fromArray([
            'BOT_TOKEN' => '123456:TEST_TOKEN',
            'ADMIN_IDS' => '456,789',
        ]);
        $context = $this->messageContext();
        $called = false;

        $result = (new AdminOnlyMiddleware($config))->process(
            $context,
            function (Context $ctx) use (&$called): string {
                $called = true;
                return 'admin';
            },
        );

        self::assertTrue($called);
        self::assertSame('admin', $result);
    }

    public function testAdminOnlyMiddlewareStopsNonAdminWhenConfiguredSilently(): void
    {
        $config = Config::fromArray(['BOT_TOKEN' => '123456:TEST_TOKEN', 'ADMIN_IDS' => '789']);
        $context = $this->messageContext();
        $called = false;

        $result = (new AdminOnlyMiddleware($config, null))->process(
            $context,
            function (Context $ctx) use (&$called): void {
                $called = true;
            },
        );

        self::assertFalse($called);
        self::assertNull($result);
    }

    public function testChatTypeMiddlewarePassesAllowedPrivateChat(): void
    {
        $context = $this->messageContext('private');
        $called = false;

        $result = (new ChatTypeMiddleware('private'))->process(
            $context,
            function (Context $ctx) use (&$called): string {
                $called = true;
                return 'private';
            },
        );

        self::assertTrue($called);
        self::assertSame('private', $result);
    }

    public function testChatTypeMiddlewareStopsDisallowedChat(): void
    {
        $context = $this->messageContext('group');
        $called = false;

        $result = (new ChatTypeMiddleware('private'))->process(
            $context,
            function (Context $ctx) use (&$called): void {
                $called = true;
            },
        );

        self::assertFalse($called);
        self::assertNull($result);
    }

    public function testChatTypeMiddlewareRejectsEmptyOrUnknownTypes(): void
    {
        try {
            new ChatTypeMiddleware();
            self::fail('Expected empty chat types to throw.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        new ChatTypeMiddleware('supergroup');
    }

    public function testChannelMembershipMiddlewareLetsMemberContinue(): void
    {
        $telegram = $this->createTelegramClient([
            new Response(200, [], json_encode([
                'ok' => true,
                'result' => ['status' => 'member'],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $context = $this->messageContext(telegram: $telegram);
        $called = false;

        $result = (new ChannelMembershipMiddleware('@mychannel'))->process(
            $context,
            function (Context $ctx) use (&$called): string {
                $called = true;
                return 'member';
            },
        );

        self::assertTrue($called);
        self::assertSame('member', $result);
    }

    public function testChannelMembershipMiddlewareLetsUpdateWithoutUserContinue(): void
    {
        $context = $this->messageContext(userId: null);
        $called = false;

        $result = (new ChannelMembershipMiddleware('@mychannel'))->process(
            $context,
            function (Context $ctx) use (&$called): string {
                $called = true;
                return 'no-user';
            },
        );

        self::assertTrue($called);
        self::assertSame('no-user', $result);
    }

    public function testLoggingMiddlewareLogsSuccessfulUpdate(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');
        $logger->expects(self::never())->method('error');
        $context = $this->messageContext();

        $result = (new LoggingMiddleware($logger))->process(
            $context,
            static fn (Context $ctx): string => 'done',
        );

        self::assertSame('done', $result);
    }

    public function testLoggingMiddlewareLogsAndRethrowsHandlerException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger->expects(self::once())->method('error')->with(
            'Update failed',
            self::callback(static fn (array $context): bool =>
                ($context['exception'] ?? null) === RuntimeException::class
                && ($context['error'] ?? null) === 'handler failed'
            ),
        );
        $context = $this->messageContext();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('handler failed');

        (new LoggingMiddleware($logger))->process(
            $context,
            static function (Context $ctx): never {
                throw new RuntimeException('handler failed');
            },
        );
    }

    public function testPipelineRunsMiddlewareInOnionOrder(): void
    {
        $pipeline = new Pipeline(new Container());
        $context = $this->messageContext();
        $order = [];
        $first = static function (Context $ctx, \Closure $next) use (&$order): mixed {
            $order[] = 'first-before';
            $result = $next($ctx);
            $order[] = 'first-after';
            return $result;
        };
        $second = static function (Context $ctx, \Closure $next) use (&$order): mixed {
            $order[] = 'second-before';
            $result = $next($ctx);
            $order[] = 'second-after';
            return $result;
        };

        $result = $pipeline->run($context, [$first, $second], static function (Context $ctx) use (&$order): string {
            $order[] = 'destination';
            return 'finished';
        });

        self::assertSame('finished', $result);
        self::assertSame([
            'first-before',
            'second-before',
            'destination',
            'second-after',
            'first-after',
        ], $order);
    }

    public function testPipelineStopsWhenMiddlewareDoesNotCallNext(): void
    {
        $pipeline = new Pipeline(new Container());
        $context = $this->messageContext();
        $destinationCalled = false;

        $result = $pipeline->run(
            $context,
            [static fn (Context $ctx, \Closure $next): null => null],
            function (Context $ctx) use (&$destinationCalled): void {
                $destinationCalled = true;
            },
        );

        self::assertFalse($destinationCalled);
        self::assertNull($result);
    }
}

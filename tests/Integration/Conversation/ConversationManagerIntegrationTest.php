<?php

declare(strict_types=1);

namespace BotForge\Tests\Integration\Conversation;

use Botforge\Api\TelegramClient;
use Botforge\Context\Context;
use Botforge\Conversation\Conversation;
use Botforge\Conversation\ConversationManager;
use Botforge\Conversation\Step;
use Botforge\Entities\Update;
use Botforge\Storage\StorageInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ConversationManagerIntegrationTest extends TestCase
{
    private function storage(): ArrayStorageForConversationTest
    {
        return new ArrayStorageForConversationTest();
    }

    /** @param list<array<string, mixed>> $history */
    private function context(
        string $text,
        ArrayStorageForConversationTest $storage,
        array &$history,
    ): Context {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'ok' => true,
                    'result' => ['message_id' => 900, 'date' => time()],
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $telegram = new TelegramClient(
            token: 'TEST_TOKEN',
            http: new Client(['handler' => $stack]),
        );

        $update = Update::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'date' => time(),
                'chat' => ['id' => 123, 'type' => 'private'],
                'from' => [
                    'id' => 456,
                    'is_bot' => false,
                    'first_name' => 'Test',
                ],
                'text' => $text,
            ],
        ]);

        return new Context($update, $telegram, $storage);
    }

    public function testConversationStartsAndSendsFirstPrompt(): void
    {
        $storage = $this->storage();
        $history = [];
        $context = $this->context('/register', $storage, $history);
        $manager = new ConversationManager();

        $manager->start($context, new RegistrationConversationForTest());

        $session = $storage->get('session:chat:123:user:456');
        self::assertSame(RegistrationConversationForTest::class, $session['conversation_state']['class']);
        self::assertSame(0, $session['conversation_state']['current_step']);
        self::assertSame('What is your name?', json_decode((string) $history[0]['request']->getBody(), true)['text']);
    }

public function testInvalidInputKeepsCurrentStepAndRepliesWithValidationMessage(): void
{
    $storage = $this->storage();
    $manager = new ConversationManager();
    $history = [];

    // Start registration conversation.
    $manager->start(
        $this->context('/register', $storage, $history),
        new RegistrationConversationForTest(),
    );

    // First, submit a valid name.
    $history = [];

    self::assertTrue(
        $manager->handle(
            $this->context('Shakiba', $storage, $history),
            'Shakiba'
        )
    );

    // The conversation should now be waiting for a phone number.
    $stored = $storage->get('session:chat:123:user:456');

    self::assertSame(
        1,
        $stored['conversation_state']['current_step']
    );

    // Now submit an invalid phone number.
    $history = [];

    self::assertTrue(
        $manager->handle(
            $this->context('bad-phone', $storage, $history),
            'bad-phone'
        )
    );

    // The conversation must remain on the phone step.
    $stored = $storage->get('session:chat:123:user:456');

    self::assertSame(
        1,
        $stored['conversation_state']['current_step']
    );

    // The bot must send the validation error.
    self::assertSame(
        'Please enter a valid phone number.',
        json_decode(
            (string) $history[0]['request']->getBody(),
            true
        )['text']
    );
}

    public function testCancelEndsActiveConversation(): void
    {
        RegistrationConversationForTest::$cancelled = false;
        $storage = $this->storage();
        $manager = new ConversationManager();
        $history = [];

        $manager->start(
            $this->context('/register', $storage, $history),
            new RegistrationConversationForTest(),
        );

        self::assertTrue($manager->handle($this->context('/cancel', $storage, $history), '/cancel'));
        self::assertTrue(RegistrationConversationForTest::$cancelled);
        self::assertArrayNotHasKey(
            'conversation_state',
            $storage->get('session:chat:123:user:456')
        );
    }

    public function testOtherCommandsAreLeftForTheRouter(): void
    {
        $storage = $this->storage();
        $manager = new ConversationManager();
        $history = [];

        $manager->start(
            $this->context('/register', $storage, $history),
            new RegistrationConversationForTest(),
        );

        $history = [];
        self::assertFalse($manager->handle($this->context('/help', $storage, $history), '/help'));
        self::assertSame([], $history);
        self::assertArrayHasKey(
            'conversation_state',
            $storage->get('session:chat:123:user:456')
        );
    }

    public function testExpiredConversationIsDeletedAndNotifiesUser(): void
    {
        $storage = $this->storage();
        $storage->set('session:chat:123:user:456', [
            'conversation_state' => [
                'class' => RegistrationConversationForTest::class,
                'current_step' => 0,
                'data' => [],
                'last_activity' => time() - 5000,
            ],
        ]);

        $history = [];
        $manager = new ConversationManager();

        self::assertTrue($manager->handle($this->context('Shakiba', $storage, $history), 'Shakiba'));
        self::assertArrayNotHasKey(
            'conversation_state',
            $storage->get('session:chat:123:user:456')
        );
        self::assertSame(
            'Your conversation has expired.',
            json_decode((string) $history[0]['request']->getBody(), true)['text']
        );
    }
}

final class RegistrationConversationForTest extends Conversation
{
    public static bool $cancelled = false;

    public function steps(): array
    {
        return [
            new Step('name', 'What is your name?'),
            new Step(
                'phone',
                'Enter your phone number',
                static fn (string $input): bool|string => preg_match('/^09\d{9}$/', $input) === 1
                    ? true
                    : 'Please enter a valid phone number.',
            ),
        ];
    }

    public function onComplete(Context $context, array $data): void
    {
        // This also checks that ConversationManager clears its active state before completion.
        $context->session()->set('registered', true);
    }

    public function onCancel(Context $context): void
    {
        self::$cancelled = true;
    }
}

final class ArrayStorageForConversationTest implements StorageInterface
{
    private array $values = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->values[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
    }
}

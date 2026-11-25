<?php

declare(strict_types=1);

// Small tools shared by the example scripts (not part of the framework).

use Botforge\Api\TelegramClient;
use Botforge\Entities\Update;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Log\AbstractLogger;

/** Collects short notes, so a script can print what happened. */
final class Recorder
{
    /** @var list<string> */
    private array $lines = [];

    public function add(string $line): void
    {
        $this->lines[] = $line;
    }

    /** Returns all notes as one text and starts fresh. */
    public function flush(): string
    {
        $text = $this->lines === [] ? '(nothing)' : implode(' > ', $this->lines);
        $this->lines = [];

        return $text;
    }
}

/** A logger that keeps its records in memory instead of writing a file. */
final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<mixed>}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [(string) $level, (string) $message, $context];
    }
}

/**
 * A fake Telegram: answers "ok" to everything and writes every request into $sent.
 *
 * @param list<string> $sent
 * @param array<string, string|list<string>> $answers Special JSON answers by method name.
 *        A list means "one answer per call, in order" (the last one is repeated).
 */
function fakeTelegram(array &$sent, array $answers = []): TelegramClient
{
    $responder = function (RequestInterface $request) use (&$sent, &$answers): Response {
        $method = basename($request->getUri()->getPath());
        $sent[] = $method . ' ' . $request->getBody();

        $answer = $answers[$method] ?? null;

        if (is_array($answer)) {
            $json = count($answer) > 1 ? array_shift($answer) : $answer[0];
            $answers[$method] = $answer;
        } elseif (is_string($answer)) {
            $json = $answer;
        } else {
            $json = in_array($method, ['sendMessage', 'editMessageText'], true)
                ? '{"ok":true,"result":{"message_id":1}}'
                : '{"ok":true,"result":true}';
        }

        return new Response(200, [], $json);
    };

    $mock = new MockHandler(array_fill(0, 200, $responder));

    return new TelegramClient('123456:TESTTOKEN', 'HTML', 3, new Client(['handler' => HandlerStack::create($mock)]));
}

/** @param array<string, mixed> $fields Extra fields of the message (text, photo, contact, ...) */
function messageUpdate(array $fields, string $chatType = 'private', int $userId = 111): Update
{
    return Update::fromArray([
        'update_id' => 1,
        'message' => $fields + [
            'message_id' => 5,
            'date' => 1700000000,
            'chat' => ['id' => $chatType === 'private' ? $userId : -100500, 'type' => $chatType],
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'User' . $userId],
        ],
    ]);
}

function textUpdate(string $text, string $chatType = 'private', int $userId = 111): Update
{
    return messageUpdate(['text' => $text], $chatType, $userId);
}

function callbackUpdate(string $data, int $userId = 111): Update
{
    return Update::fromArray([
        'update_id' => 2,
        'callback_query' => [
            'id' => '42',
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'User' . $userId],
            'message' => [
                'message_id' => 6,
                'date' => 1700000100,
                'chat' => ['id' => $userId, 'type' => 'private'],
                'text' => 'Menu',
            ],
            'data' => $data,
        ],
    ]);
}
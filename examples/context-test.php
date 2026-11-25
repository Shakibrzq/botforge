<?php

declare(strict_types=1);

// Tests the Context with a FAKE Telegram: no internet, no real token needed.
// Guzzle's MockHandler answers instead of Telegram, and we record what the Context sent.
// Run it with:  php examples/context-test.php

require __DIR__ . '/../vendor/autoload.php';

use Botforge\Api\TelegramClient;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

// ---------- Fake Telegram: 5 prepared answers, one for each call we will make ----------
$message = '{"ok":true,"result":{"message_id":99}}';
$true = '{"ok":true,"result":true}';

$mock = new MockHandler([
    new Response(200, [], $message), // sendMessage
    new Response(200, [], $true),    // sendChatAction
    new Response(200, [], $true),    // answerCallbackQuery
    new Response(200, [], $message), // editMessageText
    new Response(200, [], $true),    // deleteMessage
]);

$history = []; // every request will be recorded here
$stack = HandlerStack::create($mock);
$stack->push(Middleware::history($history));

$telegram = new TelegramClient('123456:TESTTOKEN', 'HTML', 3, new Client(['handler' => $stack]));

// ---------- Helpers to build sample updates ----------
function textUpdate(string $text): Update
{
    return Update::fromArray([
        'update_id' => 1,
        'message' => [
            'message_id' => 5,
            'date' => 1700000000,
            'chat' => ['id' => 111, 'type' => 'private'],
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ali'],
            'text' => $text,
        ],
    ]);
}

$buttonUpdate = Update::fromArray([
    'update_id' => 2,
    'callback_query' => [
        'id' => '42',
        'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ali'],
        'message' => [
            'message_id' => 6,
            'date' => 1700000100,
            'chat' => ['id' => 111, 'type' => 'private'],
            'text' => 'Menu',
        ],
        'data' => 'product:7',
    ],
]);

// ---------- 1) Reading: command and arguments ----------
echo '1) Command parsing' . PHP_EOL;

foreach (['/start ref_123', '/Buy@MyBot  2   apples', 'hello there'] as $text) {
    $ctx = new Context(textUpdate($text), $telegram);
    echo '   ' . $text . ' => command=' . ($ctx->command() ?? 'null') . ' args=[' . implode(',', $ctx->args()) . ']' . PHP_EOL;
}

$buttonCtx = new Context($buttonUpdate, $telegram);
echo '   (button press) => callbackData=' . $buttonCtx->callbackData() . PHP_EOL;

// ---------- 2) Acting: the Context sends requests for us ----------
$textCtx = new Context(textUpdate('/start ref_123'), $telegram);
$textCtx->reply('Hello ' . $textCtx->from()?->firstName . '!');
$textCtx->typing();

$buttonCtx->answerCallback('Done');
$buttonCtx->edit('New text');
$buttonCtx->delete();

echo PHP_EOL . '2) Requests the Context sent to Telegram' . PHP_EOL;

foreach ($history as $number => $transaction) {
    $request = $transaction['request'];
    echo '   ' . ($number + 1) . '. ' . basename($request->getUri()->getPath()) . ' ' . $request->getBody() . PHP_EOL;
}

// ---------- 3) Wrong usage gives a clear error ----------
echo PHP_EOL . '3) Wrong usage' . PHP_EOL;

try {
    $textCtx->edit('This is a normal text message, not a button press');
} catch (LogicException $e) {
    echo '   caught: ' . $e->getMessage() . PHP_EOL;
}
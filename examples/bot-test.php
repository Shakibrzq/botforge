<?php

declare(strict_types=1);

// Tests the Bot and the two runners with a fake Telegram. No internet, no real token.
// Run it with:  php examples/bot-test.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/helpers.php';

use Botforge\Bot;
use Botforge\Config;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use Botforge\Middleware\AdminOnlyMiddleware;
use Botforge\Runner\PollingRunner;
use Botforge\Runner\WebhookRunner;

$config = Config::fromArray(['BOT_TOKEN' => '123456:TESTTOKEN', 'ADMIN_IDS' => '999']);

$sent = [];
$telegram = fakeTelegram($sent);
$logger = new MemoryLogger();
$rec = new Recorder();

$bot = new Bot($config, $telegram, $logger);
$bot->container()->instance(Recorder::class, $rec); // handlers can now ask for a Recorder

// A global middleware: wraps everything.
$bot->middleware(function (Context $ctx, Closure $next) use ($rec) {
    $rec->add('global before');
    $result = $next($ctx);
    $rec->add('global after');

    return $result;
});

$bot->command('start', function (Context $ctx, Recorder $rec): void {
    $rec->add('start handler');
    $ctx->reply('Welcome!');
});

$bot->command('boom', function (): void {
    throw new RuntimeException('handler exploded');
});

$bot->group(['middleware' => [AdminOnlyMiddleware::class]], function (Bot $bot): void {
    $bot->command('admin', fn (Recorder $rec) => $rec->add('admin handler'));
});

// ---------- 1) Bot::handle(): global middleware > router > handler ----------
echo '1) Bot::handle()' . PHP_EOL;

$check = function (string $label, Update $update) use ($bot, $rec, &$sent): void {
    $bot->handle($update);
    echo '   ' . str_pad($label, 26) . ' => ' . $rec->flush() . PHP_EOL;

    foreach ($sent as $line) {
        echo '       sent: ' . $line . PHP_EOL;
    }

    $sent = [];
};

$check('/start (user 111)', textUpdate('/start'));
$check('/admin (normal user 111)', textUpdate('/admin'));
$check('/admin (admin 999)', textUpdate('/admin', 'private', 999));

$threw = false;
try {
    $bot->handle(textUpdate('/boom'));
} catch (Throwable) {
    $threw = true;
}

echo '   ' . str_pad('/boom (handler throws)', 26) . ' => ' . $rec->flush() . PHP_EOL;
echo '       exception escaped from handle()? ' . ($threw ? 'yes' : 'no') . PHP_EOL;

foreach ($logger->records as [$level, $message, $context]) {
    if ($level === 'error') {
        echo '       [error] ' . $message . ' -> ' . $context['error'] . PHP_EOL;
    }
}

// ---------- 2) Webhook: which requests are accepted? ----------
echo PHP_EOL . '2) Webhook requests' . PHP_EOL;

$body = '{"update_id":7,"message":{"message_id":1,"date":1700000000,"chat":{"id":5,"type":"private"},"text":"hi"}}';
$describe = fn (Update|int $result): string => is_int($result) ? 'HTTP ' . $result : 'Update #' . $result->updateId;

$secured = new WebhookRunner(
    Config::fromArray(['BOT_TOKEN' => '123456:TESTTOKEN', 'WEBHOOK_SECRET' => 's3cret']),
    $logger,
    fn () => null
);
$open = new WebhookRunner(Config::fromArray(['BOT_TOKEN' => '123456:TESTTOKEN']), $logger, fn () => null);

$cases = [
    'GET request' => $secured->parse('GET', 's3cret', $body),
    'POST without secret header' => $secured->parse('POST', null, $body),
    'POST with wrong secret' => $secured->parse('POST', 'guess', $body),
    'POST, right secret, bad JSON' => $secured->parse('POST', 's3cret', '{not json'),
    'POST, right secret, no id' => $secured->parse('POST', 's3cret', '{"hello":"world"}'),
    'POST, right secret, valid' => $secured->parse('POST', 's3cret', $body),
    'No secret set, valid update' => $open->parse('POST', null, $body),
];

foreach ($cases as $label => $result) {
    echo '   ' . str_pad($label, 30) . ' => ' . $describe($result) . PHP_EOL;
}

// ---------- 3) Long polling ----------
echo PHP_EOL . '3) Long polling (2 rounds)' . PHP_EOL;

$makeUpdate = fn (int $id, string $text): array => [
    'update_id' => $id,
    'message' => [
        'message_id' => $id,
        'date' => 1700000000,
        'chat' => ['id' => 5, 'type' => 'private'],
        'from' => ['id' => 5, 'is_bot' => false, 'first_name' => 'Ali'],
        'text' => $text,
    ],
];

$firstAnswer = json_encode(['ok' => true, 'result' => [$makeUpdate(10, 'one'), $makeUpdate(11, 'two')]]);
$sent = [];
$pollTelegram = fakeTelegram($sent, ['getUpdates' => [$firstAnswer, '{"ok":true,"result":[]}']]);

$seen = [];
$runner = new PollingRunner(
    $pollTelegram,
    function (Update $update) use (&$seen): void {
        $seen[] = $update->message?->text;
    },
    $logger,
    10
);
$runner->run(2); // two rounds, then stop

echo '   ' . str_pad('updates handled', 18) . ' => ' . implode(', ', $seen) . PHP_EOL;

foreach ($sent as $number => $line) {
    echo '   ' . str_pad('request ' . ($number + 1), 18) . ' => ' . $line . PHP_EOL;
}
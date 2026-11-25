<?php

declare(strict_types=1);

// Tries the API client for real. Needs a real BOT_TOKEN in .env and internet access.
//
//   php examples/api-test.php             -> shows who the bot is and who wrote to it
//   php examples/api-test.php 123456789   -> also sends a test message to that chat id
//ارتباط باهاش با mixed port vpn 
// $env:HTTPS_PROXY="http://127.0.0.1:12334"
// $env:HTTP_PROXY="http://127.0.0.1:12334"
// $env:NO_PROXY=""
// php examples/api-test.php

require __DIR__ . '/../vendor/autoload.php';

use Botforge\Api\TelegramApiException;
use Botforge\Api\TelegramClient;
use Botforge\Config;
use Botforge\ConfigException;

try {
    $config = Config::fromEnv(dirname(__DIR__));
} catch (ConfigException $e) {
    echo 'Config error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

$telegram = TelegramClient::fromConfig($config);

// If Telegram is blocked on your network, give the client a Guzzle object with a proxy:
// $telegram = TelegramClient::fromConfig($config, new \GuzzleHttp\Client(['proxy' => 'http://127.0.0.1:10809']));

try {
    // 1) Who am I?
    $me = $telegram->getMe();
    echo 'Bot username: @' . ($me['username'] ?? '?') . PHP_EOL;

    // 2) Who wrote to the bot recently? (send /start to your bot first)
    foreach ($telegram->getUpdates() as $update) {
        $chat = $update['message']['chat'] ?? null;
        if (is_array($chat)) {
            echo 'Chat id seen: ' . ($chat['id'] ?? '?') . PHP_EOL;
        }
    }

    // 3) Send a message (only if a chat id was given)
    if (isset($argv[1])) {
        $sent = $telegram->sendMessage((int) $argv[1], '<b>Hello</b> from Botforge!');
        echo 'Message sent, id: ' . ($sent['message_id'] ?? '?') . PHP_EOL;
    }

    // 4) A wrong call: shows that errors become exceptions
    try {
        $telegram->call('thisMethodDoesNotExist');
    } catch (TelegramApiException $e) {
        echo 'Expected error caught -> code ' . $e->getCode() . ': ' . $e->getMessage() . PHP_EOL;
    }
} catch (TelegramApiException $e) {
    echo 'Telegram error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
<?php

declare(strict_types=1);
//وب هوک سرور نیست فقط وب هوک رو حذف ثبت یا بررسی میکند
// Manages the webhook of the bot from the terminal.
//
//   php examples/echo-bot/webhook.php set      -> tell Telegram to call WEBHOOK_URL
//   php examples/echo-bot/webhook.php delete   -> remove the webhook (go back to polling)
//   php examples/echo-bot/webhook.php info     -> show what Telegram currently knows (default)
// اگر بخایم مجدد از لانگ پولینگاستفاده کنیم باید وب هوک حذف بشه
require __DIR__ . '/../../vendor/autoload.php';

use Botforge\Api\TelegramApiException;
use Botforge\Bot;
use Botforge\Config;
use Botforge\ConfigException;

try {
    $bot = new Bot(Config::fromEnv(dirname(__DIR__, 2)));

    switch ($argv[1] ?? 'info') {
        case 'set':
            $bot->registerWebhook();
            echo 'Webhook is set.' . PHP_EOL;
            break;
        case 'delete':
            $bot->removeWebhook();
            echo 'Webhook removed.' . PHP_EOL;
            break;
        default:
            print_r($bot->telegram()->getWebhookInfo());
    }
} catch (ConfigException | TelegramApiException | LogicException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
<?php
//بررسی این سه حالت
//اگر یک کاربر در همان چت پیام جدیدی بفرسته، اطلاعات Session قبلیش باقی بمونه.
//اگر کاربر دیگری در همان چت پیام بده، به اطلاعات کاربر اول دسترسی نداشته باشه.
//اگر همان کاربر وارد چت دیگری بشه، Session قبلیش در چت جدید قابل دسترسی نباشه

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Botforge\Api\TelegramClient;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use Botforge\Storage\FileStorage;

$storage = new FileStorage(__DIR__ . '/../storage/context-test');

$telegram = new TelegramClient('123456:TEST_TOKEN');

// ساخت Context آزمایشی با شناسه کاربر و چت مشخص
function createContext(
    int $userId,
    int $chatId,
    FileStorage $storage,
    TelegramClient $telegram
): Context {
    $update = Update::fromArray([
        'update_id' => random_int(1, 999999999),
        'message' => [
            'message_id' => 1,
            'date' => time(),
            'chat' => [
                'id' => $chatId,
                'type' => 'private',
            ],
            'from' => [
                'id' => $userId,
                'is_bot' => false,
                'first_name' => 'Test',
            ],
            'text' => 'Hello',
        ],
    ]);

    return new Context($update, $telegram, $storage);
}

// TEST 1: ذخیره اطلاعات کاربر اول
$context1 = createContext(1001, 500, $storage, $telegram);

$context1->session()->set('step', 'waiting_for_name');

echo "1. First user session saved.\n";

// TEST 2: ساخت Context جدید برای همان کاربر و همان چت
$context2 = createContext(1001, 500, $storage, $telegram);

$result = $context2->session()->get('step');

if ($result !== 'waiting_for_name') {
    throw new RuntimeException('Session persistence failed!');
}

echo "2. Session persisted across updates.\n";

// TEST 3: کاربر دوم در همان چت
$context3 = createContext(2002, 500, $storage, $telegram);

if ($context3->session()->has('step')) {
    throw new RuntimeException('Different users share the same session!');
}

echo "3. Different users have isolated sessions.\n";

// TEST 4: همان کاربر در چت دیگر
$context4 = createContext(1001, 600, $storage, $telegram);

if ($context4->session()->has('step')) {
    throw new RuntimeException('Different chats share the same session!');
}

echo "4. Different chats have isolated sessions.\n";

echo "\nAll Context Session tests passed!\n";
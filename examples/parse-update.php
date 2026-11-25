<?php

declare(strict_types=1);

// Tests the DTOs with sample data. No internet and no bot token needed.
// Run it with:  php examples/parse-update.php

require __DIR__ . '/../vendor/autoload.php';

use Botforge\Entities\InvalidUpdateException;
use Botforge\Entities\Update;

// ---------- 1) A text message (with a "future" field that we do not know) ----------
$textJson = <<<'JSON'
{
  "update_id": 100,
  "message": {
    "message_id": 5,
    "from": {"id": 111, "is_bot": false, "first_name": "Ali", "last_name": "Rezaei", "language_code": "fa"},
    "chat": {"id": 111, "first_name": "Ali", "type": "private"},
    "date": 1700000000,
    "text": "/start ref_123",
    "some_future_field": {"x": 1}
  }
}
JSON;

$update = Update::fromJson($textJson);
$message = $update->message;

echo '1) Text message' . PHP_EOL;
echo '   update type : ' . $update->type() . PHP_EOL;
echo '   message type: ' . $message?->type() . PHP_EOL;
echo '   from        : ' . $update->from()?->fullName() . ' (' . $update->from()?->languageCode . ')' . PHP_EOL;
echo '   chat is private: ' . ($update->chat()?->isPrivate() ? 'yes' : 'no') . PHP_EOL;
echo '   text        : ' . $message?->text . PHP_EOL;

// ---------- 2) A button press ----------
$callbackJson = <<<'JSON'
{
  "update_id": 101,
  "callback_query": {
    "id": "42",
    "from": {"id": 111, "is_bot": false, "first_name": "Ali"},
    "message": {
      "message_id": 6,
      "date": 1700000100,
      "chat": {"id": 111, "first_name": "Ali", "type": "private"},
      "text": "Menu"
    },
    "chat_instance": "abc",
    "data": "product:7"
  }
}
JSON;

$update = Update::fromJson($callbackJson);

echo PHP_EOL . '2) Button press' . PHP_EOL;
echo '   update type : ' . $update->type() . PHP_EOL;
echo '   button data : ' . $update->callbackQuery?->data . PHP_EOL;
echo '   chat id     : ' . $update->chat()?->id . PHP_EOL;

// ---------- 3) A photo in a group (two sizes) ----------
$photoJson = <<<'JSON'
{
  "update_id": 102,
  "message": {
    "message_id": 7,
    "from": {"id": 222, "is_bot": false, "first_name": "Sara"},
    "chat": {"id": -100123, "title": "Friends", "type": "supergroup"},
    "date": 1700000200,
    "photo": [
      {"file_id": "small-id", "file_unique_id": "a", "width": 90, "height": 60, "file_size": 1000},
      {"file_id": "big-id", "file_unique_id": "b", "width": 1280, "height": 853, "file_size": 90000}
    ],
    "caption": "My cat"
  }
}
JSON;

$update = Update::fromJson($photoJson);

echo PHP_EOL . '3) Photo in a group' . PHP_EOL;
echo '   message type  : ' . $update->message?->type() . PHP_EOL;
echo '   photo sizes   : ' . count($update->message?->photo ?? []) . PHP_EOL;
echo '   largest photo : ' . $update->message?->largestPhoto()?->fileId . PHP_EOL;
echo '   chat is group : ' . ($update->chat()?->isGroup() ? 'yes' : 'no') . PHP_EOL;

// ---------- 4) Bad input must give a clear error, not a crash ----------
echo PHP_EOL . '4) Bad input' . PHP_EOL;

foreach (['this is not json', '{"hello": "world"}'] as $badInput) {
    try {
        Update::fromJson($badInput);
    } catch (InvalidUpdateException $e) {
        echo '   caught: ' . $e->getMessage() . PHP_EOL;
    }
}

// ---------- 5) Immutability: nobody can change a message after it is created ----------
echo PHP_EOL . '5) Immutability' . PHP_EOL;

try {
    $message->text = 'changed!';
} catch (Error $e) {
    echo '   blocked: ' . $e->getMessage() . PHP_EOL;
}
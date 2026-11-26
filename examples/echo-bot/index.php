<?php

declare(strict_types=1);
// این فایل مسئول راه اندازی بات با لانگ پولینگ است
// A small REAL bot. It needs a real BOT_TOKEN in .env and access to Telegram.
//
//   In a terminal:     php examples/echo-bot/index.php     (long polling; stop with Ctrl+C)
//   Behind a web server: point the webhook URL to this file (webhook mode)
//
// Bot::run() picks the right mode by itself.

require __DIR__ . '/../../vendor/autoload.php';

use Botforge\Bot;
use Botforge\Config;
use Botforge\Context\Context;
use Botforge\Keyboard\Button;
use Botforge\Keyboard\Keyboard;
use Botforge\Support\Html;
use DemoBot\RegisterConversation;

try {
    $config = Config::fromEnv(dirname(__DIR__, 2));
} catch (Botforge\ConfigException $e) {
    fwrite(STDERR, 'Config error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$bot = new Bot($config);
$products = [
    101 => 'Laptop',
    102 => 'Mobile Phone',
    103 => 'Headphones',
    104 => 'Keyboard',
    105 => 'Mouse',
    106 => 'Monitor',
    107 => 'Webcam',
    108 => 'Microphone',
    109 => 'Speaker',
    110 => 'Tablet',
    111 => 'Smart Watch',
    112 => 'Power Bank',
];

$showProductsPage = static function (
    Context $ctx,
    int $page,
    bool $edit = false
) use ($products): void {

    $keyboard = Keyboard::paginate(
        items: $products,
        page: $page,
        perPage: 5,
        prefix: 'products',
        columns: 2
    );

    $totalPages = max(1, (int) ceil(count($products) / 5));

    $text = "Our Products\nPage {$page} of {$totalPages}";

    if ($edit) {
        $ctx->edit($text, ['keyboard' => $keyboard]);
    } else {
        $ctx->reply($text, ['keyboard' => $keyboard]);
    }
};
$bot->command('products', function (Context $ctx) use ($showProductsPage): void {
    $showProductsPage($ctx, 1);
});

// A function that makes a NEW keyboard each time (keyboards are not meant to be shared).
$mainMenu = static fn () => Keyboard::inline()->row(
    Button::callback('Profile', 'profile'),
    Button::callback('Help', 'help')
);

$bot->command('register', function (Context $ctx) use ($bot): void {
    $bot->startConversation($ctx, new RegisterConversation());
});

$bot->command('start', function (Context $ctx, string $payload = '') use ($mainMenu): void {
    $text = 'Hello, ' . Html::bold($ctx->from()?->firstName ?? 'friend') . '!';

    if ($payload !== '') {
        $text .= "\nYou came with the code: " . Html::code($payload);
    }

    $ctx->reply($text, ['keyboard' => $mainMenu()]);
});

$bot->callback('profile', function (Context $ctx): void {
    $ctx->answerCallback(); // stops the loading spinner on the button

    $user = $ctx->from();
    $username = $user?->username !== null ? Html::escape('@' . $user->username) : 'none';

    $ctx->edit(
        Html::bold('Your profile') . "\nName: " . Html::escape($user?->fullName() ?? '?') . "\nUsername: " . $username,
        ['keyboard' => Keyboard::inline()->row(Button::callback('« Back', 'menu'))]
    );
});

$bot->callback('menu', function (Context $ctx) use ($mainMenu): void {
    $ctx->answerCallback();
    $ctx->edit('Choose an option:', ['keyboard' => $mainMenu()]);
});

$bot->callback('help', function (Context $ctx): void {
    $ctx->answerCallback();
    $ctx->reply('Send me any text and I will repeat it.');
});

$bot->command('name', function (Context $ctx) {
    $session = $ctx->session();

    $session->set('name', 'Shakiba');

    $ctx->reply('Name saved!');
});

$bot->command('myname', function (Context $ctx) {
    $name = $ctx->session()->get('name', 'Unknown');

    $ctx->reply("Your name is: {$name}");
});

// Any other text message: repeat it. Html::escape protects us from "<" and "&" in what people write.
$bot->on('text', fn (Context $ctx) => $ctx->reply('You said: ' . Html::escape($ctx->text() ?? '')));

$bot->run();
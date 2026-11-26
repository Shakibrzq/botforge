<?php

declare(strict_types=1);

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


// ==========================================
// TRANSLATION
// ==========================================

$translate = static function (
    Context $ctx,
    string $key,
    array $replace = []
): string {

    $language = $ctx->session()->get('language', 'en');

    $translations = [
        'en' => require __DIR__ . '/langen.php',
        'fa' => require __DIR__ . '/langfa.php',
    ];

    $text = $translations[$language][$key]
        ?? $translations['en'][$key]
        ?? $key;

    foreach ($replace as $placeholder => $value) {
        $text = str_replace(
            ':' . $placeholder,
            (string) $value,
            $text
        );
    }

    return $text;
};


// ==========================================
// MAIN MENU
// ==========================================

$mainMenu = static fn () => Keyboard::inline()->row(
    Button::callback('Profile', 'profile'),
    Button::callback('Help', 'help')
);


// ==========================================
// HELP
// ==========================================

$showHelp = static function (Context $ctx) use ($translate): void {
    $ctx->reply($translate($ctx, 'help'));
};


// ==========================================
// PRODUCTS
// ==========================================

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
) use ($products, $translate): void {

    $perPage = 5;

    $totalPages = max(
        1,
        (int) ceil(count($products) / $perPage)
    );

    $page = min(max($page, 1), $totalPages);

    $keyboard = Keyboard::paginate(
        items: $products,
        page: $page,
        perPage: $perPage,
        prefix: 'products',
        columns: 2
    );

    $title = $translate($ctx, 'products_title');

    $pageText = $translate($ctx, 'products_page', [
        'page' => $page,
        'total' => $totalPages,
    ]);

    $text = $title . "\n" . $pageText;

    if ($edit) {
        $ctx->edit($text, ['keyboard' => $keyboard]);
    } else {
        $ctx->reply($text, ['keyboard' => $keyboard]);
    }
};


// ==========================================
// START COMMAND
// ==========================================

$bot->command('start', function (
    Context $ctx,
    string $payload = ''
) use ($mainMenu, $translate): void {

    $text = $translate($ctx, 'welcome');

    if ($payload !== '') {
        $text .= "\nYou came with the code: " . Html::code($payload);
    }

    $ctx->reply($text, [
        'keyboard' => $mainMenu(),
    ]);
});


// ==========================================
// HELP COMMAND
// ==========================================

$bot->command('help', function (Context $ctx) use ($showHelp): void {
    $showHelp($ctx);
});


// ==========================================
// REGISTER COMMAND
// ==========================================

$bot->command('register', function (Context $ctx) use ($bot): void {
    $bot->startConversation($ctx, new RegisterConversation());
});


// ==========================================
// PRODUCTS COMMAND
// ==========================================

$bot->command('products', function (
    Context $ctx
) use ($showProductsPage): void {

    $showProductsPage($ctx, 1);
});

$bot->callback('products:page:{page}', function (
    Context $ctx,
    string $page
) use ($showProductsPage): void {

    $ctx->answerCallback();

    $showProductsPage($ctx, (int) $page, true);
});


// ==========================================
// PROFILE CALLBACK
// ==========================================

$bot->callback('profile', function (Context $ctx): void {

    $ctx->answerCallback();

    $user = $ctx->from();

    $username = $user?->username !== null
        ? Html::escape('@' . $user->username)
        : 'none';

    $ctx->edit(
        Html::bold('Your profile')
        . "\nName: " . Html::escape($user?->fullName() ?? '?')
        . "\nUsername: " . $username,
        [
            'keyboard' => Keyboard::inline()->row(
                Button::callback('« Back', 'menu')
            ),
        ]
    );
});


// ==========================================
// MENU CALLBACK
// ==========================================

$bot->callback('menu', function (
    Context $ctx
) use ($mainMenu): void {

    $ctx->answerCallback();

    $ctx->edit('Choose an option:', [
        'keyboard' => $mainMenu(),
    ]);
});


// ==========================================
// HELP CALLBACK
// ==========================================

$bot->callback('help', function (
    Context $ctx
) use ($showHelp): void {

    $ctx->answerCallback();

    $showHelp($ctx);
});


// ==========================================
// NAME COMMANDS
// ==========================================

$bot->command('name', function (Context $ctx): void {

    $ctx->session()->set('name', 'Shakiba');

    $ctx->reply('Name saved!');
});

$bot->command('myname', function (Context $ctx): void {

    $name = $ctx->session()->get('name', 'Unknown');

    $ctx->reply("Your name is: {$name}");
});


// ==========================================
// LANGUAGE COMMAND
// ==========================================

$bot->command('language', function (
    Context $ctx
) use ($translate): void {

    $keyboard = Keyboard::inline()->row(
        Button::callback('🇬🇧 English', 'language:en'),
        Button::callback('🇮🇷 فارسی', 'language:fa')
    );

    $ctx->reply(
        $translate($ctx, 'choose_language'),
        ['keyboard' => $keyboard]
    );
});


// ==========================================
// LANGUAGE CALLBACK
// ==========================================

$bot->callback('language:{language}', function (
    Context $ctx,
    string $language
) use ($translate): void {

    if (!in_array($language, ['en', 'fa'], true)) {
        $ctx->answerCallback();
        return;
    }

    $ctx->session()->set('language', $language);

    $ctx->answerCallback();

    $ctx->reply($translate($ctx, 'language_changed'));
});


// ==========================================
// TEXT HANDLER
// ==========================================

$bot->on('text', function (Context $ctx): void {

    $text = $ctx->text() ?? '';

    $ctx->reply(
        'You said: ' . Html::escape($text)
    );
});


// ==========================================
// RUN BOT
// ==========================================

$bot->run();
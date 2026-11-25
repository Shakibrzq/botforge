<?php

declare(strict_types=1);

// Tests the keyboard builders. No internet, no real token.
// Run it with:  php examples/keyboard-test.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/helpers.php';

use Botforge\Context\Context;
use Botforge\Keyboard\Button;
use Botforge\Keyboard\Keyboard;
use Botforge\Keyboard\ReplyMarkup;
use Botforge\Routing\Matchers;

$show = function (string $label, ReplyMarkup $markup): void {
    echo '   ' . $label . PHP_EOL . '     ' . json_encode($markup->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
};

// ---------- 1) Inline keyboard ----------
echo '1) Inline keyboard' . PHP_EOL;
$show('callback + url + web app', Keyboard::inline()
    ->row(Button::callback('Profile', 'profile'), Button::callback('Help', 'help'))
    ->row(Button::url('Docs', 'https://example.com'), Button::webApp('Shop', 'https://example.com/app')));

// ---------- 2) Reply keyboards ----------
echo PHP_EOL . '2) Reply keyboards' . PHP_EOL;
$show('contact + location + options', Keyboard::reply()
    ->row(Button::requestContact('Share phone'), Button::requestLocation('Share location'))
    ->row(Button::plain('Cancel'))
    ->oneTime()
    ->placeholder('Tap a button'));
$show('remove', Keyboard::remove());
$show('force reply', Keyboard::forceReply('Your name?'));

// ---------- 3) Pagination ----------
echo PHP_EOL . '3) Pagination (5 items, 2 per page)' . PHP_EOL;
$items = ['Apple', 'Banana', 'Cherry', 'Date', 'Elderberry'];

$show('page 1', Keyboard::paginate($items, 1, 2, 'products'));
$show('page 2', Keyboard::paginate($items, 2, 2, 'products'));
$show('page 3', Keyboard::paginate($items, 3, 2, 'products'));
$show('page 99 (corrected to 3)', Keyboard::paginate($items, 99, 2, 'products'));
$show('page 1, two columns', Keyboard::paginate($items, 1, 2, 'products', 2));

// ---------- 4) Keyboard + router fit together ----------
echo PHP_EOL . '4) The buttons match the router patterns' . PHP_EOL;

$sent = [];
$telegram = fakeTelegram($sent);

$pageMatcher = Matchers::callback('products:page:{page}');
$itemMatcher = Matchers::callback('products:item:{id}');

echo '   products:page:2 => ' . json_encode($pageMatcher(new Context(callbackUpdate('products:page:2'), $telegram))) . PHP_EOL;
echo '   products:item:4 => ' . json_encode($itemMatcher(new Context(callbackUpdate('products:item:4'), $telegram))) . PHP_EOL;

// ---------- 5) Using a keyboard with $ctx->reply() ----------
echo PHP_EOL . '5) $ctx->reply() with a keyboard' . PHP_EOL;

$ctx = new Context(textUpdate('/start'), $telegram);
$ctx->reply('Pick one', [
    'keyboard' => Keyboard::inline()->row(Button::callback('Profile', 'profile'), Button::callback('Help', 'help')),
]);
echo '   sent: ' . $sent[0] . PHP_EOL;

// ---------- 6) Mistakes are caught early ----------
echo PHP_EOL . '6) Mistakes are caught early' . PHP_EOL;

$mistakes = [
    'callback data too long' => fn () => Button::callback('Too long', str_repeat('x', 65)),
    'empty button text' => fn () => Button::callback('', 'x'),
    'javascript: link' => fn () => Button::url('Bad', 'javascript:alert(1)'),
    'web app without https' => fn () => Button::webApp('Bad', 'http://example.com'),
    'empty row' => fn () => Keyboard::inline()->row(),
    '9 buttons in a row' => fn () => Keyboard::inline()->row(...array_map(
        fn (int $n): Button => Button::callback('B' . $n, 'b' . $n),
        range(1, 9)
    )),
];

foreach ($mistakes as $label => $mistake) {
    try {
        $mistake();
    } catch (InvalidArgumentException $e) {
        echo '   ' . str_pad($label, 24) . ' => ' . $e->getMessage() . PHP_EOL;
    }
}
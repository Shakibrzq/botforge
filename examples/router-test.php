<?php

declare(strict_types=1);

// Tests the Router with a fake Telegram. No internet, no real token.
// Run it with:  php examples/router-test.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/helpers.php';

use Botforge\Container\Container;
use Botforge\Context\Context;
use Botforge\Entities\ChatMemberUpdated;
use Botforge\Entities\Update;
use Botforge\Middleware\Pipeline;
use Botforge\Routing\Router;

/** An invokable class used as a handler. */
final class HelpHandler
{
    public function __invoke(Recorder $rec): void
    {
        $rec->add('help from invokable class');
    }
}

// ---------- Setup ----------
$container = new Container();
$container->singleton(Recorder::class, fn () => new Recorder());
$rec = $container->get(Recorder::class);

$sent = [];
$telegram = fakeTelegram($sent);

$router = new Router($container, new Pipeline($container));

// ---------- Routes (the FIRST matching one wins) ----------
$router->command('start', function (Recorder $rec, string $payload = '(none)') {
    $rec->add('start payload=' . $payload);
});
$router->command('help', HelpHandler::class);
$router->text('hello', fn (Recorder $rec) => $rec->add('exact text'));
$router->contains('price', fn (Recorder $rec) => $rec->add('contains "price"'));
$router->regex('/^buy (?<qty>\d+) (?<item>\w+)$/', function (Recorder $rec, int $qty, string $item) {
    $rec->add('buy qty=' . $qty . ' (' . gettype($qty) . ') item=' . $item);
});
$router->callback('menu', fn (Recorder $rec) => $rec->add('callback menu'));
$router->callback('product:{id}', function (Recorder $rec, int $id) {
    $rec->add('callback product id=' . $id . ' (' . gettype($id) . ')');
});
$router->on('photo', fn (Recorder $rec) => $rec->add('photo message'));
$router->on('contact', fn (Recorder $rec) => $rec->add('contact message'));
$router->inlineQuery(fn (Recorder $rec, string $query) => $rec->add('inline query=' . $query));
$router->myChatMember(function (Recorder $rec, ChatMemberUpdated $change) {
    $rec->add('bot status: ' . $change->oldStatus . ' -> ' . $change->newStatus);
});

// A group: these routes only match in private chats.
$router->group(
    ['when' => fn (Context $ctx): bool => $ctx->chat()?->isPrivate() === true],
    function (Router $r): void {
        $r->command('secret', fn (Recorder $rec) => $rec->add('secret (private chats only)'));
    }
);

$router->text('dup', fn (Recorder $rec) => $rec->add('first'));
$router->text('dup', fn (Recorder $rec) => $rec->add('second'));
$router->fallback(fn (Recorder $rec) => $rec->add('fallback'));

// ---------- Helpers for printing ----------
$show = function (string $label, string $result): void {
    echo '   ' . str_pad($label, 26) . ' => ' . $result . PHP_EOL;
};

$try = function (string $label, Update $update) use ($router, $telegram, $rec, $show): void {
    $router->dispatch(new Context($update, $telegram));
    $show($label, $rec->flush());
};

$botUser = ['id' => 999, 'is_bot' => true, 'first_name' => 'MyBot'];

// ---------- 1) Which handler runs? ----------
echo '1) Which handler runs?' . PHP_EOL;

$try('/start', textUpdate('/start'));
$try('/start ref_123', textUpdate('/start ref_123'));
$try('/help', textUpdate('/help'));
$try('hello', textUpdate('hello'));
$try('What is the PRICE?', textUpdate('What is the PRICE?'));
$try('buy 3 apples', textUpdate('buy 3 apples'));
$try('button "menu"', callbackUpdate('menu'));
$try('button "product:42"', callbackUpdate('product:42'));
$try('photo message', messageUpdate(['photo' => [['file_id' => 'a', 'file_unique_id' => 'b', 'width' => 90, 'height' => 60]]]));
$try('contact message', messageUpdate(['contact' => ['phone_number' => '+100', 'first_name' => 'Ali']]));
$try('inline query "cats"', Update::fromArray([
    'update_id' => 3,
    'inline_query' => ['id' => '9', 'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ali'], 'query' => 'cats', 'offset' => ''],
]));
$try('bot blocked by user', Update::fromArray([
    'update_id' => 4,
    'my_chat_member' => [
        'chat' => ['id' => 111, 'type' => 'private'],
        'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ali'],
        'date' => 1700000000,
        'old_chat_member' => ['status' => 'member', 'user' => $botUser],
        'new_chat_member' => ['status' => 'kicked', 'user' => $botUser],
    ],
]));
$try('/secret in private chat', textUpdate('/secret'));
$try('/secret in a group', textUpdate('/secret', 'supergroup'));
$try('"dup" (two routes)', textUpdate('dup'));
$try('random words', textUpdate('random words'));

// ---------- 2) No fallback registered ----------
echo PHP_EOL . '2) A router without a fallback' . PHP_EOL;

$bare = new Router($container, new Pipeline($container));
$handled = $bare->dispatch(new Context(textUpdate('anything'), $telegram));
$show('dispatch() returned', $handled ? 'true' : 'false');

// ---------- 3) Mistakes are caught when registering ----------
echo PHP_EOL . '3) Mistakes are caught early' . PHP_EOL;

$mistakes = [
    'unknown handler class' => fn () => $router->command('x', 'NoSuchClass'),
    'broken regex' => fn () => $router->regex('/(/', fn () => null),
    'typo in message type' => fn () => $router->on('foto', fn () => null),
];

foreach ($mistakes as $label => $mistake) {
    try {
        $mistake();
    } catch (InvalidArgumentException $e) {
        $show($label, $e->getMessage());
    }
}
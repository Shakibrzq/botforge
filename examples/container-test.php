<?php

declare(strict_types=1);

// Tests the container. No internet and no real token needed.
// Run it with:  php examples/container-test.php

require __DIR__ . '/../vendor/autoload.php';

use Botforge\Api\TelegramClient;
use Botforge\Config;
use Botforge\Container\Container;
use Botforge\Container\ContainerException;
use Botforge\Context\Context;
use Botforge\CoreServiceProvider;
use Botforge\Entities\Update;
use Psr\Container\ContainerInterface;

// ---------- Sample classes (they depend on each other) ----------
interface Greeter
{
    public function greet(string $name): string;
}

final class EnglishGreeter implements Greeter
{
    public function greet(string $name): string
    {
        return 'Hello, ' . $name . '!';
    }
}

final class WelcomeService
{
    public function __construct(private Greeter $greeter, private Config $config)
    {
    }

    public function welcome(string $name): string
    {
        return $this->greeter->greet($name) . ' (admins: ' . count($this->config->adminIds) . ')';
    }
}

final class Plain
{
}

final class StartHandler
{
    public function __construct(private WelcomeService $welcome)
    {
    }

    public function __invoke(Context $ctx): string
    {
        return $this->welcome->welcome($ctx->from()?->firstName ?? 'friend');
    }

    public function profile(Context $ctx, int $id): string
    {
        return 'profile #' . $id;
    }
}

final class CircularA
{
    public function __construct(CircularB $b)
    {
    }
}

final class CircularB
{
    public function __construct(CircularA $a)
    {
    }
}

final class NeedsString
{
    public function __construct(string $apiKey)
    {
    }
}

// ---------- Setup ----------
$config = Config::fromArray(['BOT_TOKEN' => '123456:TESTTOKEN', 'ADMIN_IDS' => '1,2']);

$container = new Container();
$container->register(new CoreServiceProvider($config)); // Config + TelegramClient
$container->bind(Greeter::class, EnglishGreeter::class); // "when someone wants a Greeter, use this class"

// Things that belong to ONE update are stored as ready objects:
$update = Update::fromArray([
    'update_id' => 1,
    'message' => [
        'message_id' => 5,
        'date' => 1700000000,
        'chat' => ['id' => 111, 'type' => 'private'],
        'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ali'],
        'text' => '/start',
    ],
]);
$container->instance(Update::class, $update);
$container->instance(Context::class, new Context($update, $container->get(TelegramClient::class)));

// ---------- 1) Autowiring ----------
echo '1) Autowiring' . PHP_EOL;

$service = $container->get(WelcomeService::class); // needs Greeter (bound) and Config (stored)
echo '   ' . $service->welcome('Ali') . PHP_EOL;
echo '   is a PSR-11 container: ' . ($container instanceof ContainerInterface ? 'yes' : 'no') . PHP_EOL;

// ---------- 2) One object or a new one? ----------
echo PHP_EOL . '2) One object or a new one?' . PHP_EOL;

$container->singleton('shared.plain', fn () => new Plain());
echo '   not registered: same object twice? ' . ($container->get(Plain::class) === $container->get(Plain::class) ? 'yes' : 'no') . PHP_EOL;
echo '   singleton     : same object twice? ' . ($container->get('shared.plain') === $container->get('shared.plain') ? 'yes' : 'no') . PHP_EOL;

// ---------- 3) Calling handlers ----------
echo PHP_EOL . '3) Calling handlers' . PHP_EOL;

echo '   closure : ' . $container->call(
    function (Context $ctx, string $id): string {
        return 'got Context (text=' . $ctx->text() . ') and id=' . $id;
    },
    ['id' => '7']
) . PHP_EOL;

echo '   invokable class: ' . $container->call(StartHandler::class) . PHP_EOL;

// "42" is text, but the method wants an int: the container converts it.
echo '   [Class, method]: ' . $container->call([StartHandler::class, 'profile'], ['id' => '42']) . PHP_EOL;

// ---------- 4) Helpful errors ----------
echo PHP_EOL . '4) Helpful errors' . PHP_EOL;

$empty = new Container();
$mistakes = [
    'interface not bound' => fn () => $empty->get(Greeter::class),
    'circular' => fn () => $empty->get(CircularA::class),
    'missing value' => fn () => $empty->get(NeedsString::class),
    'unknown name' => fn () => $empty->get('nothing.here'),
];

foreach ($mistakes as $label => $try) {
    try {
        $try();
    } catch (ContainerException $e) {
        echo '   ' . $label . ' (' . (new ReflectionClass($e))->getShortName() . '): ' . $e->getMessage() . PHP_EOL;
    }
}
<?php

declare(strict_types=1);

// Tests the Middleware pipeline and the built-in middleware with a fake Telegram.
// Run it with:  php examples/middleware-test.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/helpers.php';

use Botforge\Api\TelegramClient;
use Botforge\Config;
use Botforge\Container\Container;
use Botforge\Context\Context;
use Botforge\CoreServiceProvider;
use Botforge\Entities\Update;
use Botforge\Middleware\AdminOnlyMiddleware;
use Botforge\Middleware\ChannelMembershipMiddleware;
use Botforge\Middleware\ChatTypeMiddleware;
use Botforge\Middleware\LoggingMiddleware;
use Botforge\Middleware\MaintenanceMiddleware;
use Botforge\Middleware\Middleware;
use Botforge\Middleware\Pipeline;
use Psr\Log\AbstractLogger;

/** A custom middleware written by "us": it only leaves a note, then lets the update go on. */
final class StampMiddleware implements Middleware
{
    public function __construct(private Recorder $rec)
    {
    }

    public function process(Context $ctx, Closure $next): mixed
    {
        $this->rec->add('stamp');

        return $next($ctx);
    }
}

/** A logger that keeps the records in memory. */
final class ArrayLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<mixed>}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [(string) $level, (string) $message, $context];
    }
}

// ---------- Setup ----------
$config = Config::fromArray(['BOT_TOKEN' => '123456:TESTTOKEN', 'ADMIN_IDS' => '999']);

$container = new Container();
$container->register(new CoreServiceProvider($config));
$container->singleton(Recorder::class, fn () => new Recorder());

$rec = $container->get(Recorder::class);
$pipeline = new Pipeline($container);

$sent = [];
$telegram = fakeTelegram($sent);
$ctx = new Context(textUpdate('hello'), $telegram);

$handler = function (Context $ctx) use ($rec): void {
    $rec->add('HANDLER');
};

// A layer that writes a note before and after the inner part.
$layer = function (string $name) use ($rec): Closure {
    return function (Context $ctx, Closure $next) use ($rec, $name) {
        $rec->add($name . ' before');
        $result = $next($ctx);
        $rec->add($name . ' after');

        return $result;
    };
};

// ---------- 1) The onion ----------
echo '1) The onion: first middleware = outermost layer' . PHP_EOL;
$pipeline->run($ctx, [$layer('A'), $layer('B')], $handler);
echo '   ' . $rec->flush() . PHP_EOL;

// ---------- 2) A middleware can stop everything ----------
echo PHP_EOL . '2) A middleware that does not call $next' . PHP_EOL;
$blocker = function (Context $ctx, Closure $next) use ($rec): void {
    $rec->add('BLOCKER (does not call $next)');
};
$pipeline->run($ctx, [$layer('A'), $blocker, $layer('B')], $handler);
echo '   ' . $rec->flush() . PHP_EOL;

// ---------- 3) Middleware given as a class name ----------
echo PHP_EOL . '3) Middleware as a class name (the container creates it)' . PHP_EOL;
$pipeline->run($ctx, [StampMiddleware::class], $handler);
echo '   ' . $rec->flush() . PHP_EOL;

// ---------- 4) The built-in middleware ----------
echo PHP_EOL . '4) Built-in middleware' . PHP_EOL;

$check = function (string $label, array $middleware, Update $update, TelegramClient $client) use ($pipeline, $rec, $handler, &$sent): void {
    $pipeline->run(new Context($update, $client), $middleware, $handler);
    echo '   ' . str_pad($label, 32) . ' => ' . $rec->flush() . PHP_EOL;

    foreach ($sent as $line) {
        echo '       sent: ' . $line . PHP_EOL;
    }

    $sent = [];
};

$check('ChatType(private), private chat', [new ChatTypeMiddleware('private')], textUpdate('hi'), $telegram);
$check('ChatType(private), in a group', [new ChatTypeMiddleware('private')], textUpdate('hi', 'supergroup'), $telegram);

$check('AdminOnly, admin 999', [AdminOnlyMiddleware::class], textUpdate('hi', 'private', 999), $telegram);
$check('AdminOnly, normal user 111', [AdminOnlyMiddleware::class], textUpdate('hi'), $telegram);
$check('AdminOnly, button press by 111', [AdminOnlyMiddleware::class], callbackUpdate('x'), $telegram);

$flag = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'botforge-test-maintenance.flag';
$maintenance = new MaintenanceMiddleware($flag, $config->adminIds);

$check('Maintenance OFF, user 111', [$maintenance], textUpdate('hi'), $telegram);
file_put_contents($flag, 'on'); // switch maintenance mode ON
$check('Maintenance ON, user 111', [$maintenance], textUpdate('hi'), $telegram);
$check('Maintenance ON, admin 999', [$maintenance], textUpdate('hi', 'private', 999), $telegram);
unlink($flag); // switch it OFF again

$left = fakeTelegram($sent, ['getChatMember' => '{"ok":true,"result":{"status":"left"}}']);
$member = fakeTelegram($sent, ['getChatMember' => '{"ok":true,"result":{"status":"member"}}']);
$channel = new ChannelMembershipMiddleware('@mychannel');

$check('ChannelMembership, user left', [$channel], textUpdate('hi'), $left);
$check('ChannelMembership, user member', [$channel], textUpdate('hi'), $member);

// ---------- 5) Logging ----------
echo PHP_EOL . '5) LoggingMiddleware' . PHP_EOL;

$logger = new ArrayLogger();
$logging = new LoggingMiddleware($logger);

$pipeline->run($ctx, [$logging], $handler);
$rec->flush();

try {
    $pipeline->run($ctx, [$logging], function (Context $ctx): void {
        throw new RuntimeException('boom');
    });
} catch (RuntimeException $e) {
    echo '   (the exception was thrown again: ' . $e->getMessage() . ')' . PHP_EOL;
}

foreach ($logger->records as [$level, $message, $context]) {
    $hasTime = array_key_exists('duration_ms', $context);
    unset($context['duration_ms']);

    echo '   [' . $level . '] ' . $message . ' ' . json_encode($context) . ($hasTime ? ' + duration_ms' : '') . PHP_EOL;
}
<?php

declare(strict_types=1);

namespace Botforge;

use Botforge\Api\TelegramClient;
use Botforge\Container\Container;
use Botforge\Container\ServiceProvider;
use Botforge\Context\Context;
use Botforge\Entities\Update;
use Botforge\Middleware\LoggingMiddleware;
use Botforge\Middleware\Middleware;
use Botforge\Middleware\Pipeline;
use Botforge\Routing\Route;
use Botforge\Routing\Router;
use Botforge\Runner\PollingRunner;
use Botforge\Runner\WebhookRunner;
use Closure;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;
use Botforge\Storage\StorageInterface;
use Botforge\Conversation\Conversation;
use Botforge\Conversation\ConversationManager;

/**
 * The front door of the framework. You create one Bot, tell it what to do, and call run().
 *
 *   $bot = new Bot(Config::fromEnv(__DIR__));
 *   $bot->command('start', fn (Context $ctx) => $ctx->reply('Hello!'));
 *   $bot->run();
 */
final class Bot
{
    private readonly Container $container;
    private readonly TelegramClient $telegram;
    private readonly LoggerInterface $logger;
    private readonly Pipeline $pipeline;
    private readonly Router $router;
    private readonly StorageInterface $storage;
    private readonly ConversationManager $conversationManager;

    /** @var list<Middleware|Closure|string> Global middleware: wraps EVERY update */
    private array $middleware;

    /**
     * @param TelegramClient|null $telegram Ready-made client (tests pass a fake one)
     * @param LoggerInterface|null $logger Ready-made logger (null = Monolog, configured from Config)
     */
    public function __construct(
        private readonly Config $config,
        ?TelegramClient $telegram = null,
        ?LoggerInterface $logger = null,
    ) {
        //در نهایت لاگ ها میرن تو بات لاگ استورج
        $this->container = new Container();
        $this->container->register(new CoreServiceProvider($config, $telegram, $logger));

        $this->telegram = $this->container->get(TelegramClient::class);
        $this->logger = $this->container->get(LoggerInterface::class);
        $this->pipeline = $this->container->get(Pipeline::class);
        $this->router = $this->container->get(Router::class);
        $this->storage = $this->container->get(StorageInterface::class);
        $this->conversationManager = new ConversationManager();

        // Logging is always the OUTERMOST layer, so it sees every update and every failure.
        $this->middleware = [new LoggingMiddleware($this->logger)];
    }

    // ------------------------------------------------------------------
    // Access to the parts
    // ------------------------------------------------------------------

    /** The container: register your own services here (e.g. $bot->container()->singleton(...)). */
    public function container(): Container
    {
        return $this->container;
    }
    /**
 * Starts a conversation for the current user.
 */
public function startConversation(
    Context $context,
    Conversation $conversation,
): void {
    $this->conversationManager->start($context, $conversation);
}
  
    public function telegram(): TelegramClient
    {
        return $this->telegram;
    }

    /** Lets a service provider (a "setup box") register its services. */
    public function register(ServiceProvider $provider): self
    {
        $this->container->register($provider);

        return $this;
    }

    /**
     * Adds GLOBAL middleware: it wraps every update, even those that match no route.
     *
     * @param Middleware|Closure|string ...$middleware
     */
    public function middleware(Middleware|Closure|string ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    // ------------------------------------------------------------------
    // Routes (they all forward to the Router, see Router for details)
    // ------------------------------------------------------------------

    /** @param callable|array<int, mixed>|string $handler */
    public function command(string $name, callable|array|string $handler): Route
    {
        return $this->router->command($name, $handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function text(string $text, callable|array|string $handler): Route
    {
        return $this->router->text($text, $handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function contains(string $needle, callable|array|string $handler): Route
    {
        return $this->router->contains($needle, $handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function regex(string $pattern, callable|array|string $handler): Route
    {
        return $this->router->regex($pattern, $handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function callback(string $pattern, callable|array|string $handler): Route
    {
        return $this->router->callback($pattern, $handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function on(string $messageType, callable|array|string $handler): Route
    {
        return $this->router->on($messageType, $handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function inlineQuery(callable|array|string $handler): Route
    {
        return $this->router->inlineQuery($handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function myChatMember(callable|array|string $handler): Route
    {
        return $this->router->myChatMember($handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function chatMember(callable|array|string $handler): Route
    {
        return $this->router->chatMember($handler);
    }

    /** @param callable|array<int, mixed>|string $handler */
    public function fallback(callable|array|string $handler): Route
    {
        return $this->router->fallback($handler);
    }

    /**
     * Routes that share middleware or a condition. Inside, use the Bot you receive:
     *
     *   $bot->group(['middleware' => [AdminOnlyMiddleware::class]], function (Bot $bot) {
     *       $bot->command('stats', ...);
     *   });
     *
     * @param array{middleware?: list<Middleware|Closure|string>, when?: Closure(Context): bool} $options
     * @param Closure(Bot): void $define
     */
    public function group(array $options, Closure $define): void
    {
        $this->router->group($options, function () use ($define): void {
            $define($this);
        });
    }

    // ------------------------------------------------------------------
    // Running
    // ------------------------------------------------------------------

    /**
     * Handles ONE update: global middleware -> router -> route middleware -> handler.
     * It never throws: errors are logged, so one bad update cannot stop the bot.
     */
    public function handle(Update $update): void
    {
        //برای اپدیت یک کانتکست میسازه

        $ctx = new Context($update, $this->telegram, $this->storage);

        try {
            $this->pipeline->run(
    $ctx,
    $this->middleware,
    function (Context $ctx): bool {
        // Check whether the user has an active conversation.
        if (
            $ctx->text() !== null
            && $this->conversationManager->handle($ctx, $ctx->text())
        ) {
            return true;
        }

        // If no active conversation handled the message,
        // continue with normal routing.
        return $this->router->dispatch($ctx);
    }
);
            
        } catch (Throwable) {
            // LoggingMiddleware (the outermost layer) has already written the error to the log.
            // We must not let it escape: in webhook mode it would become an HTTP 500 error, and
            // Telegram would send the same failing update again and again.
        }
    }

    /**
     * Starts the bot the right way for where it runs:
     * in a terminal (CLI) -> long polling; behind a web server (PHP-FPM, Apache, ...) -> webhook.
     */
    //اکه تو سی ال ای ایندکسو اجرا کنم بات با لانگ پولینگ اجرا میشه و اپدیت میگیره
    //اگه رو سرور اجرا کنیم ینی قبلش وب هوک ست کرده باشیم اپدیت از طریق وب هوک میاد
    public function run(): void
    {
        if (PHP_SAPI === 'cli') {
            $this->runPolling();

            return;
        }

        $this->runWebhook();
    }

    /** Answers ONE webhook request (the current HTTP request). */
    public function runWebhook(): void
    {
        (new WebhookRunner($this->config, $this->logger, $this->handle(...)))->run();
    }

    /** Asks Telegram for updates in an endless loop, until stopped (Ctrl+C or SIGTERM). */
    public function runPolling(): void
    {
        (new PollingRunner($this->telegram, $this->handle(...), $this->logger))->run();
    }

    // ------------------------------------------------------------------
    // Webhook setup (use these from a small script; there is no CLI tool yet)
    // ------------------------------------------------------------------

    /**
     * Tells Telegram to send updates to WEBHOOK_URL, with WEBHOOK_SECRET as the secret token.
     *
     * @param array<string, mixed> $extra More setWebhook parameters, e.g. ['allowed_updates' => [...]]
     */
    public function registerWebhook(array $extra = []): bool
    {
        $url = $this->config->webhookUrl;

        if ($url === null) {
            throw new LogicException('WEBHOOK_URL is not set in your .env file.');
        }

        return $this->telegram->setWebhook($url, ['secret_token' => $this->config->secretToken] + $extra);
    }

    public function removeWebhook(bool $dropPendingUpdates = false): bool
    {
        return $this->telegram->deleteWebhook($dropPendingUpdates);
    }
}
<?php

declare(strict_types=1);

namespace Botforge;

use Botforge\Api\TelegramClient;
use Botforge\Container\Container;
use Botforge\Container\ServiceProvider;
use Botforge\Middleware\Pipeline;
use Botforge\Routing\Router;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Registers the basic things every bot needs: Config, Telegram client, logger, pipeline and router.
 *
 * $telegram and $logger can be handed in ready-made (tests do this with fakes).
 * If they are null, the real ones are built from the Config.
 */
final class CoreServiceProvider implements ServiceProvider
{
    public function __construct(
        private readonly Config $config,
        private readonly ?TelegramClient $telegram = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function register(Container $container): void
    {
        $container->instance(Config::class, $this->config);

        if ($this->telegram !== null) {
            $container->instance(TelegramClient::class, $this->telegram);
        } else {
            // Built once, the first time someone asks for it.
            $container->singleton(
                TelegramClient::class,
                fn (): TelegramClient => TelegramClient::fromConfig($this->config)
            );
        }

        if ($this->logger !== null) {
            $container->instance(LoggerInterface::class, $this->logger);
        } else {
            $container->singleton(LoggerInterface::class, fn (): LoggerInterface => $this->createLogger());
        }

        // "Class name -> same class name" means: build it by autowiring, but only once.
        $container->singleton(Pipeline::class, Pipeline::class);
        $container->singleton(Router::class, Router::class);
    }
//لاگ هارو میزاره تو استورجی که تو کانفیگ تعیین کردیم
// زمانیکه بات دات پی اچ پی 
// دستور زیر رو اجرا میکنه
//$this->container->register(
//     new CoreServiceProvider($config, $telegram, $logger)
// );

    private function createLogger(): LoggerInterface
    {
        $level = Level::fromName($this->config->logLevel);

        $logger = new Logger('botforge');
        $logger->pushHandler(new StreamHandler($this->config->storagePath . '/logs/bot.log', $level));

        if (PHP_SAPI === 'cli') {
            // While you run the bot in a terminal, also show the log there.
            $logger->pushHandler(new StreamHandler('php://stderr', $level));
        }

        return $logger;
    }
}
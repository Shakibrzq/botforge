<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Botforge\Config;
use Botforge\Container\Container;
use Botforge\CoreServiceProvider;
use Botforge\Storage\StorageInterface;

try {
    // انتخاب Driver معتبر، بدون تنظیم DB_DSN
    $config = Config::fromArray([
        'BOT_TOKEN' => '123456:TestToken',
        'STORAGE_DRIVER' => 'pdo',
    ]);

    // ساخت Container
    $container = new Container();

    // ثبت سرویس‌ها
    $container->register(new CoreServiceProvider($config));

    // درخواست Storage
    $container->get(StorageInterface::class);

    echo "ERROR: Storage was created without DB_DSN.\n";

} catch (RuntimeException $e) {

    if ($e->getMessage() !== 'DB_DSN is not configured.') {
        throw $e;
    }

    echo "Missing DB_DSN rejected successfully.\n";
    echo "Error message: " . $e->getMessage() . "\n";
}
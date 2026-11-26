<?php
//مطمعن شیم وقتی کاربر تو .env
//STORAGE_DRIVER = file
//قرار داد فریم ورک بصورت خودکار فایل استورج رو در اختیار بخشهای مختلف قرار میده
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Botforge\Config;
use Botforge\CoreServiceProvider;
use Botforge\Container\Container;
use Botforge\Storage\FileStorage;
use Botforge\Storage\StorageInterface;

// 1. Create configuration
$config = Config::fromArray([
    'BOT_TOKEN' => '123456:TestToken',
    'STORAGE_DRIVER' => 'file',
    'STORAGE_PATH' => __DIR__ . '/../storage/integration-test',
]);

// 2. Create Container
$container = new Container();

// 3. Register CoreServiceProvider
$container->register(new CoreServiceProvider($config));

// 4. Resolve StorageInterface
$storage = $container->get(StorageInterface::class);

// 5. Check resolved driver
if (!$storage instanceof FileStorage) {
    throw new RuntimeException('FileStorage was not resolved!');
}

echo "Storage driver resolved successfully.\n";

// 6. Test actual storage operation
$storage->set('integration:test', ['status' => 'working']);

$result = $storage->get('integration:test');

if ($result !== ['status' => 'working']) {
    throw new RuntimeException('Storage integration failed!');
}

echo "Storage operation successful.\n";

$storage->delete('integration:test');

echo "Integration test passed!\n";
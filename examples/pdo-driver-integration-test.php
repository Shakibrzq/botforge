<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Botforge\Config;
use Botforge\Container\Container;
use Botforge\CoreServiceProvider;
use Botforge\Storage\PdoStorage;
use Botforge\Storage\StorageInterface;

// 1. Configure PDO storage
$config = Config::fromArray([
    'BOT_TOKEN' => '123456:TestToken',
    'STORAGE_DRIVER' => 'pdo',
    'DB_DSN' => 'mysql:host=127.0.0.1;port=3307;dbname=botforge_test;charset=utf8mb4',
    'DB_USER' => 'root',
    'DB_PASSWORD' => 'botforge_test_pass',
]);

// 2. Create Container
$container = new Container();

// 3. Register CoreServiceProvider
$container->register(new CoreServiceProvider($config));

// 4. Resolve StorageInterface
$storage = $container->get(StorageInterface::class);

// 5. Verify driver
if (!$storage instanceof PdoStorage) {
    throw new RuntimeException('PdoStorage was not resolved!');
}

echo "PDO driver resolved successfully.\n";

// 6. Test storage operation
$storage->set('integration:pdo', ['status' => 'working']);

$result = $storage->get('integration:pdo');

if ($result !== ['status' => 'working']) {
    throw new RuntimeException('PDO integration failed!');
}

echo "PDO storage operation successful.\n";

$storage->delete('integration:pdo');

echo "PDO driver integration test passed!\n";
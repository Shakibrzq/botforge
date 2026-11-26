<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Botforge\Storage\PdoStorage;

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3307;dbname=botforge_test;charset=utf8mb4',
    'root',
    'botforge_test_pass',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

$storage = new PdoStorage($pdo);

$key = 'test:user:123';

echo "1. Testing SET...\n";
$storage->set($key, ['name' => 'Shakiba', 'step' => 2]);

echo "2. Testing GET...\n";
$data = $storage->get($key);
var_dump($data);

echo "3. Testing HAS...\n";
var_dump($storage->has($key));

echo "4. Testing DELETE...\n";
$storage->delete($key);
var_dump($storage->has($key));

echo "5. Testing TTL...\n";
$storage->set($key, 'temporary data', 2);

sleep(3);

var_dump($storage->get($key));
var_dump($storage->has($key));

echo "PDO Storage test finished.\n";
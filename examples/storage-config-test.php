<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Botforge\Config;

$config = Config::fromEnv(dirname(__DIR__));

echo "Storage driver: {$config->storageDriver}\n";
echo "Redis host: {$config->redisHost}\n";
echo "Redis port: {$config->redisPort}\n";

echo "DB DSN: ";
var_dump($config->dbDsn);
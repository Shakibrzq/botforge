<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Botforge\Config;
use Botforge\ConfigException;

try {
    $config = Config::fromArray([
        'BOT_TOKEN' => '123456:TestToken',
        'STORAGE_DRIVER' => 'mongodb',
    ]);

    echo "ERROR: Invalid driver was accepted.\n";

} catch (ConfigException $e) {
    echo "Invalid configuration rejected successfully.\n";
    echo "Error message: " . $e->getMessage() . "\n";
}
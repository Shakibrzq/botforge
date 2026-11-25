<?php

declare(strict_types=1);

// A tiny script to check that your configuration loads correctly.
// Run it with:  php examples/check-config.php

require __DIR__ . '/../vendor/autoload.php';

use Botforge\Config;
use Botforge\ConfigException;

try {
    $config = Config::fromEnv(dirname(__DIR__));
} catch (ConfigException $e) {
    echo 'Config error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "Config loaded OK:" . PHP_EOL;
var_dump($config);

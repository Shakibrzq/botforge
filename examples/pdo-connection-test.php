<?php
//checking connection pf php to database
declare(strict_types=1);

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3307;dbname=botforge_test;charset=utf8mb4',
        'root',
        'botforge_test_pass',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    echo "PDO connection successful!" . PHP_EOL;
} catch (PDOException $e) {
    echo "PDO connection failed: " . $e->getMessage() . PHP_EOL;
}

<?php
//فایل موقت تستی
// require dirname(__DIR__) . '/vendor/autoload.php';

// use Botforge\Storage\FileStorage;
// use Botforge\Storage\Session;

// $storage = new FileStorage(dirname(__DIR__) . '/storage/test');

// $storage->set('user:123', [
//     'name' => 'Shakiba',
//     'step' => 'ask_phone',
// ]);

// var_dump($storage->get('user:123'));

// var_dump($storage->has('user:123'));

// $storage->delete('user:123');

// var_dump($storage->has('user:123'));

// //تست TTL
// // $storage->set('temporary', 'hello', 2);

// // var_dump($storage->get('temporary'));

// // sleep(3);

// // var_dump($storage->get('temporary'));



// $session = new Session(
//     $storage,
//     'telegram:chat:123:session',
//     [],
//     3600,
// );

// $session->set('name', 'Shakiba');
// $session->set('step', 'ask_phone');

// var_dump($session->get('name'));
// var_dump($session->get('step'));
// var_dump($session->all());


declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Botforge\Storage\FileStorage;
use Botforge\Storage\Session;

$storage = new FileStorage(
    __DIR__ . '/../storage/test-sessions'
);

echo "Test 1: Storage set/get\n";

$storage->set('user:100', [
    'name' => 'Shakiba',
    'age' => 24,
]);

$result = $storage->get('user:100');

if ($result['name'] === 'Shakiba' && $result['age'] === 24) {
    echo "PASSED\n";
} else {
    echo "FAILED\n";
}


echo "\nTest 2: Storage has/delete\n";

if ($storage->has('user:100')) {
    echo "Has: PASSED\n";
} else {
    echo "Has: FAILED\n";
}

$storage->delete('user:100');

if (!$storage->has('user:100')) {
    echo "Delete: PASSED\n";
} else {
    echo "Delete: FAILED\n";
}


echo "\nTest 3: Session persistence\n";

$session1 = new Session(
    $storage,
    'session:user:200'
);

$session1->set('name', 'Sara');
$session1->set('step', 'enter_phone');

// ساخت یک Session جدید با همان کلید
$session2 = new Session(
    $storage,
    'session:user:200'
);

if (
    $session2->get('name') === 'Sara'
    && $session2->get('step') === 'enter_phone'
) {
    echo "PASSED\n";
} else {
    echo "FAILED\n";
}


echo "\nTest 4: TTL expiration\n";

$storage->set('temporary:data', 'hello', 1);

sleep(2);

if (!$storage->has('temporary:data')) {
    echo "PASSED\n";
} else {
    echo "FAILED\n";
}

echo "\nAll tests finished.\n";
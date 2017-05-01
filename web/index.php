<?php

header('Content-Type: text/plain', true, 200);

echo "Hello, World!\n";

$redis = new Redis();
$redis->connect('redis');
echo 'Number of visits: ' . $redis->incrBy('visitors', random_int(1, 3)) . "\n";

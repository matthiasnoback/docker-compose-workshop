<?php

header('Content-Type: text/plain', true, 200);

echo "Hello, World!\n";

$redis = new Redis();
$redis->connect('redis');
echo 'Number of visits: ' . $redis->incrBy('visitors', mt_rand(1, 4)) . "\n";

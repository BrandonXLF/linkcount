<?php

require __DIR__ . '/../vendor/autoload.php';

$db = new Database('localhost', '');
$db->exec(file_get_contents(__DIR__ . '/createdb.sql'));

echo "Created database linkcounttest.\n";

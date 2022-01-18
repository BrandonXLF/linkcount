<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/TestDatabaseFactory.php';

$db = TestDatabaseFactory::create();
$db->exec(file_get_contents(__DIR__ . '/createdb.sql'));

echo "Created database linkcounttest.\n";

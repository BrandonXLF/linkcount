<?php

require __DIR__ . '/../includes/Config.php';
require __DIR__ . '/../includes/Database.php';

$db = new Database('localhost', '');
$db->exec(file_get_contents(__DIR__ . '/linkcounttest.sql'));

echo "Created database linkcounttest.\n";

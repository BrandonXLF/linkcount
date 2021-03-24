<?php

$cnf = [
	'user' => null,
	'password' => null,
	'server' => null,
	'database' => null,
	'useragent' => null
];

if (file_exists(__DIR__ . '/../config.ini')) {
	$override = parse_ini_file(__DIR__ . '/../config.ini');
	$cnf = $override + $cnf;
}

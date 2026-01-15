<?php

class TestDatabaseFactory {
	public static function create(?string $project = null): PDO {
		$host = Config::get('test-db-host');
		$name = $project ? 'linkcounttest' : '';
		$port = Config::get('test-db-port');
		$user = Config::get('test-db-user');
		$pass = Config::get('test-db-password');

		return new PDO("mysql:host=$host;dbname=$name;port=$port;charset=utf8", $user, $pass);
	}
}

define('OVERRIDE_DATABASE_FACTORY', TestDatabaseFactory::class);

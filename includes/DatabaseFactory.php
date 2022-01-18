<?php

class DatabaseFactory {
	public static function create($server, $db) {
		$server = Config::get('db-host') ?: $server;
		$db = Config::get('db-name') ?: $db;
		$port = Config::get('db-port');
		return new PDO("mysql:host=$server;dbname=$db;port=$port;charset=utf8", Config::get('db-user'), Config::get('db-password'));
	}
}

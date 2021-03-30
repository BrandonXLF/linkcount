<?php

class Database extends PDO {
	public function __construct($server, $db) {
		$server = Config::get('db-host') ?: $server;
		$db = Config::get('db-name') ?: $db;
		$port = Config::get('db-port');
		parent::__construct("mysql:host=$server;dbname=$db;port=$port;charset=utf8", Config::get('db-user'), Config::get('db-password'));
	}
}

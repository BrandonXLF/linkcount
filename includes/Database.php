<?php

class Database extends PDO {
	public function __construct($server, $db) {
		$server = Config::get('server') ?: $server;
		$db = Config::get('database') ?: $db;
		parent::__construct("mysql:host=$server;dbname=$db;charset=utf8", Config::get('user'), Config::get('password'));
	}
}

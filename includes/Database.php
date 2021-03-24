<?php

class Database extends PDO {
	public function __construct($server, $db) {
		global $cnf;
		$server = $cnf['server'] ?: $server;
		$db = $cnf['database'] ?: $db;
		parent::__construct("mysql:host=$server;dbname=$db;charset=utf8", $cnf['user'], $cnf['password']);
	}
}

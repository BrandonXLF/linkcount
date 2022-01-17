<?php

class Database extends PDO {
	public function __construct($host, $db, $port = 3306) {
		parent::__construct("mysql:host=$host;dbname=$db;port=$port;charset=utf8", Config::$database['user'], Config::$database['password']);
	}
}

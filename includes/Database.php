<?php

class Database extends mysqli {
	private $cnf = [
		'user' => null,
		'password' => null,
		'port' => 3306,
		'server' => 'metawiki.web.db.svc.eqiad.wmflabs',
		'table' => 'meta_p'
	];

	public function __construct() {
		if (file_exists(__DIR__ . '/../config.ini')) {
			$override = parse_ini_file(__DIR__ . '/../config.ini');
			$cnf = $override + $this->cnf;
		}

		parent::__construct($cnf['server'], $cnf['user'], $cnf['password'], $cnf['table'], $cnf['port']);

		$this->set_charset('utf8');
	}
}

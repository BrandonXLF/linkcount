<?php

class DefaultConfig {
	public static $redis = [
		'host' => '',
		'port' => 6379,
		'auth' => '',
		'prefix' => ''
	];

	public static $database = [
		'user' => '',
		'password' => ''
	];

	public static $userAgent = '';

	public static function getDatabase($dbname = null) {
		$host = "$dbname.web.db.svc.wikimedia.cloud";

		if (!$dbname) {
			$dbname = 'meta';
			$host = 'metawiki.web.db.svc.wikimedia.cloud';
		}

		return new Database($host, "{$dbname}_p", 3306);
	}
}

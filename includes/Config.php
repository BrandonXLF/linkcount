<?php

class Config {
	private static $config = [];
	private static $defaults = [
		'db-user' => null,
		'db-password' => null,
		'db-host' => null,
		'db-name' => null,
		'db-port' => 3306,
		'redis-server' => 'localhost',
		'redis-port' => 6379,
		'redis-auth' => '',
		'redis-prefix' => 'linkcount',
		'useragent' => ''
	];

	private static function &load() {
		if (!self::$config) {
			if (file_exists(__DIR__ . '/../config.ini')) {
				self::$config += parse_ini_file(__DIR__ . '/../config.ini');
			}
			self::$config += self::$defaults;
		}
		return self::$config;
	}

	static function get($key) {
		return self::load()[$key];
	}

	static function set($key, $value) {
		return self::load()[$key] = $value;
	}
}

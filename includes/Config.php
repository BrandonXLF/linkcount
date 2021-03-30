<?php

class Config {
	private static $config = [];
	private static $defaults = [
		'user' => null,
		'password' => null,
		'server' => null,
		'database' => null,
		'useragent' => null
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

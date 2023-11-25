<?php

class Config {
	private static $config = [];

	private static function &load() {
		if (!self::$config) {
			if (file_exists(__DIR__ . '/../config.ini')) {
				self::$config += parse_ini_file(__DIR__ . '/../config.ini');
			}

			self::$config += parse_ini_file(__DIR__ . '/../config-default.ini');
		}

		return self::$config;
	}

	public static function get(string $key) {
		return self::load()[$key];
	}

	public static function set(string $key, string $value) {
		return self::load()[$key] = $value;
	}
}

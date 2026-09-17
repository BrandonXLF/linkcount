<?php

class Config {
	private static $config = [];

	private static function &load() {
		if (!self::$config) {
			$env = getenv('CONFIG_INI_TEXT') ?? '';

			if ($env !== '') {
				self::$config += parse_ini_string($env);
			} else if (file_exists(__DIR__ . '/../config.ini')) {
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

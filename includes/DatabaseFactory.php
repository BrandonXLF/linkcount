<?php

class DatabaseFactory {
	public const META_DATABASE = 'LINKCOUNT-META-DATABASE';

	public static function create(string $project = DatabaseFactory::META_DATABASE): PDO {
		if (defined('OVERRIDE_DATABASE_FACTORY')) {
			return constant('OVERRIDE_DATABASE_FACTORY')::create($project);
		}

		$host = self::fillName('host', $project);
		$port = self::fillName('port', $project);
		$name = self::fillName('name', $project);
		$user = Config::get('db-user');
		$pass = Config::get('db-password');

		return new PDO("mysql:host=$host;dbname=$name;port=$port;charset=utf8", $user, $pass);
	}

	private static function fillName(string $config, string $project): string {
		if ($project == self::META_DATABASE) {
			return Config::get("db-meta-$config");
		}

		return str_replace('PROJECT', $project, Config::get("db-$config"));
	}
}

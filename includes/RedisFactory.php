<?php

class RedisFactory {
	private static $instance;

	private static function make(): Redis {
		$redis = new Redis;
		$redis->pconnect(Config::get('redis-server'), Config::get('redis-port'));

		$redisAuth = Config::get('redis-auth');
		if ($redisAuth) {
			$redis->auth($redisAuth);
		}

		return $redis;
	}

	public static function get(): Redis {
		self::$instance ??= self::make();
		return self::$instance;
	}
}

<?php

class RedisFactory {
	public static function create(): Redis {
		$redis = new Redis;

		$redis->pconnect(Config::get('redis-server'), Config::get('redis-port'));

		$redisAuth = Config::get('redis-auth');

		if ($redisAuth) {
			$redis->auth($redisAuth);
		}

		return $redis;
	}
}

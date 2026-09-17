<?php

class LoadTestRedis {
	public static function load() {
		$redis = RedisFactory::get();
		$prefix = Config::get('redis-prefix');
		$ver = 'v' . Title::REDIS_DB_VER;
		$nsInfoHashKey = "$prefix:$ver:linkcounttest";

		$fileText = file_get_contents(__DIR__ . '/redis.json');
		$namespaceByName = json_decode($fileText, true);

		$redis->hMSet($nsInfoHashKey, $namespaceByName);
		$redis->expire($nsInfoHashKey, 86400);
	}
}

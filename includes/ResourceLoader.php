<?php

class ResourceLoader {
	public const CONTENT_JS = "text/javascript";
	public const CONTENT_CSS = "text/css";

	public $files;

	public function __construct(string ...$files) {
		$this->files = $files;
	}

	private function makeFile() {
		$out = '';

		foreach ($this->files as $file) {
			$content = file_get_contents($file);
			$out .= "/* $file */\n{$content}\n";
		}

		return $out;
	}

	public function getContent(string $name, string $contentType, string|null $ckey = null) {
		global $COMMIT;

		if (!headers_sent()) {
			header("Content-Type: {$contentType}; charset=utf-8");

			if ($ckey) {
				header('Cache-Control: public, max-age=31536000, immutable');
			}
		}

		$redis = RedisFactory::get();
		$prefix = Config::get('redis-prefix');
		$hashKey = "$prefix:resource:$name:$COMMIT:$ckey";
		$out = '';

		if ($redis->exists($hashKey)) {
			$out = $redis->get($hashKey);
		} else {
			$out = $this->makeFile();
			$redis->set($hashKey, $out);
			$redis->expire($hashKey, 86400);
		}

		return $out;
	}
}

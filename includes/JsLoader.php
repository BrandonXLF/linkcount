<?php

class JsLoader {
	public $files;

	public function __construct(string ...$files) {
		$this->files = $files;
	}

	public function getContent() {
		if (!headers_sent()) {
			header("Content-Type: text/javascript; charset=utf-8");

			if (get('ck') !== '') {
				header('Cache-Control: public, max-age=31536000, immutable');
			}
		}

		$out = '';

		foreach ($this->files as $file) {
			$content = file_get_contents($file);
			$out .= "/* $file */\n{$content}\n";
		}

		return $out;
	}
}

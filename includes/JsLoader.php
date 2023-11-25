<?php

class JsLoader {
	public $type;
	public $files;

	public function __construct(string ...$files) {
		$this->files = $files;
	}

	public function getContent() {
		$out = '';

		if (!headers_sent()) {
			header("Content-Type: text/javascript");
		}

		foreach ($this->files as $file) {
			$content = file_get_contents($file);
			$out .= "/* $file */\n{$content}\n";
		}

		return $out;
	}
}

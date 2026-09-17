<?php

class ResourceLoader {
	public const CONTENT_JS = "text/javascript";
	public const CONTENT_CSS = "text/css";

	public $contentType;
	public $files;

	public function __construct(string $contentType, string ...$files) {
		$this->contentType = $contentType;
		$this->files = $files;
	}

	public function getContent() {
		if (!headers_sent()) {
			header("Content-Type: {$this->contentType}; charset=utf-8");

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

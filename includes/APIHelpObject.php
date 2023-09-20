<?php


class APIHelpObject implements HtmlProducer {
	private $keys;

	public function __construct(...$keys) {
		$this->keys = $keys;
	}

	public function getHtml() {
		$out = '';

		foreach ($this->keys as list($key, $type, $status, $desc)) {
			$out .= "<li><strong><code>$key</code></strong> - $status <code>$type</code> - $desc</li>";
		}

		return "<ul>$out</ul>";
	}
}

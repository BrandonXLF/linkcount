<?php

class Footer implements HtmlProducer {
	private $rel;

	public function __construct(string $rel) {
		$this->rel = $rel;
	}

	public function getHtml() {
		$gitHubLink = (new OOUI\Tag('a'))->setAttributes([
			'href' => 'https://github.com/BrandonXLF/linkcount'
		])->appendContent('GitHub');

		$shortRev = exec('git rev-parse --short HEAD');
		$revLink = (new OOUI\Tag('a'))->setAttributes([
			'href' => 'https://github.com/BrandonXLF/linkcount/tree/' . $shortRev
		])->appendContent($shortRev);

		$authorLink = (new OOUI\Tag('a'))->setAttributes([
			'href' => 'https://en.wikipedia.org/wiki/User:BrandonXLF'
		])->appendContent('BrandonXLF');

		$parts = [
			(new OOUI\Tag('a'))->setAttributes(['href' => "./{$this->rel}/"])->appendContent('Form'),
			(new OOUI\Tag('a'))->setAttributes(['href' => "./{$this->rel}/api/"])->appendContent('API'),
			"$gitHubLink ($revLink)",
			"Created by $authorLink"
		];

		$content = new OOUI\HtmlSnippet(implode(' | ', $parts));

		return (new OOUI\Tag('footer'))->appendContent($content)->toString();
	}
}

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
			(new OOUI\Tag('a'))->setAttributes(['href' => "./{$this->rel}/"])->appendContent(_('footer-form')),
			(new OOUI\Tag('a'))->setAttributes(['href' => "./{$this->rel}/api/"])->appendContent(_('footer-api')),
			"$gitHubLink ($revLink)",
			_html('footer-created-by', [ 'variables' => [ $authorLink ], 'raw-variables' => true ])
		];

		$content = new OOUI\HtmlSnippet(implode(' | ', $parts));

		return (new OOUI\Tag('footer'))->appendContent($content)->toString();
	}
}

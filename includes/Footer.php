<?php

class Footer implements HtmlProducer {
	private $rel;

	public function __construct(string $rel) {
		$this->rel = $rel;
	}

	public function getHtml() {
		global $COMMIT;

		$gitHubLink = (new OOUI\Tag('a'))->setAttributes([
			'href' => 'https://github.com/BrandonXLF/linkcount'
		])->appendContent('GitHub');

		$revLink = (new OOUI\Tag('a'))->setAttributes([
			'href' => 'https://github.com/BrandonXLF/linkcount/tree/' . $COMMIT
		])->appendContent($COMMIT);

		$authorLink = (new OOUI\Tag('a'))->setAttributes([
			'href' => 'https://en.wikipedia.org/wiki/User:BrandonXLF'
		])->appendContent('BrandonXLF');

		$parts = [
			(new OOUI\Tag('a'))->setAttributes(['href' => "./{$this->rel}/"])->appendContent(rawmsg('footer-form')),
			(new OOUI\Tag('a'))->setAttributes(['href' => "./{$this->rel}/api/"])->appendContent(rawmsg('footer-api')),
			"$gitHubLink ($revLink)",
			escmsg('footer-created-by', [ 'variables' => [ $authorLink ], 'raw-variables' => true ])
		];

		$content = new OOUI\HtmlSnippet(implode(' | ', $parts));

		return (new OOUI\Tag('footer'))->appendContent($content)->toString();
	}
}

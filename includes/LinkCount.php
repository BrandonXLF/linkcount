<?php

class LinkCount implements HtmlProducer, JsonProducer {
	public static $description = "View the number of links (wikilinks, redirects, transclusions, file links, and category links) to any page on any Wikimedia project.";

	public $counts;
	public string $error;

	private string $projectURL;
	private Title $title;
	private CountQuery $countQuery;

	private $typeInfo = [
		'filelinks' => [
			'name' => 'File links',
			'url' => '/wiki/Special:WhatLinksHere/PAGE?hidetrans=1&hidelinks=1'
		],
		'categorylinks' => [
			'name' => 'Category links',
			'url' => '/wiki/PAGE' // WhatLinksHere doesn't show category links
		],
		'wikilinks' => [
			'name' => 'Wikilinks',
			'url' => '/wiki/Special:WhatLinksHere/PAGE?hidetrans=1&hideimages=1'
		],
		'redirects' => [
			'name' => 'Redirects',
			'url' => '/wiki/Special:WhatLinksHere/PAGE?hidelinks=1&hidetrans=1&hideimages=1'
		],
		'transclusions' => [
			'name' => 'Transclusions',
			'url' => '/wiki/Special:WhatLinksHere/PAGE?hidelinks=1&hideimages=1'
		]
	];

	public function __construct(string $page, string $project, $namespaces = '') {
		if (!$page && !$project && $namespaces === '') {
			return;
		}

		if (!$page) {
			$this->error = 'Page name is required.';
			return;
		}

		if (!$project) {
			$project = Config::get('default-project');
		}

		foreach ($namespaces ? explode(',', $namespaces) : [] as $rawNamespace) {
			if (!is_numeric($rawNamespace)) {
				$this->error = 'Invalid namespace IDs.';
				return;
			}
		}

		$maybeProjectURL = 'https://' . preg_replace('/^https:\/\//', '', $project);
		$metaDB = DatabaseFactory::create();

		$stmt = $metaDB->prepare('SELECT dbname, url FROM wiki WHERE dbname=? OR url=? LIMIT 1');
		$stmt->execute([$project, $maybeProjectURL]);

		if (!$stmt->rowCount()) {
			$this->error = 'That project does not exist...';
			return;
		}

		list($dbName, $this->projectURL) = $stmt->fetch();
		$metaDB = null;

		$db = DatabaseFactory::create($dbName);
		$this->title = new Title($page, $dbName, $this->projectURL);
		$this->countQuery = new CountQuery($namespaces, $db, $this->title);

		$this->counts = [
			'filelinks' => $this->title->getNamespaceId() === 6
				? $this->countQuery->runQuery(
					'imagelinks',
					'il',
					CountQueryMode::Transclusion,
					CountQuery::SINGLE_NS | CountQuery::NO_LINK_TARGET
				)
				: null,
			'categorylinks' => $this->title->getNamespaceId() === 14
				? $this->countQuery->runQuery(
					'categorylinks',
					'cl',
					CountQueryMode::Link,
					CountQuery::SINGLE_NS | CountQuery::NO_FROM_NS
				)
				: null,
			'wikilinks' => $this->countQuery->runQuery(
				'pagelinks',
				'pl',
				CountQueryMode::Link
			),
			'redirects' => $this->countQuery->runQuery(
				'redirect',
				'rd',
				CountQueryMode::Redirect,
				CountQuery::NO_FROM_NS | CountQuery::NO_LINK_TARGET
			),
			'transclusions' => $this->countQuery->runQuery(
				'templatelinks',
				'tl',
				CountQueryMode::Transclusion
			)
		];

		// Redirects are included in the wikilinks table
		$this->counts['wikilinks']['all'] -= $this->counts['redirects'];
		$this->counts['wikilinks']['direct'] -= $this->counts['redirects'];
	}

	public function getTitle() {
		$parts = [];

		if (isset($this->error)) {
			array_push($parts, 'Error');
		} elseif (isset($this->counts)) {
			array_push($parts, $this->title->getFullText());
		}

		array_push($parts, 'Link Count');

		return implode(' - ', $parts);
	}

	public function getHtml() {
		if (isset($this->error)) {
			return (new OOUI\Tag('div'))->addClasses(['error'])->appendContent($this->error)->toString();
		}

		if (!isset($this->counts)) {
			return LinkCount::$description;
		}

		$validCounts = array_filter($this->counts, function($val) {
			return $val !== null;
		});

		$out = (new OOUI\Tag('div'))->addClasses(['out'])->setAttributes([
			'role' => 'table',
			'aria-rowcount' => count($validCounts) + 1
		]);

		$out->appendContent(
			(new OOUI\Tag('div'))->setAttributes([
				'role' => 'row'
			])->appendContent(
				(new OOUI\Tag('div'))->setAttributes([
					'role' => 'columnheader'
				])->appendContent('Type'),
				(new OOUI\Tag('div'))->setAttributes([
					'role' => 'columnheader'
				])->appendContent('All'),
				(new OOUI\Tag('abbr'))->setAttributes([
					'title' => 'Number of pages that link to page using the actual page name',
					'role' => 'columnheader'
				])->appendContent('Direct'),
				(new OOUI\Tag('abbr'))->setAttributes([
					'title' => 'Number of pages that link to the page through a redirect',
					'role' => 'columnheader'
				])->appendContent('Indirect')
			)
		);

		$encodedPage = rawurlencode($this->title->getFullText());

		foreach ($validCounts as $key => $count) {
			$singleCount = is_int($count);

			$label = (new OOUI\Tag('a'))->setAttributes([
				'href' => $this->projectURL . str_replace('PAGE', $encodedPage, $this->typeInfo[$key]['url'])
			])->appendContent($this->typeInfo[$key]['name']);

			$link = (new OOUI\Tag('a'))->addClasses(['hash-link'])->setAttributes([
				'href' => '#' . $key,
				'title' => 'Link to row'
			])->appendContent('(#)');

			$all = number_format($singleCount ? $count : $count['all']);
			$direct = $singleCount ? new OOUI\HtmlSnippet('&#8210;') : number_format($count['direct']);
			$indirect = $singleCount ? new OOUI\HtmlSnippet('&#8210;') : number_format($count['indirect']);

			$out->appendContent(
				(new OOUI\Tag('div'))->setAttributes([
					'id' => $key,
					'role' => 'row'
				])->appendContent(
					(new OOUI\Tag('div'))->addClasses(['type'])->setAttributes([
						'role' => 'cell'
					])->appendContent($label, ' ', $link),
					(new OOUI\Tag('div'))->addClasses(['all'])->setAttributes([
						'role' => 'cell'
					])->appendContent($all),
					(new OOUI\Tag('div'))->addClasses(['direct'])->setAttributes([
						'role' => 'cell'
					])->appendContent($direct),
					(new OOUI\Tag('div'))->addClasses(['indirect'])->setAttributes([
						'role' => 'cell'
					])->appendContent($indirect)
				)
			);
		}

		$links = (new OOUI\Tag('div'))->addClasses(['links'])->appendContent(
			(new OOUI\Tag('a'))->setAttributes([
				'href' => $this->projectURL . '/wiki/Special:WhatLinksHere/' . $encodedPage
			])->appendContent('What links here')
		);

		return $out . $links;
	}

	public function getPageUpdateJson() {
		return json_encode([
			'title' => $this->getTitle(),
			'html' => $this->getHtml()
		]);
	}

	public function getJson() {
		if (isset($this->error)) {
			return json_encode(['error' => $this->error]);
		}

		return json_encode((object) $this->counts);
	}
}

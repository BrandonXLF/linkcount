<?php

class LinkCount {
	public $counts;
	public $error;

	private $fromNamespaces;
	private $projectURL;
	private $db;
	private $title;

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

	public function __construct($page, $project, $namespaces = '') {
		if (!$page && !$project && $namespaces === '') {
			return;
		}

		if (!$page) {
			$this->error = 'Page name is required.';
			return;
		}

		if (!$project) {
			$project = 'en.wikipedia.org';
		}

		foreach ($namespaces ? explode(',', $namespaces) : [] as $rawNamespace) {
			if (!is_numeric($rawNamespace)) {
				$this->error = 'Invalid namespace IDs.';
				return;
			}
		}

		$this->fromNamespaces = $namespaces;

		$maybeProjectURL = 'https://' . preg_replace('/^https:\/\//', '', $project);
		$metaDB = DatabaseFactory::create();

		$stmt = $metaDB->prepare('SELECT dbname, url FROM wiki WHERE dbname=? OR url=? LIMIT 1');
		$stmt->execute([$project, $maybeProjectURL]);

		if (!$stmt->rowCount()) {
			$this->error = 'That project does not exist...';
			return;
		}

		list($dbName, $this->projectURL) = $stmt->fetch();
		$this->db = DatabaseFactory::create($dbName);
		$this->title = new Title($page, $dbName, $this->projectURL);

		$this->counts = [
			'filelinks' => $this->title->getNamespaceId() === 6 ? $this->counts('imagelinks', 'il', 'transclusion', true) : null,
			'categorylinks' => $this->title->getNamespaceId() === 14 ? $this->counts('categorylinks', 'cl', 'link', true, true) : null,
			'wikilinks' => $this->counts('pagelinks', 'pl'),
			'redirects' => $this->counts('redirect', 'rd', 'redirect', false, true),
			'transclusions' => $this->counts('templatelinks', 'tl', 'transclusion')
		];

		// Redirects are included in the wikilinks table
		$this->counts['wikilinks']['all'] -= $this->counts['redirects'];
		$this->counts['wikilinks']['direct'] -= $this->counts['redirects'];
	}

	private function counts($table, $prefix, $mode = 'link', $singleNS = false, $noFromNamespace = false) {
		$escapedTitle = $this->db->quote($this->title->getDBKey());
		$escapedBlank = $this->db->quote('');
		$titleColumn = $prefix . '_' . ($singleNS ? 'to' : 'title');

		$where = $this->fromNamespaces !== '' && !$noFromNamespace ? " AND {$prefix}_from_namespace IN ({$this->fromNamespaces})" : '';
		$join = $this->fromNamespaces !== '' && $noFromNamespace ? " JOIN page AS source ON source.page_id = {$prefix}_from AND source.page_namespace IN ({$this->fromNamespaces})" : '';

		$directCond = "$join WHERE $titleColumn = $escapedTitle" . ($singleNS ? '' : " AND {$prefix}_namespace = {$this->title->getNamespaceId()}") . $where;
		$indirectCond = "JOIN page AS target ON target.page_id = rd_from"
			. " JOIN $table ON $titleColumn = target.page_title" . ($singleNS ? '' : " AND {$prefix}_namespace = target.page_namespace") . "$where$join"
			. " WHERE rd_title = $escapedTitle AND rd_namespace = {$this->title->getNamespaceId()} AND (rd_interwiki IS NULL OR rd_interwiki = $escapedBlank)";

		if ($mode == 'redirect') {
			$query = "SELECT COUNT(rd_from) FROM redirect"
				. " $directCond AND ({$prefix}_interwiki is NULl or {$prefix}_interwiki = $escapedBlank)";
		} elseif ($mode == 'transclusion') {
			// Transclusions of a redirect that actually follow the redirect are also added as a transclusion of the redirect target
			// If a page transcludes a redirect to a target and the target itself, only the transclusion of the redirect is counted
			$query = "SELECT COUNT({$prefix}_from), COUNT({$prefix}_from) - COUNT(indirect_from), COUNT(indirect_from) FROM $table"
				. " LEFT JOIN (SELECT DISTINCT {$prefix}_from AS indirect_from FROM redirect $indirectCond) AS temp ON {$prefix}_from = indirect_from $directCond";
		} else {
			$query = "SELECT COUNT(DISTINCT {$prefix}_from), SUM(NOT indirect), SUM(indirect) FROM"
				. " (SELECT DISTINCT {$prefix}_from, 1 AS indirect FROM redirect $indirectCond UNION ALL SELECT {$prefix}_from, 0 AS indirect FROM $table $directCond) AS temp";
		}

		$res = $this->db->query($query)->fetch();

		return $mode == 'redirect' ? (int) $res[0] : [
			'all' => (int) $res[0],
			'direct' => (int) $res[1],
			'indirect' => (int) $res[2]
		];
	}

	public function html() {
		if (isset($this->error)) {
			return (new OOUI\Tag('div'))->addClasses(['error'])->appendContent($this->error)->toString();
		}

		if (!isset($this->counts)) {
			return '';
		}

		$out = (new OOUI\Tag('div'))->addClasses(['out']);

		$out->appendContent(
			(new OOUI\Tag('div'))->addClasses(['header'])->appendContent('Type'),
			(new OOUI\Tag('div'))->addClasses(['header'])->appendContent('All'),
			(new OOUI\Tag('div'))->addClasses(['header'])->appendContent('Direct'),
			(new OOUI\Tag('div'))->addClasses(['header'])->appendContent('Indirect')
		);

		$encodedPage = rawurlencode($this->title->getFullText());

		foreach ($this->counts as $key => $count) {
			if ($count === null) continue;

			$singleCount = is_int($count);

			$label = (new OOUI\Tag('a'))->setAttributes([
				'href' => $this->projectURL . str_replace('PAGE', $encodedPage, $this->typeInfo[$key]['url'])
			])->appendContent($this->typeInfo[$key]['name']);

			$all = number_format($singleCount ? $count : $count['all']);
			$direct = $singleCount ? new OOUI\HtmlSnippet('&#8210;') : number_format($count['direct']);
			$indirect = $singleCount ? new OOUI\HtmlSnippet('&#8210;') : number_format($count['indirect']);

			$out->appendContent(
				(new OOUI\Tag('div'))->addClasses(['type'])->appendContent($label),
				(new OOUI\Tag('div'))->addClasses(['all'])->appendContent($all),
				(new OOUI\Tag('div'))->addClasses(['direct'])->appendContent($direct),
				(new OOUI\Tag('div'))->addClasses(['indirect'])->appendContent($indirect)
			);
		}

		$links = (new OOUI\Tag('div'))->addClasses(['links'])->appendContent(
			(new OOUI\Tag('a'))->setAttributes([
				'href' => $this->projectURL . '/wiki/Special:WhatLinksHere/' . $encodedPage
			])->appendContent('What links here')
		);

		return $out . $links;
	}

	public function json($headers = true) {
		if ($headers) {
			header('Content-Type: application/json');
			header('Access-Control-Allow-Origin: *');
		}

		if (isset($this->error)) {
			return json_encode(['error' => $this->error]);
		}

		return json_encode((object)$this->counts);
	}
}

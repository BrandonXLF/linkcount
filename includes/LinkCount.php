<?php

class LinkCount implements HtmlProducer, JsonProducer {
	public const COUNT_MODE_REDIRECT = 'redirect';
	public const COUNT_MODE_LINK = 'link';
	public const COUNT_MODE_TRANSCLUSION = 'transclusion';

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
			$project = Config::get('default-project');
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
			'filelinks' => $this->title->getNamespaceId() === 6 ? $this->counts('imagelinks', 'il', self::COUNT_MODE_TRANSCLUSION, true, true, false) : null,
			'categorylinks' => $this->title->getNamespaceId() === 14 ? $this->counts('categorylinks', 'cl', self::COUNT_MODE_LINK, true, false, false) : null,
			'wikilinks' => $this->counts('pagelinks', 'pl', self::COUNT_MODE_LINK, false, true, false),
			'redirects' => $this->counts('redirect', 'rd', self::COUNT_MODE_REDIRECT, false, false, false),
			'transclusions' => $this->counts('templatelinks', 'tl', self::COUNT_MODE_TRANSCLUSION, false, true, true)
		];

		// Redirects are included in the wikilinks table
		$this->counts['wikilinks']['all'] -= $this->counts['redirects'];
		$this->counts['wikilinks']['direct'] -= $this->counts['redirects'];
	}

	private function counts($table, $prefix, $mode = self::COUNT_MODE_LINK, $singleNS = false, $hasFromNamespace = true, $usesLinkTarget = true) {
		$escapedTitle = $this->db->quote($this->title->getDBKey());
		$escapedBlank = $this->db->quote('');
		$titleColumn = $prefix . '_' . ($singleNS ? 'to' : 'title');

		$fromNamespaceWhere = '';
		$fromNamespaceJoin = '';

		if ($this->fromNamespaces !== '') {
			// Must be used in queries with $table
			$fromNamespaceWhere = $hasFromNamespace ? " AND {$prefix}_from_namespace IN ({$this->fromNamespaces})" : '';
			$fromNamespaceJoin = !$hasFromNamespace ? " JOIN page AS source ON source.page_id = {$prefix}_from AND source.page_namespace IN ({$this->fromNamespaces})" : '';
		}

		// TODO: Remove once all tables are switched to linktarget
		$directCond = '';
		$indirectQuery = '';

		if (!$usesLinkTarget) {
			$namespaceComponent = $singleNS ? '' : " AND {$prefix}_namespace = {$this->title->getNamespaceId()}";

			$directCond = <<<SQL
				$fromNamespaceJoin
				WHERE $titleColumn = $escapedTitle $namespaceComponent $fromNamespaceWhere
			SQL;

			$namespaceComponent = $singleNS ? '' : " AND {$prefix}_namespace = target.page_namespace";

			$indirectQuery = <<<SQL
				SELECT DISTINCT NULL AS direct_link, {$prefix}_from AS indirect_link FROM redirect
				JOIN page AS target ON target.page_id = rd_from
				JOIN $table ON $titleColumn = target.page_title $namespaceComponent $fromNamespaceWhere$fromNamespaceJoin
				WHERE rd_title = $escapedTitle AND rd_namespace = {$this->title->getNamespaceId()} AND (rd_interwiki IS NULL OR rd_interwiki = $escapedBlank)
			SQL;
		} else {
			// Must be used in queries with $table
			$directCond = <<<SQL
				JOIN linktarget on {$prefix}_target_id = lt_id $fromNamespaceJoin
				WHERE lt_title = $escapedTitle AND lt_namespace = {$this->title->getNamespaceId()} $fromNamespaceWhere
			SQL;

			$indirectQuery = <<<SQL
				SELECT DISTINCT NULL AS direct_link, {$prefix}_from AS indirect_link FROM redirect
				JOIN page AS target ON target.page_id = rd_from
				JOIN linktarget ON lt_title = target.page_title AND lt_namespace = target.page_namespace
				JOIN $table ON {$prefix}_target_id = lt_id $fromNamespaceWhere$fromNamespaceJoin
				WHERE rd_title = $escapedTitle AND rd_namespace = {$this->title->getNamespaceId()} AND (rd_interwiki IS NULL OR rd_interwiki = $escapedBlank)
			SQL;
		}

		if ($mode == self::COUNT_MODE_REDIRECT) {
			$query = <<<SQL
				SELECT COUNT(rd_from) FROM $table
				$directCond AND ({$prefix}_interwiki is NULL or {$prefix}_interwiki = $escapedBlank)
			SQL;
		} elseif ($mode == self::COUNT_MODE_TRANSCLUSION) {
			// Transclusions of a redirect that follow the redirect are also added as a transclusion of the redirect target.
			// There is no way to differentiate from a page with a indirect link and a page with a indirect and a direct link in this case, only the indirect link is recorded.
			// Pages can also transclude a page with a redirect without following the redirect, so a valid indirect link must have an associated direct link.
			$query = <<<SQL
				SELECT
					COUNT({$prefix}_from),
					COUNT({$prefix}_from) - COUNT(indirect_link),
					COUNT(indirect_link)
				FROM $table
				LEFT JOIN ($indirectQuery) AS temp ON {$prefix}_from = indirect_link $directCond
			SQL;
		} elseif ($mode == self::COUNT_MODE_LINK) {
			$query = <<<SQL
				SELECT
					COUNT(DISTINCT COALESCE(direct_link, indirect_link)),
					COUNT(direct_link),
					COUNT(indirect_link)
				FROM (
					SELECT {$prefix}_from AS direct_link, NULL AS indirect_link FROM $table
					$directCond
					UNION ALL
					$indirectQuery
				) AS temp
			SQL;
		}

		$res = $this->db->query($query)->fetch();

		return $mode == self::COUNT_MODE_REDIRECT ? (int) $res[0] : [
			'all' => (int) $res[0],
			'direct' => (int) $res[1],
			'indirect' => (int) $res[2]
		];
	}

	public function getHtml() {
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
			(new OOUI\Tag('abbr'))->addClasses(['header'])->setAttributes([
				'title' => 'Number of pages that link to page using the actual page name'
			])->appendContent('Direct'),
			(new OOUI\Tag('abbr'))->addClasses(['header'])->setAttributes([
				'title' => 'Number of pages that link to the page through a redirect'
			])->appendContent('Indirect')
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

	public function getJson() {
		if (isset($this->error)) {
			return json_encode(['error' => $this->error]);
		}

		return json_encode((object) $this->counts);
	}
}

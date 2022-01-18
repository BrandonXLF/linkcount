<?php

class LinkCount {
	public $counts;
	public $error;

	private $projectURL;
	private $db;
	private $namespace;
	private $title;
	private $page;
	private $namespaces;

	private $meta = [
		'filelinks' => ['File links', '/wiki/Special:WhatLinksHere/PAGE?hidetrans=1&hidelinks=1'],
		// WhatLinksHere doesn't show category links
		'categorylinks' => ['Category links', '/wiki/PAGE'],
		'wikilinks' => ['Wikilinks', '/wiki/Special:WhatLinksHere/PAGE?hidetrans=1&hideimages=1'],
		'redirects' => ['Redirects', '/wiki/Special:WhatLinksHere/PAGE?hidelinks=1&hidetrans=1&hideimages=1'],
		'transclusions' => ['Transclusions', '/wiki/Special:WhatLinksHere/PAGE?hidelinks=1&hideimages=1']
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

		$this->namespaces = $namespaces;
		$this->page = $page;

		if (substr($project, 0, 8) === 'https://') {
			$project = substr($project, 8);
		} elseif (substr($project, 0, 7) === 'http://') {
			$project = substr($project, 7);
		}

		$maybeProjectURL = 'https://' . $project;
		$this->db = DatabaseFactory::create('metawiki.web.db.svc.wikimedia.cloud', 'meta_p');

		$stmt = $this->db->prepare('SELECT dbname, url FROM wiki WHERE dbname=? OR url=? LIMIT 1');
		$stmt->execute([$project, $maybeProjectURL]);

		if (!$stmt->rowCount()) {
			$this->error = 'That project does not exist...';
			return;
		}

		list($dbname, $this->projectURL) = $stmt->fetch();
		list($this->namespace, $this->title) = $this->getDBInfo($dbname, $this->projectURL, $this->page);
		$this->db = DatabaseFactory::create("$dbname.web.db.svc.wikimedia.cloud", "{$dbname}_p");

		$this->counts = [
			'filelinks' => $this->namespace === 6 ? $this->counts('imagelinks', 'il', 'transclusion', true) : null,
			'categorylinks' => $this->namespace === 14 ? $this->counts('categorylinks', 'cl', 'link', true, true) : null,
			'wikilinks' => $this->counts('pagelinks', 'pl'),
			'redirects' => $this->counts('redirect', 'rd', 'redirect', false, true),
			'transclusions' => $this->counts('templatelinks', 'tl', 'transclusion')
		];

		// Redirects are included in the wikilinks table
		$this->counts['wikilinks']['all'] -= $this->counts['redirects'];
		$this->counts['wikilinks']['direct'] -= $this->counts['redirects'];
	}

	public static function getDBInfo($dbname, $url, $page) {
		$redis = new Redis();
		$redis->connect(Config::get('redis-server'), Config::get('redis-port'));
		$redis->auth(Config::get('redis-auth'));

		$prefix = Config::get('redis-prefix');
		$idsKey = $prefix . ':' . $dbname;
		$casesKey = $prefix . ':' . $dbname . ':cases';

		if (!$redis->exists($idsKey)) {
			$curl = curl_init();
			curl_setopt_array($curl, [
				CURLOPT_URL => $url . '/w/api.php?action=query&meta=siteinfo&siprop=namespaces|namespacealiases&format=json&formatversion=2',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERAGENT => Config::get('useragent')
			]);
			$info = json_decode(curl_exec($curl));
			curl_close($curl);

			$ids = [];
			$caseSensitive = [];

			foreach ($info->query->namespaces as $namespace) {
				$ids[strtolower($namespace->name)] = $namespace->id;
				$caseSensitive[$namespace->id] = $namespace->case;

				if (isset($namespace->canonical)) {
					$ids[strtolower($namespace->canonical)] = $namespace->id;
				}
			}

			foreach ($info->query->namespacealiases as $namespace) {
				$ids[strtolower($namespace->alias)] = $namespace->id;
			}

			$redis->hMSet($idsKey, $ids);
			$redis->hMSet($casesKey, $caseSensitive);

			$redis->expire($idsKey, 86400);
			$redis->expire($casesKey, 86400);
		}

		if ($page[0] === ':') {
			$page = substr($page, 1);
		}

		$fragPos = strpos($page, '#');
		if ($fragPos) {
			$page = substr($page, 0, $fragPos);
		}

		$page = str_replace('_', ' ', $page);
		list($ns, $title) = strpos($page, ':') === false ? ['', $page] : explode(':', $page, 2);
		$ns = strtolower($ns);
		$title = str_replace(' ', '_', $title);
		$id = $redis->hGet($idsKey, $ns);

		if ($id === false) {
			$title = str_replace(' ', '_', $page);
			$id = $redis->hGet($idsKey, '');
		}

		$case = $redis->hGet($casesKey, $id);
		$title = $case !== 'case-sensitive' ? ucfirst($title) : $title;

		return [(int) $id, $title];
	}

	private function counts($table, $prefix, $mode = 'link', $singleNS = false, $noFromNamespace = false) {
		$escapedTitle = $this->db->quote($this->title);
		$escapedBlank = $this->db->quote('');
		$titleColumn = $prefix . '_' . ($singleNS ? 'to' : 'title');

		$where = $this->namespaces !== '' && !$noFromNamespace ? " AND {$prefix}_from_namespace IN ({$this->namespaces})" : '';
		$join = $this->namespaces !== '' && $noFromNamespace ? " JOIN page AS source ON source.page_id = {$prefix}_from AND source.page_namespace IN ({$this->namespaces})" : '';

		$directCond = "$join WHERE $titleColumn = $escapedTitle" . ($singleNS ? '' : " AND {$prefix}_namespace = {$this->namespace}") . $where;
		$indirectCond = "JOIN page AS target ON target.page_id = rd_from"
			. " JOIN $table ON $titleColumn = target.page_title" . ($singleNS ? '' : " AND {$prefix}_namespace = target.page_namespace") . "$where$join"
			. " WHERE rd_title = $escapedTitle AND rd_namespace = {$this->namespace} AND (rd_interwiki IS NULL OR rd_interwiki = $escapedBlank)";

		if ($mode == 'redirect') {
			$query = "SELECT COUNT(rd_from) FROM redirect"
				. " $directCond AND ({$prefix}_interwiki is NULl or {$prefix}_interwiki = $escapedBlank)";
		} elseif ($mode == 'transclusion') {
			// Transclusions of a redirect that actually follow the redirect are also added as a transclusion of the redirect target
			// If a page transcludes a redirect to a page and the page itself, only the transclusion of the redirect is counted
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
		$out = '';

		if (isset($this->error)) {
			$out = "<div class=\"error\">{$this->error}</div>";
		} elseif (isset($this->counts)) {
			$out .= '<div class="out"><div class="header">Type</div><div class="header">All</div><div class="header">Direct</div><div class="header">Indirect</div>';

			foreach ($this->counts as $key => $count) {
				if ($count === null) continue;

				$sublink = str_replace('PAGE', rawurlencode($this->page), $this->meta[$key][1]);
				$label = "<a href=\"{$this->projectURL}$sublink\">{$this->meta[$key][0]}</a>";;

				$all = number_format(is_int($count) ? $count : $count['all']);
				$direct = is_int($count) ? '‒' : number_format($count['direct']);
				$indirect = is_int($count) ? '‒' : number_format($count['indirect']);

				$out .= "<div class=\"type\">$label</div><div class=\"all\">$all</div><div class=\"direct\">$direct</div><div class=\"indirect\">$indirect</div>";
			}

			$out .= '</div>';
			$link = $this->projectURL . '/wiki/Special:WhatLinksHere/' . rawurlencode($this->page);
			$out .= "<div class=\"links\"><a href=\"$link\">What links here</a></div>";
		}

		return $out;
	}

	public function json($headers = true) {
		$out = [];

		if (isset($this->error)) {
			$out['error'] = $this->error;
		} elseif (isset($this->counts)) {
			foreach ($this->counts as $type => $count) {
				$out[$type] = $count;
			}
		}

		if ($headers) {
			header('Content-Type: application/json');
			header('Access-Control-Allow-Origin: *');
		}

		return json_encode($out);
	}
}

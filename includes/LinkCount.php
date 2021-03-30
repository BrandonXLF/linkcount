<?php

define('SINGLE_NAMESPACE', 1);
define('HAS_INTERWIKI', 2);
define('EXCLUDE_INDIRECT', 4);
define('NO_FROM_NAMESPACE', 8);

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
		'directfilelinks' => ['Direct file links', '/wiki/Special:WhatLinksHere/PAGE?hideredirs=1&hidetrans=1&hidelinks=1'],
		'allfilelinks' => ['All file links', '/wiki/Special:WhatLinksHere/PAGE?hidetrans=1&hidelinks=1'],
		// WhatLinksHere doesn't show category links
		'directcategorylinks' => ['Direct category links', '/wiki/PAGE'],
		// Show redirects so they can be clicked to see their category links, or something like that...
		'allcategorylinks' => ['All category links', '/wiki/Special:WhatLinksHere/PAGE?hidelinks=1&hidetrans=1&hideimages=1'],
		'directwikilinks' => ['Direct wikilinks', '/wiki/Special:WhatLinksHere/PAGE?hideredirs=1&hidetrans=1&hideimages=1'],
		'allwikilinks' => ['All wikilinks', '/wiki/Special:WhatLinksHere/PAGE?hidetrans=1&hideimages=1'],
		'redirects' => ['Redirects', '/wiki/Special:WhatLinksHere/PAGE?hidelinks=1&hidetrans=1&hideimages=1'],
		'directtransclusions' => ['Direct transclusions', '/wiki/Special:WhatLinksHere/PAGE?hideredirs=1&hidelinks=1&hideimages=1'],
		'alltransclusions' => ['All transclusions', '/wiki/Special:WhatLinksHere/PAGE?hidelinks=1&hideimages=1']
	];

	public function __construct($page, $project, $namespaces) {
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
		$this->db = new Database('metawiki.web.db.svc.wikimedia.cloud', 'meta_p');

		$stmt = $this->db->prepare('SELECT dbname, url FROM wiki WHERE dbname=? OR url=? LIMIT 1');
		$stmt->execute([$project, $maybeProjectURL]);

		if (!$stmt->rowCount()) {
			$this->error = 'That project does not exist..';
			return;
		}

		list($dbname, $this->projectURL) = $stmt->fetch();

		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $this->projectURL . '/w/api.php?action=query&prop=info&format=json&formatversion=2&titles=' . rawurlencode($this->page),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => Config::get('useragent')
		]);
		$info = json_decode(curl_exec($curl));
		curl_close($curl);

		$this->namespace = $info->query->pages[0]->ns;
		$this->title = str_replace(' ', '_', $info->query->pages[0]->title);
		$this->db = new Database("$dbname.web.db.svc.wikimedia.cloud", "{$dbname}_p");

		if ($this->namespace != 0) {
			$this->title = explode(':', $this->title, 2)[1];
		}

		$redirects = $this->fetch('redirect', 'rd', NO_FROM_NAMESPACE | HAS_INTERWIKI | EXCLUDE_INDIRECT);

		$this->counts = [
			// The filelinks table counts links to redirects twice
			'filelinks' => $this->namespace != 6 ? null : $this->fetch('imagelinks', 'il', SINGLE_NAMESPACE, function($direct, $indirect) {
				return [$direct - $indirect, $indirect];
			}),
			'categorylinks' => $this->namespace != 14 ? null : $this->fetch('categorylinks', 'cl', SINGLE_NAMESPACE | NO_FROM_NAMESPACE, function($direct, $indirect) {
				return [$direct, $indirect];
			}),
			// Redirects are included in the wikilinks table
			'wikilinks' => $this->fetch('pagelinks', 'pl', 0, function($direct, $indirect) use ($redirects) {
				return [$direct - $redirects, $indirect];
			}),
			'redirects' => $redirects,
			// The transclusions table counts links to redirects twice
			'transclusions' => $this->fetch('templatelinks', 'tl', 0, function($direct, $indirect) {
				return [$direct - $indirect, $indirect];
			})
		];
	}

	private function get_count($conds) {
		$tables = [];
		$where = [];

		foreach ($conds as $table => $cond) {
			$tables[] = $table;
			$where = array_merge($where, $cond);
		}

		$stmt = $this->db->query('SELECT COUNT(*) FROM ' . implode(', ',  $tables) . ' WHERE ' . implode(' AND ', $where));
		return (int) $stmt->fetch()[0];
	}

	private function fetch($table, $prefix, $flags, $calc = null) {
		$titleColumn = $prefix . '_' . ($flags & SINGLE_NAMESPACE ? 'to' : 'title');
		$escapedTitle = $this->db->quote($this->title);
		$escapedNS = $this->db->quote($this->namespace);
		$escapedBlank = $this->db->quote('');

		$direct = [
			$table => ["$titleColumn = $escapedTitle"]
		];

		$indirect = [
			'redirect' => ["rd_namespace = $escapedNS", "rd_title = $escapedTitle", "(rd_interwiki IS NULL OR rd_interwiki = $escapedBlank)"],
			'page AS target' => ['target.page_id = rd_from'],
			$table => ["$titleColumn = target.page_title"]
		];

		if (~$flags & SINGLE_NAMESPACE) {
			$direct[$table][] = "{$prefix}_namespace = $escapedNS";
			$indirect[$table][] = "{$prefix}_namespace = target.page_namespace";
		}

		// Check if link comes from one of the selected namespaces
		if ($this->namespaces !== '') {
			if ($flags & NO_FROM_NAMESPACE) {
				$direct['page AS source'] = $indirect['page AS source'] = [
					"source.page_id = {$prefix}_from",
					"source.page_namespace IN ({$this->namespaces})"
				];
			} else {
				$direct[$table][] = $indirect[$table][] = "{$prefix}_from_namespace IN ({$this->namespaces})";
			}
		}

		if ($flags & HAS_INTERWIKI) {
			$direct[$table][] = "({$prefix}_interwiki IS NULL OR {$prefix}_interwiki = $escapedBlank)";
		}

		if ($flags & EXCLUDE_INDIRECT) {
			return $this->get_count($direct);
		}

		list($direct_count, $indirect_count) = $calc($this->get_count($direct), $this->get_count($indirect));

		return [
			'direct' => $direct_count,
			'indirect' => $indirect_count,
			'all' => $direct_count + $indirect_count
		];;
	}

	private function create_out($key, $num, $class = '') {
		$formatted = number_format($num);
		$class = $class ? " $class" : '';
		$label = '<a href="' . $this->projectURL . str_replace('PAGE', rawurlencode($this->page), $this->meta[$key][1]) . "\">{$this->meta[$key][0]}</a>";
		return "<div class=\"out$class\"><h2>$label</h2><div class=\"num\">$formatted</div></div>";
	}

	public function html() {
		$out = '';

		if (isset($this->error)) {
			$out = "<div class=\"error\">{$this->error}</div>";
		}

		if (isset($this->counts)) {
			foreach ($this->counts as $type => $count) {
				if ($count === null) continue;

				if (is_int($count)) {
					$out .= $this->create_out($type, $count);
					continue;
				}

				$out .= $this->create_out("direct$type", $count['direct'], 'left');
				$out .= $this->create_out("all$type", $count['all'], 'right');
			}

			$link = $this->projectURL . '/wiki/Special:WhatLinksHere/' . rawurlencode($this->page);
			$out .= "<div class=\"links\"><a href=\"$link\">What links here</a></div>";
		}

		return $out;
	}

	public function json($headers = true) {
		$out = [];

		if (isset($this->error)) {
			$out['error'] = $this->error;
		}

		if (isset($this->counts)) {
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

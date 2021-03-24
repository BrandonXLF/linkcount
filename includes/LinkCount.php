<?php

require __DIR__ . '/Database.php';

define('SINGLE_NAMESPACE', 1);
define('HAS_INTERWIKI', 2);
define('EXCLUDE_INDIRECT', 4);
define('NO_FROM_NAMESPACE', 8);

class LinkCount {
	public $counts;
	public $error;

	private $project_url;
	private $db;
	private $namespace;
	private $title;
	private $page;
	private $namespaces;

	public function __construct($page, $project, $namespaces) {
		global $cnf;

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
		$this->page = str_replace(' ', '_', $page);
		$this->page = ucfirst($this->page);

		if (substr($project, 0, 8) === 'https://') {
			$project = substr($project, 8);
		} elseif (substr($project, 0, 7) === 'http://') {
			$project = substr($project, 7);
		}

		$maybe_project_url = 'https://' . $project;
		$this->db = new Database('metawiki.web.db.svc.wikimedia.cloud', 'meta_p');

		$stmt = $this->db->prepare('SELECT dbname, url FROM wiki WHERE dbname=? OR url=? LIMIT 1');
		$stmt->execute([$project, $maybe_project_url]);

		if (!$stmt->rowCount()) {
			$this->error = 'That project does not exist..';
			return;
		}

		list($dbname, $this->project_url) = $stmt->fetch();

		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $this->project_url . '/w/api.php?action=query&prop=info&format=json&formatversion=2&titles=' . rawurlencode($this->page),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => $cnf['useragent']
		]);
		$info = json_decode(curl_exec($curl));
		curl_close($curl);

		$this->namespace = $info->query->pages[0]->ns;
		$this->title = $info->query->pages[0]->title;
		$this->db = new Database("$dbname.web.db.svc.wikimedia.cloud", "{$dbname}_p");

		if ($this->namespace != 0) {
			$this->title = explode(':', $this->title, 2)[1];
		}

		$redirects = $this->fetch('redirect', 'rd', null, NO_FROM_NAMESPACE | HAS_INTERWIKI | EXCLUDE_INDIRECT);

		$this->counts = [
			// The filelinks table counts links to redirects twice
			'filelinks' => $this->namespace != 6 ? null : $this->fetch('imagelinks', 'il', fn($d, $i) => [$d - $i, $i], SINGLE_NAMESPACE),
			'categorylinks' => $this->namespace != 14 ? null : $this->fetch('categorylinks', 'cl', fn($d, $i) => [$d, $i], SINGLE_NAMESPACE | NO_FROM_NAMESPACE),
			// Redirects are included in the wikilinks table
			'wikilinks' => $this->fetch('pagelinks', 'pl', fn($d, $i) => [$d - $redirects, $i]),
			'redirects' => $redirects,
			// The transclusions table counts links to redirects twice
			'transclusions' => $this->fetch('templatelinks', 'tl', fn($d, $i) => [$d - $i, $i])
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

	private function fetch($table, $prefix, $calc, $flags = 0) {
		$title_column = $prefix . '_' . ($flags & SINGLE_NAMESPACE ? 'to' : 'title');
		$escaped_title = $this->db->quote($this->title);
		$escaped_ns = $this->db->quote($this->namespace);
		$escaped_blank = $this->db->quote('');

		$direct = [
			$table => ["$title_column = $escaped_title"]
		];

		$indirect = [
			'redirect' => ["rd_namespace = $escaped_ns", "rd_title = $escaped_title", "(rd_interwiki IS NULL OR rd_interwiki = $escaped_blank)"],
			'page AS target' => ['target.page_id = rd_from'],
			$table => ["$title_column = target.page_title"]
		];

		if (~$flags & SINGLE_NAMESPACE) {
			$direct[$table][] = "{$prefix}_namespace = $escaped_ns";
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
			$direct[$table][] = "({$prefix}_interwiki IS NULL OR {$prefix}_interwiki = $escaped_blank)";
		}

		if ($flags & EXCLUDE_INDIRECT) {
			return $this->get_count($direct);
		}

		list($d, $i) = $calc($this->get_count($direct), $this->get_count($indirect));

		return [
			'direct' => $d,
			'indirect' => $i,
			'all' => $d + $i
		];;
	}

	private function create_out($label, $num, $class = '') {
		$formatted = number_format($num);
		return "<div class=\"out $class\"><div>$label </div><div class=\"num\">$formatted</div></div>";
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
					$out .= $this->create_out(ucfirst($type), $count);
					continue;
				}
				$out .= $this->create_out("Direct $type", $count['direct'], 'left');
				$out .= $this->create_out("All $type", $count['all'], 'right');
			}

			$link = $this->project_url . '/wiki/Special:WhatLinksHere/' . rawurlencode($this->page);
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

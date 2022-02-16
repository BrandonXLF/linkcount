<?php

class ProjectPrefixSearch implements ProducesJson {
	public $projects = [];
	public $exact;

	public function __construct($prefix) {
		if ($prefix == '') {
			$this->projects = [];
			return;
		}

		$db = DatabaseFactory::create();
		$maybeProjectURL = 'https://' . preg_replace('/^https:\/\//', '', $prefix);

		$stmt = $db->prepare('SELECT dbname, url FROM wiki WHERE dbname LIKE ? OR url LIKE ?');
		$stmt->execute([$prefix . '%', $maybeProjectURL . '%']);

		foreach ($stmt->fetchAll() as $row) {
			$domain = substr($row[1], 8);

			if ($row[0] == $prefix || $row[1] == $maybeProjectURL) {
				$this->exact = $domain;
			}

			$this->projects[] = $domain;
		}
	}

	public function getJson() {
		if (!headers_sent()) {
			header('Content-Type: application/json');
		}

		echo json_encode([
			'projects' => $this->projects,
			'exact' => $this->exact
		]);
	}
}

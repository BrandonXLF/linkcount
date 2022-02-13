<?php

class ProjectPrefixSearch implements ProducesJson {
	public $projects;

	public function __construct($prefix) {
		if ($prefix == '') {
			$this->projects = [];
			return;
		}

		$db = DatabaseFactory::create();
		$maybeProjectURL = 'https://' . preg_replace('/^https:\/\//', '', $prefix);

		$stmt = $db->prepare('SELECT url FROM wiki WHERE dbname LIKE ? OR url LIKE ?');
		$stmt->execute([$prefix . '%', $maybeProjectURL . '%']);

		foreach ($stmt->fetchAll() as $row) {
			$this->projects[] = substr($row[0], 8);
		}
	}

	public function getJson() {
		if (!headers_sent()) {
			header('Content-Type: application/json');
		}

		echo json_encode($this->projects);
	}
}

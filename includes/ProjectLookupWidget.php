<?php

class ProjectLookupWidget extends OOUI\TextInputWidget {
	private $domain;

	public function __construct(array $config = []) {
		$config['placeholder'] = $config['default'];

		parent::__construct($config);

		$project = $config['value'] ?: 'en.wikipedia.org';
		$db = DatabaseFactory::create();
		$maybeProjectURL = 'https://' . preg_replace('/^https:\/\//', '', $project);

		$stmt = $db->prepare('SELECT url FROM wiki WHERE dbname = ? OR url = ? LIMIT 1');
		$stmt->execute([$project, $maybeProjectURL]);
		$row = $stmt->fetch();

		if ($row) {
			$this->domain = substr($row[0], 8);
		}
	}

	public function getConfig(&$config) {
		$config['domain'] = $this->domain;

		return parent::getConfig($config);
	}
}

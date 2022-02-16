<?php

class ProjectLookupWidget extends OOUI\TextInputWidget {
	private $domain;
	private $default;

	public function __construct(array $config = []) {
		$config['placeholder'] = $config['default'];

		parent::__construct($config);

		$this->default = $config['default'];

		$project = $config['value'];

		if (!$project) {
			$this->domain = $this->default;
			return;
		}

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
		$config['default'] = $this->default;

		return parent::getConfig($config);
	}
}

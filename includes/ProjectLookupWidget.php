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

		$projectInfo = ProjectLookup::lookupProject($project);

		if ($projectInfo) {
			$this->domain = substr($projectInfo->url, 8);
		}
	}

	public function getConfig(&$config) {
		$config['domain'] = $this->domain;
		$config['default'] = $this->default;

		return parent::getConfig($config);
	}
}

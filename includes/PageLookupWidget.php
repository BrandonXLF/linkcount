<?php

class PageLookupWidget extends OOUI\TextInputWidget {
	public function getConfig(&$config) {
		$config['site'] = get('project') ?: 'en.wikipedia.org';
		return parent::getConfig($config);
	}
}

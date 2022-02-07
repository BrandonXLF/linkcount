<?php

class ProjectLookupWidget extends OOUI\TextInputWidget {}

class PageLookupWidget extends OOUI\TextInputWidget {
	public function getConfig(&$config) {
		$config['site'] = get('project');
		return parent::getConfig($config);
	}
}

class HTMLWidget extends OOUI\Widget {
	public function __construct($config) {
		parent::__construct($config);
		$this->html = $config['html'];
		$this->appendContent(new OOUI\HtmlSnippet($this->html));
	}

	protected function getJavaScriptClassName() {
		return 'OO.ui.Widget';
	}

	public function getConfig(&$config) {
		$config['$content'] = $this->html;
		return parent::getConfig($config);
	}
}

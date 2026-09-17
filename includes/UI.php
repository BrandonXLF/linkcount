<?php

use Krinkle\Intuition\Intuition;

class UI {
	public static function init($lang = null) {
		/** @global Intuition $I18N */
		global $I18N;

		OOUI\Theme::setSingleton(new OOUI\WikimediaUITheme);

		$I18N = new Intuition([
			'domain' => 'linkcount',
			'globalfunctions' => true,
			'lang' => $lang,
		]);

		$I18N->registerDomain('linkcount', __DIR__ . '/../i18n');
	}
}

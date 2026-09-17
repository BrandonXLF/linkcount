<?php

use Krinkle\Intuition\Intuition;

class UI {
	public static function init($lang = null) {
		/** @global Intuition $I18N */
		global $I18N;
		global $COMMIT;

		OOUI\Theme::setSingleton(new OOUI\WikimediaUITheme);

		$I18N = new Intuition([
			'domain' => 'linkcount',
			'lang' => $lang,
		]);

		$I18N->registerDomain('linkcount', __DIR__ . '/../i18n');

		$COMMIT = exec('git rev-parse --short HEAD');
	}
}

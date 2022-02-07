<?php

OOUI\Theme::setSingleton(new OOUI\WikimediaUITheme);

function get($param) {
	return trim($_GET[$param] ?? '');
}

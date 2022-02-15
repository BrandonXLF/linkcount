<?php

require '../vendor/autoload.php';

echo (new JsLoader(
	'../node_modules/jquery/dist/jquery.min.js',
	'../node_modules/oojs/dist/oojs.min.js',
	'../node_modules/oojs-ui/dist/oojs-ui.min.js',
	'../node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.js',
	'../static/NamespaceLookupWidget.js',
	'../static/PageLookupWidget.js',
	'../static/ProjectLookupWidget.js',
	'../static/index.js'
))->getContent();

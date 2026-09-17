<?php

require '../vendor/autoload.php';

echo (new ResourceLoader(
	'../node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.css',
	'../static/index.css'
))->getContent('all.css', ResourceLoader::CONTENT_CSS, get('ckey'));

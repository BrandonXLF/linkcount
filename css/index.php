<?php

require '../vendor/autoload.php';

echo (new ResourceLoader(
	ResourceLoader::CONTENT_CSS,
	'../node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.css',
	'../static/index.css'
))->getContent();

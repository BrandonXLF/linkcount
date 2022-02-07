<?php

require '../vendor/autoload.php';

if (!$_SERVER['QUERY_STRING']) {
	echo APIHelp::getHTML();
	exit;
}

echo (new LinkCount(get('page'), get('project'), get('namespaces')))->json();

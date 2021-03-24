<?php

require __DIR__ . '/../includes/Config.php';

if ($_SERVER['QUERY_STRING']) {
	require __DIR__ . '/../includes/LinkCount.php';
	die((new LinkCount($_GET['page'] ?? '', $_GET['project'] ?? '', $_GET['namespaces'] ?? ''))->json());
}

require __DIR__ . '/../includes/APIHelp.php';
echo APIHelp::html();

<?php

require '../vendor/autoload.php';

if ($_SERVER['QUERY_STRING']) {
	die((new LinkCount($_GET['page'] ?? '', $_GET['project'] ?? '', $_GET['namespaces'] ?? ''))->json());
}

echo APIHelp::html();

<?php

require '../vendor/autoload.php';

UI::init();

$linkCount = new LinkCount(get('page'), get('project'), get('namespaces'));

header('Content-Type: application/json');
echo $linkCount->getPageUpdateJson();

?>

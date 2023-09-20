<?php

require '../vendor/autoload.php';

$linkCount = new LinkCount(get('page'), get('project'), get('namespaces'));

header('Content-Type: application/json');
echo $linkCount->getPageUpdateJson();

?>

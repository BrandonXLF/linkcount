<?php

require __DIR__ . '/../includes/Config.php';
require __DIR__ . '/../includes/LinkCount.php';
echo (new LinkCount($_GET['page'] ?? '', $_GET['project'] ?? '', $_GET['namespaces'] ?? ''))->html();

<?php

require '../vendor/autoload.php';

echo (new LinkCount($_GET['page'] ?? '', $_GET['project'] ?? '', $_GET['namespaces'] ?? ''))->html();

<?php

require '../vendor/autoload.php';

echo (new LinkCount(get('page'), get('project'), get('namespaces')))->html();

<?php

require '../vendor/autoload.php';

header('Content-Type: application/json');
echo (new ProjectPrefixSearch(get('prefix')))->getJson();

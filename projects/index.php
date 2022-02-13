<?php

require '../vendor/autoload.php';

echo (new ProjectPrefixSearch(get('prefix')))->getJson();

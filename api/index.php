<?php

if ($_SERVER['QUERY_STRING']) {
    require __DIR__ . '/../includes/LinkCount.php';
    echo (new LinkCount())->json();
} else {
    require __DIR__ . '/../includes/APIHelp.php';
    echo APIHelp::html();
}
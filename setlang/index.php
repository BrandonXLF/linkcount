<?php

use Krinkle\Intuition\Intuition;

require '../vendor/autoload.php';

UI::init();

/** @global Intuition $I18N */
global $I18N;

$I18N->setCookie('userlang', $_POST['lang'] ?? '');

$qs = http_build_query($_GET);
header('Location: ../' . ($qs ? '?' . $qs : ''));

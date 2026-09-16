<?php

function get($param) {
	return trim($_GET[$param] ?? '');
}

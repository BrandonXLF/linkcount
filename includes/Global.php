<?php

use Krinkle\Intuition\Intuition;

function get($param) {
	return trim($_GET[$param] ?? '');
}

function rawmsg($key, $options = []) : string {
	/** @global Intuition $I18N */
	global $I18N;
	return $I18N->msg( $key, $options );
}

function escmsg($key, $options = []) : string {
	/** @global Intuition $I18N */
	global $I18N;
	$options = array_merge( $options, [ 'escape' => 'html' ] );
	return $I18N->msg( $key, $options );
}

<?php

/**
 * @param string $payment_method
 */
function buckaroo_autoload( $payment_method ) {
	$class_name = strtolower( $payment_method );
	$path       = __DIR__ . "/{$class_name}/{$class_name}.php";
	if ( file_exists( $path ) ) {
		require_once $path;
	} else {
		die( 'Class not found!' );
	}
}

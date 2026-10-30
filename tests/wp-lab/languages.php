<?php

define( 'LAB', __DIR__ . '/.lab' );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/wpcli-shim.php';
require LAB . '/wordpress/wp-load.php';

$result = vgarco_setup_languages();
echo implode( "\n", $result['log'] ) . "\n";
if ( ! $result['ok'] ) {
	exit( 1 );
}

<?php
$root = __DIR__ . '/.lab/wordpress';
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $root . $path;

$provision = (string) @file_get_contents( dirname( __DIR__, 2 ) . '/scripts/ops/provision.sh' );
if ( preg_match( '/security-headers\.inc [^\n]*<<\'EOF\'[^\n]*\n(.*?)\nEOF/s', $provision, $block ) && preg_match( '/map "[^"]*" \$vg_csp \{\n\s*default "(.*?)";\n(.*?)\n\}/s', $provision, $map ) ) {
	$csp = $map[1];
	if ( preg_match_all( '/"~(.*?)"\s+"";/', $map[2], $skips ) ) {
		$key = $path . '|' . ( $_SERVER['HTTP_COOKIE'] ?? '' );
		foreach ( $skips[1] as $pattern ) {
			if ( preg_match( '~' . str_replace( '~', '\~', $pattern ) . '~', $key ) ) {
				$csp = '';
				break;
			}
		}
	}
	preg_match_all( '/^add_header (\S+) (?:"(.*)"|(\$vg_csp)) always;$/m', $block[1], $headers, PREG_SET_ORDER );
	foreach ( $headers as $h ) {
		$value = '' !== ( $h[3] ?? '' ) ? $csp : $h[2];
		if ( '' !== $value ) {
			header( $h[1] . ': ' . $value );
		}
	}
}

if ( '/' !== $path && is_file( $file ) ) {
	return false;
}
if ( is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
	chdir( $file );
	require rtrim( $file, '/' ) . '/index.php';
	return true;
}
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir( $root );
require $root . '/index.php';

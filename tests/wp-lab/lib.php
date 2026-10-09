<?php

error_reporting( E_ALL );
define( 'LAB', __DIR__ . '/.lab' );
define( 'REPO', dirname( __DIR__, 2 ) );

require __DIR__ . '/wpcli-shim.php';

$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
require LAB . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/post.php';

$GLOBALS['it_pass'] = 0;
$GLOBALS['it_fail'] = array();

function check( string $label, bool $condition ): void {
	if ( $condition ) {
		++$GLOBALS['it_pass'];
	} else {
		$GLOBALS['it_fail'][] = $label;
	}
}

function same( string $label, $expected, $actual ): void {
	check( $label . ' — mong đợi ' . var_export( $expected, true ) . ', nhận ' . var_export( $actual, true ), $expected === $actual );
}

function finish(): void {
	if ( $GLOBALS['it_fail'] ) {
		foreach ( $GLOBALS['it_fail'] as $line ) {
			echo "  ✗ {$line}\n";
		}
		echo 'THẤT BẠI ' . count( $GLOBALS['it_fail'] ) . ' / ' . ( $GLOBALS['it_pass'] + count( $GLOBALS['it_fail'] ) ) . "\n";
		exit( 1 );
	}
	echo "ĐẠT {$GLOBALS['it_pass']}/{$GLOBALS['it_pass']}\n";
}

function http( string $method, string $path, array $o = array() ): array {
	$ch = curl_init( 'http://localhost:8080' . $path );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => ! empty( $o['follow'] ),
			CURLOPT_HTTPHEADER     => $o['headers'] ?? array(),
			CURLOPT_TIMEOUT        => 60,
		)
	);
	if ( isset( $o['body'] ) ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $o['body'] );
	}
	if ( isset( $o['cookies'] ) ) {
		curl_setopt( $ch, CURLOPT_COOKIEJAR, $o['cookies'] );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, $o['cookies'] );
	}
	$raw  = (string) curl_exec( $ch );
	$size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );
	$body = substr( $raw, $size );
	return array( 'status' => $code, 'headers' => substr( $raw, 0, $size ), 'body' => $body, 'json' => json_decode( $body, true ) );
}

function lab_admin_password(): string {
	preg_match( '/password: (.+)/', (string) file_get_contents( LAB . '/credentials.txt' ), $m );
	return trim( $m[1] ?? '' );
}

function lab_login( string $user = 'labadmin', ?string $password = null ): string {
	$jar = LAB . '/cookies-' . $user . '.txt';
	@unlink( $jar );
	http( 'GET', '/wp-login.php', array( 'cookies' => $jar ) );
	http(
		'POST',
		'/wp-login.php',
		array(
			'cookies' => $jar,
			'body'    => http_build_query( array( 'log' => $user, 'pwd' => $password ?? lab_admin_password(), 'wp-submit' => 'Log In', 'redirect_to' => 'http://localhost:8080/wp-admin/', 'testcookie' => '1' ) ),
			'headers' => array( 'Cookie: wordpress_test_cookie=WP%20Cookie%20check' ),
		)
	);
	return $jar;
}

function lab_clear_mail(): void {
	@unlink( LAB . '/mail.log' );
}

function lab_mails(): array {
	$lines = is_file( LAB . '/mail.log' ) ? file( LAB . '/mail.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
	return array_map( static fn( $l ) => json_decode( $l, true ), $lines );
}

function lab_cookie_for( int $user_id ): string {
	$exp   = time() + 3600;
	$token = WP_Session_Tokens::get_instance( $user_id )->create( $exp );
	return AUTH_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $user_id, $exp, 'auth', $token ) ) . '; '
		. LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $user_id, $exp, 'logged_in', $token ) );
}

function lab_token( string $code = 'AB7K9', int $age = 10 ): string {
	$token = bin2hex( random_bytes( 16 ) );
	set_transient( 'vg_apply_' . $token, array( 'issued' => time() - $age, 'hash' => vgarco_captcha_hash( $code, wp_salt( 'auth' ) ) ), 600 );
	return $token;
}

function lab_apply( array $fields, ?string $file = null, string $name = 'cv.pdf', array $headers = array() ): array {
	$body = $fields;
	if ( null !== $file ) {
		$body['cv'] = new CURLFile( $file, 'application/octet-stream', $name );
	}
	$has_ip = (bool) preg_grep( '/^CF-Connecting-IP:/i', $headers );
	return http( 'POST', '/wp-json/vgarco/v1/apply', array( 'body' => $body, 'headers' => array_merge( array( 'Origin: http://localhost:8080' ), $has_ip ? array() : array( 'CF-Connecting-IP: 198.51.100.' . random_int( 1, 250 ) ), $headers ) ) );
}

<?php
/**
 * Plugin Name: VGARCO Loader
 * Description: Phần luôn chạy, không tắt được từ trang quản trị: thu hẹp bề mặt tấn công, chặn thư thật ở staging, tắt bình luận.
 * Version: 0.1.0
 * Requires PHP: 8.3
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'xmlrpc_enabled', '__return_false' );
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'XML-RPC đã bị tắt.' );
}
add_filter(
	'wp_headers',
	static function ( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

add_filter( 'wp_is_application_passwords_available', '__return_false' );

add_action(
	'template_redirect',
	static function (): void {
		if ( is_author() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	},
	1
);

add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);

add_filter(
	'rest_endpoints',
	static function ( array $endpoints ): array {
		if ( ! is_user_logged_in() ) {
			foreach ( array_keys( $endpoints ) as $route ) {
				if ( str_starts_with( $route, '/wp/v2/users' ) ) {
					unset( $endpoints[ $route ] );
				}
			}
		}
		return $endpoints;
	}
);

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );

add_action(
	'admin_menu',
	static function (): void {
		remove_menu_page( 'edit-comments.php' );
	}
);
add_action(
	'wp_before_admin_bar_render',
	static function (): void {
		global $wp_admin_bar;
		if ( $wp_admin_bar ) {
			$wp_admin_bar->remove_menu( 'comments' );
		}
	}
);

function vgarco_mail_addresses( $value ): array {
	$text = is_array( $value ) ? implode( ',', array_map( 'strval', $value ) ) : (string) $value;
	preg_match_all( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)+/', $text, $found );
	return array_map( 'strtolower', $found[0] );
}

add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, array $atts ) {
		if ( 'staging' !== wp_get_environment_type() ) {
			return $short_circuit;
		}

		$allow = defined( 'VG_MAIL_ALLOW' ) ? array_map( 'strtolower', (array) VG_MAIL_ALLOW ) : array();

		$recipients = vgarco_mail_addresses( $atts['to'] ?? '' );
		$headers    = $atts['headers'] ?? array();
		$lines      = is_array( $headers ) ? $headers : preg_split( '/\r\n|\r|\n/', (string) $headers );
		foreach ( $lines as $line ) {
			if ( is_string( $line ) && preg_match( '/^\s*(cc|bcc)\s*:(.*)$/i', $line, $m ) ) {
				$recipients = array_merge( $recipients, vgarco_mail_addresses( $m[2] ) );
			}
		}

		$blocked = array();
		foreach ( array_unique( $recipients ) as $address ) {
			$domain  = '@' . substr( strrchr( $address, '@' ), 1 );
			$allowed = in_array( $address, $allow, true ) || in_array( $domain, $allow, true );
			if ( ! $allowed ) {
				$blocked[] = $address;
			}
		}

		if ( $blocked ) {
			error_log( 'VGARCO staging: đã chặn thư tới ' . implode( ', ', $blocked ) );
			return true;
		}

		return $short_circuit;
	},
	10,
	2
);

<?php
/**
 * Plugin Name: LAB — giả lập ngôn ngữ của WPML (?lab_lang=de)
 * Description: CHỈ DÙNG TRONG MÔI TRƯỜNG THỬ. Phòng thử không có WPML, nên plugin này giả lập vừa đủ để xem giao diện: (1) locale của giao diện công khai là tiếng Việt (WPML đặt locale theo ngôn ngữ),
 */

defined( 'ABSPATH' ) || exit;

function lab_lang_is_de(): bool {
	return isset( $_GET['lab_lang'] ) && 'de' === $_GET['lab_lang'] && ! is_admin();
}

add_filter(
	'locale',
	static function ( $locale ) {
		if ( is_admin() ) {
			return $locale;
		}
		return lab_lang_is_de() ? 'de_DE' : 'vi';
	}
);

add_filter(
	'wpml_active_languages',
	static function ( $languages ) {
		if ( is_admin() ) {
			return $languages;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = (string) strtok( $uri, '?' );
		parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $query );
		unset( $query['lab_lang'] );
		$vi_url = home_url( $path ) . ( $query ? '?' . http_build_query( $query ) : '' );
		$de_url = home_url( $path ) . '?' . http_build_query( $query + array( 'lab_lang' => 'de' ) );
		$de     = lab_lang_is_de();
		return array(
			'vi' => array( 'code' => 'vi', 'native_name' => 'Tiếng Việt', 'url' => $vi_url, 'active' => $de ? 0 : 1, 'missing' => 0 ),
			'de' => array( 'code' => 'de', 'native_name' => 'Deutsch', 'url' => $de_url, 'active' => $de ? 1 : 0, 'missing' => 0 ),
		);
	}
);

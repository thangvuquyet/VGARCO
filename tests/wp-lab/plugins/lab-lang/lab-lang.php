<?php
/**
 * Plugin Name: LAB — giả lập ngôn ngữ của WPML (?lab_lang=de)
 * Description: CHỈ DÙNG TRONG MÔI TRƯỜNG THỬ. Phòng thử không có WPML, nên plugin này giả lập vừa đủ để xem giao diện: (1) locale của giao diện công khai là tiếng Việt (WPML đặt locale theo ngôn ngữ),
 *              hoặc tiếng Đức khi có ?lab_lang=de (nạp .mo của theme và plugin: kiểm tra bố cục chuỗi dài, thuộc tính lang); (2) bộ lọc wpml_active_languages để theme vẽ bộ chuyển VI/DE và thẻ hreflang.
 *              KHÔNG thay cho WPML: không có URL /de/, không có nội dung dịch, không dịch đường dẫn.
 */

defined( 'ABSPATH' ) || exit;

/** Đang xem bản tiếng Đức giả lập? Chỉ giao diện công khai, không phải trang quản trị. */
function lab_lang_is_de(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ môi trường thử
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

// Danh sách ngôn ngữ như WPML trả về (mã → url, active, native_name, missing). Bản Đức dùng ?lab_lang=de; bản Việt là cùng địa chỉ không có tham số đó.
add_filter(
	'wpml_active_languages',
	static function ( $languages ) {
		if ( is_admin() ) {
			return $languages;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
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

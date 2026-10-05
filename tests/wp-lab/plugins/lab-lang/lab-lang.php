<?php
/**
 * Plugin Name: LAB — ép ngôn ngữ giao diện bằng ?lab_lang=de
 * Description: CHỈ DÙNG TRONG MÔI TRƯỜNG THỬ. Không có WPML trong phòng thử, nên dùng tham số ?lab_lang=de để nạp bản dịch tiếng Đức (.mo) của theme và plugin
 *              cho MỘT yêu cầu ở giao diện công khai: kiểm tra bố cục với chuỗi tiếng Đức dài hơn, thuộc tính lang của trang. Không thay cho WPML (URL /de/, nội dung dịch, bộ chuyển ngôn ngữ).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'locale',
	static function ( $locale ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ môi trường thử
		return ( isset( $_GET['lab_lang'] ) && 'de' === $_GET['lab_lang'] && ! is_admin() ) ? 'de_DE' : $locale;
	}
);

<?php
/**
 * Gỡ tài nguyên WordPress tự chèn mà giao diện không dùng: nhẹ trang, ít bề mặt lộ thông tin.
 * Không nạp jQuery ở front-end: giao diện không phụ thuộc jQuery, WordPress chỉ nạp khi có plugin yêu cầu.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function (): void {
		// Thông tin phiên bản và điểm truy cập không dùng.
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );

		// Emoji.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
	}
);

// Kiểu dáng của khối và bảng màu sinh từ theme.json chỉ dành cho trình soạn thảo.
// Giao diện tự định kiểu nội dung bài viết (.entry-content) trong SCSS.
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'global-styles' );
	},
	100
);
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

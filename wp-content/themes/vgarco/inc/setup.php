<?php
/**
 * Khai báo hỗ trợ của giao diện.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		// Mọi chuỗi bọc gettext, text domain "vgarco"; bản dịch .po/.mo nằm trong languages/.
		load_theme_textdomain( 'vgarco', get_theme_file_path( 'languages' ) );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' ); // Cần cho ảnh đầu bài của kiểu nội dung tin_tuc.
		add_theme_support(
			'html5',
			array( 'search-form', 'gallery', 'caption', 'style', 'script' )
		);

		register_nav_menus(
			array(
				'primary' => 'Menu chính',
				'footer'  => 'Menu chân trang',
			)
		);
	}
);

// Giao diện đọc cấu hình từ plugin vgarco-core. Thiếu plugin thì báo rõ, không để trang chết vì lỗi hàm không tồn tại.
if ( ! function_exists( 'vgarco_option' ) ) {
	function vgarco_option( string $path, $fallback = '' ) {
		return $fallback;
	}

	function vgarco_directions_url(): string {
		return '';
	}

	add_action(
		'admin_notices',
		static function (): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p><strong>Giao diện VGARCO</strong> cần plugin <em>VGARCO Core</em> đang hoạt động.</p></div>';
			}
		}
	);
}

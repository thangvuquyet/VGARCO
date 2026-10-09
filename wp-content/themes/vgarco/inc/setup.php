<?php

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'vgarco', get_theme_file_path( 'languages' ) );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
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

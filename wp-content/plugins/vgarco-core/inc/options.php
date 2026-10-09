<?php

defined( 'ABSPATH' ) || exit;

add_action(
	'acf/init',
	static function (): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		acf_add_options_page(
			array(
				'page_title'      => 'Cấu hình VGARCO',
				'menu_title'      => 'Cấu hình VGARCO',
				'menu_slug'       => 'vgarco-cau-hinh',
				'capability'      => 'manage_options',
				'position'        => 3,
				'icon_url'        => 'dashicons-admin-generic',
				'redirect'        => false,
				'update_button'   => 'Lưu cấu hình',
				'updated_message' => 'Đã lưu cấu hình.',
			)
		);
	}
);

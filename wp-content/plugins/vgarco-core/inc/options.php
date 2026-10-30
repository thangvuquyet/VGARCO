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

		foreach ( vgarco_secondary_languages() as $lang ) {
			acf_add_options_page(
				array(
					'page_title'      => 'Cấu hình VGARCO — bản ' . strtoupper( $lang ),
					'menu_title'      => 'Bản ' . strtoupper( $lang ),
					'menu_slug'       => 'vgarco-cau-hinh-' . $lang,
					'parent_slug'     => 'vgarco-cau-hinh',
					'capability'      => 'manage_options',
					'update_button'   => 'Lưu bản ' . strtoupper( $lang ),
					'updated_message' => 'Đã lưu cấu hình bản ' . strtoupper( $lang ) . '.',
				)
			);
		}
	}
);

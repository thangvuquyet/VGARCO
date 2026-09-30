<?php
/**
 * Trang "Cấu hình VGARCO" (ACF PRO Options Page).
 * Chỉ quản trị viên chỉnh được: chứa thông tin pháp nhân và số liệu công bố với khách hàng.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'acf/init',
	static function (): void {
		// acf_add_options_page chỉ có ở ACF PRO. Thiếu thì thông báo ở inc/fields.php.
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

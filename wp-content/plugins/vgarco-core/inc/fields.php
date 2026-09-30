<?php
/**
 * Trường dữ liệu ACF — khai báo bằng mã (Local field groups), nằm trong Git.
 *
 * Vì sao khai báo bằng mã thay vì nhập trong trang quản trị: cấu hình trường được kiểm soát phiên bản,
 * đồng nhất giữa local/staging/production, và biên tập viên không sửa nhầm được cấu trúc.
 *
 * Độ ưu tiên dịch (wpml_cf_preferences, do ACFML đọc):
 *   'translate' — mỗi ngôn ngữ một giá trị (chữ hiển thị);
 *   'copy'      — bản dịch luôn lấy giá trị của bản gốc (ảnh, số, lựa chọn);
 *   'ignore'    — không đồng bộ.
 * Chưa kiểm chứng trên WPML/ACFML thật; kiểm ở spike ngày N2 (phương án mục 6.7).
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

require_once VGARCO_CORE_DIR . 'inc/fields/du-an.php';
require_once VGARCO_CORE_DIR . 'inc/fields/linh-vuc.php';
require_once VGARCO_CORE_DIR . 'inc/fields/tin-tuc.php';
require_once VGARCO_CORE_DIR . 'inc/fields/vi-tri.php';
require_once VGARCO_CORE_DIR . 'inc/fields/cau-hinh.php';

/**
 * Chuyển tên chế độ dịch sang số của WPML (0 bỏ qua, 1 sao chép, 2 dịch, 3 sao chép một lần).
 */
function vgarco_wpml_pref( string $mode ): int {
	$map = array(
		'ignore'    => 0,
		'copy'      => 1,
		'translate' => 2,
		'copy_once' => 3,
	);
	return $map[ $mode ] ?? 0;
}

/**
 * Tạo hàm dựng trường cho một nhóm. Khóa trường sinh cố định theo tiền tố + tên,
 * nên không đổi giữa các lần triển khai (ACF dùng khóa để nối dữ liệu đã lưu).
 *
 * @param string $prefix Tiền tố duy nhất của nhóm.
 */
function vgarco_field_builder( string $prefix ): Closure {
	return static function ( string $name, string $label, string $type, string $wpml, array $args = array() ) use ( $prefix ): array {
		return array_merge(
			array(
				'key'                 => "field_vg_{$prefix}_{$name}",
				'name'                => $name,
				'label'               => $label,
				'type'                => $type,
				'wpml_cf_preferences' => vgarco_wpml_pref( $wpml ),
			),
			$args
		);
	};
}

/**
 * Quy tắc vị trí: nhóm trường chỉ hiện ở một kiểu nội dung.
 */
function vgarco_location_post_type( string $post_type ): array {
	return array(
		array(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => $post_type,
			),
		),
	);
}

/**
 * Tất cả nhóm trường của website.
 *
 * @return array[]
 */
function vgarco_field_groups(): array {
	return array_merge(
		array(
			vgarco_fields_du_an(),
			vgarco_fields_linh_vuc(),
			vgarco_fields_tin_tuc(),
			vgarco_fields_vi_tri(),
		),
		vgarco_fields_cau_hinh()
	);
}

add_action(
	'acf/include_fields',
	static function (): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}
		foreach ( vgarco_field_groups() as $group ) {
			acf_add_local_field_group( $group );
		}
	}
);

/**
 * Kiểm tra định dạng mã: chữ thường không dấu, số, gạch ngang.
 *
 * @param true|string $valid Kết quả kiểm tra trước đó.
 * @param mixed       $value Giá trị nhập.
 * @return true|string
 */
function vgarco_validate_slug_value( $valid, $value ) {
	if ( true !== $valid ) {
		return $valid;
	}
	if ( is_string( $value ) && '' !== $value && ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value ) ) {
		return 'Chỉ dùng chữ thường không dấu, chữ số và dấu gạch ngang. Ví dụ: bao-tang-qdnd-lao';
	}
	return $valid;
}
add_filter( 'acf/validate_value/key=field_vg_du_an_ma', 'vgarco_validate_slug_value', 10, 2 );
add_filter( 'acf/validate_value/key=field_vg_linh_vuc_neo', 'vgarco_validate_slug_value', 10, 2 );

// Thiếu ACF PRO thì trang quản trị không có trường nào — báo rõ thay vì để biên tập viên tự dò.
add_action(
	'admin_notices',
	static function (): void {
		if ( function_exists( 'acf_add_options_page' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>VGARCO Core:</strong> cần cài và kích hoạt <em>ACF PRO</em> '
			. '(bản miễn phí không có trang cấu hình và trường lặp) để nhập dữ liệu dự án, tuyển dụng và cấu hình chung.</p></div>';
	}
);

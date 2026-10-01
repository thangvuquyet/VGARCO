<?php
/**
 * Phân loại (mục 6.1 của phương án).
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bộ nhãn chuẩn cho một phân loại.
 *
 * @param string $name Tên hiển thị.
 * @return array
 */
function vgarco_tax_labels( string $name ): array {
	$lower = mb_strtolower( $name );

	return array(
		'name'          => $name,
		'singular_name' => $name,
		'menu_name'     => $name,
		'all_items'     => "Tất cả {$lower}",
		'edit_item'     => "Sửa {$lower}",
		'view_item'     => "Xem {$lower}",
		'update_item'   => "Cập nhật {$lower}",
		'add_new_item'  => "Thêm {$lower} mới",
		'new_item_name' => "Tên {$lower} mới",
		'search_items'  => "Tìm {$lower}",
		'not_found'     => "Chưa có {$lower} nào",
	);
}

function vgarco_register_taxonomies(): void {
	// Loại công trình — 7 nhóm cố định, một dự án có thể thuộc nhiều nhóm
	// (Học viện thuộc cả Giáo dục và Quân sự & an ninh). Đường dẫn: /du-an/loai/<nhóm>/.
	register_taxonomy(
		'loai_cong_trinh',
		array( 'du_an' ),
		array(
			'labels'            => vgarco_tax_labels( 'Loại công trình' ),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'         => 'du-an/loai',
				'with_front'   => false,
				'hierarchical' => false,
			),
		)
	);

	// Chuyên mục tin — 3 mục cố định.
	register_taxonomy(
		'chuyen_muc',
		array( 'tin_tuc' ),
		array(
			'labels'            => vgarco_tax_labels( 'Chuyên mục tin' ),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'         => 'tin-tuc/chuyen-muc',
				'with_front'   => false,
				'hierarchical' => false,
			),
		)
	);

	// Bộ lọc tuyển dụng: chỉ dùng làm điều kiện lọc, không có trang riêng.
	// Dạng phân cấp để biên tập viên tích chọn từ danh sách có sẵn thay vì gõ tay (tránh sai chính tả).
	register_taxonomy(
		'noi_lam_viec',
		array( 'vi_tri' ),
		array(
			'labels'             => vgarco_tax_labels( 'Nơi làm việc' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'hierarchical'       => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'rewrite'            => false,
		)
	);

	register_taxonomy(
		'phong_ban',
		array( 'vi_tri' ),
		array(
			'labels'             => vgarco_tax_labels( 'Phòng ban' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'hierarchical'       => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'rewrite'            => false,
		)
	);
}
// Ưu tiên 5, trước kiểu nội dung (ưu tiên 10): WordPress xếp quy tắc đường dẫn theo thứ tự đăng ký. Đăng ký sau thì quy tắc ảnh đính kèm
// của dự án (du-an/[^/]+/([^/]+)/) khớp trước và /du-an/loai/<nhóm>/ thành 404 (phát hiện khi chạy trên WordPress 7.1 thật).
add_action( 'init', 'vgarco_register_taxonomies', 5 );

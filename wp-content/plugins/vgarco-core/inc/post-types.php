<?php
/**
 * Kiểu nội dung (mục 6.1 của phương án). Nằm trong plugin, không nằm trong theme.
 *
 * Tin tức dùng kiểu riêng `tin_tuc` thay vì `post`: WPML không dịch được phần tiền tố cố định
 * trong đường dẫn của bài viết thường, nên bản tiếng Đức sẽ ra /de/tin-tuc/... thay vì /de/aktuelles/...
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bộ nhãn tiếng Việt chuẩn cho một kiểu nội dung.
 *
 * @param string $name Tên hiển thị, ví dụ "Dự án".
 * @return array
 */
function vgarco_cpt_labels( string $name ): array {
	$lower = mb_strtolower( $name );

	return array(
		'name'               => $name,
		'singular_name'      => $name,
		'menu_name'          => $name,
		'add_new'            => 'Thêm mới',
		'add_new_item'       => "Thêm {$lower} mới",
		'edit_item'          => "Sửa {$lower}",
		'new_item'           => "{$name} mới",
		'view_item'          => "Xem {$lower}",
		'view_items'         => "Xem {$lower}",
		'search_items'       => "Tìm {$lower}",
		'not_found'          => "Chưa có {$lower} nào",
		'not_found_in_trash' => "Không có {$lower} trong thùng rác",
		'all_items'          => "Tất cả {$lower}",
		'archives'           => $name,
	);
}

function vgarco_register_post_types(): void {
	// Dự án: 20 hồ sơ, sắp theo "Thứ tự" (menu_order) do chủ đầu tư quyết định.
	register_post_type(
		'du_an',
		array(
			'labels'              => vgarco_cpt_labels( 'Dự án' ),
			'public'              => true,
			'exclude_from_search' => true, // Tìm kiếm dự án chạy qua REST riêng, không dùng /?s=.
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'du-an',
				'with_front' => false,
			),
			'menu_icon'           => 'dashicons-building',
			'menu_position'       => 5,
			'supports'            => array( 'title', 'editor', 'page-attributes', 'revisions' ),
			'show_in_rest'        => true,
		)
	);

	// Lĩnh vực hoạt động: hiển thị trong một trang duy nhất, mỗi mục có mã neo — không có trang riêng.
	register_post_type(
		'linh_vuc',
		array(
			'labels'             => vgarco_cpt_labels( 'Lĩnh vực hoạt động' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'has_archive'        => false,
			'rewrite'            => false,
			'menu_icon'          => 'dashicons-networking',
			'menu_position'      => 6,
			'supports'           => array( 'title', 'editor', 'page-attributes', 'revisions' ),
			'show_in_rest'       => true,
		)
	);

	register_post_type(
		'tin_tuc',
		array(
			'labels'              => array_merge(
				vgarco_cpt_labels( 'Tin tức' ),
				array(
					'featured_image'        => 'Ảnh đầu bài',
					'set_featured_image'    => 'Chọn ảnh đầu bài',
					'remove_featured_image' => 'Bỏ ảnh đầu bài',
					'use_featured_image'    => 'Dùng làm ảnh đầu bài',
				)
			),
			'public'              => true,
			'exclude_from_search' => true,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'tin-tuc',
				'with_front' => false,
			),
			'menu_icon'           => 'dashicons-megaphone',
			'menu_position'       => 7,
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'show_in_rest'        => true,
		)
	);

	// Vị trí tuyển dụng: có trang chi tiết, KHÔNG có trang lưu trữ — trang /tuyen-dung/ là một trang tĩnh.
	register_post_type(
		'vi_tri',
		array(
			'labels'              => vgarco_cpt_labels( 'Vị trí tuyển dụng' ),
			'public'              => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => array(
				'slug'       => 'tuyen-dung',
				'with_front' => false,
			),
			'menu_icon'           => 'dashicons-id-alt',
			'menu_position'       => 8,
			'supports'            => array( 'title', 'editor', 'revisions' ),
			'show_in_rest'        => true,
		)
	);
}
add_action( 'init', 'vgarco_register_post_types' );

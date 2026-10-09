<?php

defined( 'ABSPATH' ) || exit;

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
add_action( 'init', 'vgarco_register_taxonomies', 5 );

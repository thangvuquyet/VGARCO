<?php

defined( 'ABSPATH' ) || exit;

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
	register_post_type(
		'du_an',
		array(
			'labels'              => vgarco_cpt_labels( 'Dự án' ),
			'public'              => true,
			'exclude_from_search' => true,
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

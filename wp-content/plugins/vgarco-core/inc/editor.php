<?php
/**
 * Trình soạn thảo: giới hạn khối được dùng để biên tập viên không phá bố cục (mục 6.2),
 * và ẩn menu "Bài viết" mặc định (tin tức là kiểu nội dung riêng `tin_tuc`).
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'allowed_block_types_all',
	static function ( $allowed, $context ) {
		$post = $context->post ?? null;
		if ( ! $post ) {
			return $allowed;
		}

		$text = array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item' );
		$map  = array(
			'du_an'    => $text,
			'linh_vuc' => $text,
			'vi_tri'   => $text,
			'tin_tuc'  => array_merge( $text, array( 'core/quote', 'core/image' ) ),
			'page'     => array_merge( $text, array( 'core/quote', 'core/image' ) ),
		);

		return $map[ $post->post_type ] ?? $allowed;
	},
	10,
	2
);

add_action(
	'admin_menu',
	static function (): void {
		remove_menu_page( 'edit.php' );
	},
	99
);

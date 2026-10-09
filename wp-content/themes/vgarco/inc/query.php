<?php

defined( 'ABSPATH' ) || exit;

add_action(
	'pre_get_posts',
	static function ( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->is_post_type_archive( 'du_an' ) || $query->is_tax( 'loai_cong_trinh' ) ) {
			$query->set( 'posts_per_page', -1 );
			$query->set(
				'orderby',
				array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				)
			);
			$query->set( 'no_found_rows', true );
			return;
		}

		if ( $query->is_post_type_archive( 'tin_tuc' ) || $query->is_tax( 'chuyen_muc' ) ) {
			$query->set( 'posts_per_page', 9 );
		}
	}
);

<?php

defined( 'ABSPATH' ) || exit;

function vgarco_menu_node( string $title, string $url, bool $current = false, string $label = '', array $children = array() ): array {
	return array(
		'title'    => $title,
		'url'      => $url,
		'current'  => $current,
		'label'    => $label,
		'children' => $children,
	);
}

function vgarco_taxonomy_menu_children( string $taxonomy ): array {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'term_id',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$nodes = array();
	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			$nodes[] = vgarco_menu_node( $term->name, $link, is_tax( $taxonomy, $term->term_id ) );
		}
	}
	return $nodes;
}

function vgarco_default_menu( string $location ): array {
	$archive = static function ( string $post_type, string $fallback ): string {
		$url = get_post_type_archive_link( $post_type );
		return $url ? $url : home_url( $fallback );
	};

	if ( 'footer' === $location ) {
		return array(
			vgarco_menu_node( __( 'Giới thiệu', 'vgarco' ), vgarco_page_url( 'about' ) ),
			vgarco_menu_node( __( 'Dự án', 'vgarco' ), $archive( 'du_an', '/du-an/' ) ),
			vgarco_menu_node( __( 'Lĩnh vực hoạt động', 'vgarco' ), vgarco_page_url( 'services' ) ),
			vgarco_menu_node( __( 'Liên hệ', 'vgarco' ), vgarco_page_url( 'contact' ) ),
		);
	}

	$in_projects = is_post_type_archive( 'du_an' ) || is_singular( 'du_an' ) || is_tax( 'loai_cong_trinh' );
	$in_news     = is_post_type_archive( 'tin_tuc' ) || is_singular( 'tin_tuc' ) || is_tax( 'chuyen_muc' );
	$in_about    = is_page( array( 'gioi-thieu', 'ban-lanh-dao', 'phong-ban' ) );

	return array(
		vgarco_menu_node(
			__( 'Giới thiệu', 'vgarco' ),
			vgarco_page_url( 'about' ),
			$in_about,
			__( 'KHÁM PHÁ VGARCO', 'vgarco' ),
			array(
				vgarco_menu_node( __( 'Về công ty', 'vgarco' ), vgarco_page_url( 'about' ), is_page( 'gioi-thieu' ) ),
				vgarco_menu_node( __( 'Ban lãnh đạo', 'vgarco' ), vgarco_page_url( 'leadership' ), is_page( 'ban-lanh-dao' ) ),
				vgarco_menu_node( __( 'Phòng ban', 'vgarco' ), vgarco_page_url( 'departments' ), is_page( 'phong-ban' ) ),
			)
		),
		vgarco_menu_node( __( 'Lĩnh vực hoạt động', 'vgarco' ), vgarco_page_url( 'services' ), is_page( 'linh-vuc-hoat-dong' ) ),
		vgarco_menu_node(
			__( 'Dự án', 'vgarco' ),
			$archive( 'du_an', '/du-an/' ),
			$in_projects,
			__( 'DỰ ÁN THEO LOẠI CÔNG TRÌNH', 'vgarco' ),
			vgarco_taxonomy_menu_children( 'loai_cong_trinh' )
		),
		vgarco_menu_node(
			__( 'Tin tức', 'vgarco' ),
			$archive( 'tin_tuc', '/tin-tuc/' ),
			$in_news,
			__( 'TIN TỨC & HOẠT ĐỘNG', 'vgarco' ),
			vgarco_taxonomy_menu_children( 'chuyen_muc' )
		),
		vgarco_menu_node( __( 'Tuyển dụng', 'vgarco' ), vgarco_page_url( 'careers' ), is_singular( 'vi_tri' ) || is_page( 'tuyen-dung' ) ),
		vgarco_menu_node( __( 'Liên hệ', 'vgarco' ), vgarco_page_url( 'contact' ), is_page( 'lien-he' ) ),
	);
}

function vgarco_menu_item_is_current( $item ): bool {
	switch ( $item->type ) {
		case 'post_type':
			return is_singular() && (int) get_queried_object_id() === (int) $item->object_id;
		case 'taxonomy':
			return is_tax() && (int) get_queried_object_id() === (int) $item->object_id;
		case 'post_type_archive':
			return is_post_type_archive( $item->object ) || is_singular( $item->object );
	}
	return false;
}

function vgarco_menu_tree( string $location ): array {
	$menu_id = (int) ( get_nav_menu_locations()[ $location ] ?? 0 );
	$items   = $menu_id ? wp_get_nav_menu_items( $menu_id ) : false;

	if ( empty( $items ) ) {
		return vgarco_default_menu( $location );
	}

	$by_parent = array();
	foreach ( $items as $item ) {
		$by_parent[ (int) $item->menu_item_parent ][] = $item;
	}

	$build = static function ( int $parent ) use ( &$build, $by_parent ): array {
		$nodes = array();
		foreach ( $by_parent[ $parent ] ?? array() as $item ) {
			$children = $build( (int) $item->ID );
			$current  = vgarco_menu_item_is_current( $item ) || in_array( true, array_column( $children, 'current' ), true );
			$nodes[]  = vgarco_menu_node( $item->title, $item->url, $current, (string) $item->description, $children );
		}
		return $nodes;
	};

	return $build( 0 );
}

function vgarco_render_menu_link( array $node ): void {
	printf(
		'<a href="%1$s"%2$s>%3$s</a>',
		esc_url( $node['url'] ),
		$node['current'] ? ' aria-current="page"' : '',
		esc_html( $node['title'] )
	);
}

function vgarco_render_primary_nav( array $tree ): void {
	$index = 0;

	foreach ( $tree as $node ) {
		if ( empty( $node['children'] ) ) {
			vgarco_render_menu_link( $node );
			continue;
		}

		++$index;
		$panel_id = 'nav-panel-' . $index;

		echo '<div class="nav-group">';
		printf(
			'<button type="button" class="nav-trigger"%1$s aria-expanded="false" aria-controls="%2$s">%3$s<span class="chevron" aria-hidden="true"></span></button>',
			$node['current'] ? ' aria-current="true"' : '',
			esc_attr( $panel_id ),
			esc_html( $node['title'] )
		);
		printf( '<div class="nav-panel" id="%s" hidden>', esc_attr( $panel_id ) );
		if ( '' !== $node['label'] ) {
			printf( '<p class="nav-panel-label">%s</p>', esc_html( $node['label'] ) );
		}
		echo '<div class="nav-panel-grid">';
		foreach ( $node['children'] as $child ) {
			vgarco_render_menu_link( $child );
		}
		echo '</div></div></div>';
	}
}

<?php
/**
 * Menu: đọc menu WordPress (WPML đồng bộ hai bản ngôn ngữ) thành cây dữ liệu, rồi in ra HTML kiểu "disclosure".
 *
 * Nhãn của bảng menu con lấy từ trường "Mô tả" của mục cha (bật ở Tùy chọn màn hình → Mô tả).
 * Chưa gán menu cho vị trí thì dùng menu mặc định trong mã, để local/staging chạy được ngay.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

/**
 * Một nút của cây menu.
 *
 * @param string $title    Nhãn.
 * @param string $url      Địa chỉ.
 * @param bool   $current  Đang xem mục này hoặc mục con của nó.
 * @param string $label    Nhãn của bảng menu con (chỉ mục cha).
 * @param array  $children Các nút con.
 */
function vgarco_menu_node( string $title, string $url, bool $current = false, string $label = '', array $children = array() ): array {
	return array(
		'title'    => $title,
		'url'      => $url,
		'current'  => $current,
		'label'    => $label,
		'children' => $children,
	);
}

/**
 * Các nút con lấy từ một phân loại (mỗi thuật ngữ một liên kết).
 */
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

/**
 * Menu mặc định theo vị trí.
 * Địa chỉ trang tĩnh viết theo tiếng Việt; bản tiếng Đức dùng menu WordPress do WPML đồng bộ.
 */
function vgarco_default_menu( string $location ): array {
	$archive = static function ( string $post_type, string $fallback ): string {
		$url = get_post_type_archive_link( $post_type );
		return $url ? $url : home_url( $fallback );
	};

	if ( 'footer' === $location ) {
		return array(
			vgarco_menu_node( __( 'Giới thiệu', 'vgarco' ), home_url( '/gioi-thieu/' ) ),
			vgarco_menu_node( __( 'Dự án', 'vgarco' ), $archive( 'du_an', '/du-an/' ) ),
			vgarco_menu_node( __( 'Lĩnh vực hoạt động', 'vgarco' ), home_url( '/linh-vuc-hoat-dong/' ) ),
			vgarco_menu_node( __( 'Liên hệ', 'vgarco' ), home_url( '/lien-he/' ) ),
		);
	}

	$in_projects = is_post_type_archive( 'du_an' ) || is_singular( 'du_an' ) || is_tax( 'loai_cong_trinh' );
	$in_news     = is_post_type_archive( 'tin_tuc' ) || is_singular( 'tin_tuc' ) || is_tax( 'chuyen_muc' );
	$in_about    = is_page( array( 'gioi-thieu', 'ban-lanh-dao', 'phong-ban' ) );

	return array(
		vgarco_menu_node(
			__( 'Giới thiệu', 'vgarco' ),
			home_url( '/gioi-thieu/' ),
			$in_about,
			__( 'KHÁM PHÁ VGARCO', 'vgarco' ),
			array(
				vgarco_menu_node( __( 'Về công ty', 'vgarco' ), home_url( '/gioi-thieu/' ), is_page( 'gioi-thieu' ) ),
				vgarco_menu_node( __( 'Ban lãnh đạo', 'vgarco' ), home_url( '/gioi-thieu/ban-lanh-dao/' ), is_page( 'ban-lanh-dao' ) ),
				vgarco_menu_node( __( 'Phòng ban', 'vgarco' ), home_url( '/gioi-thieu/phong-ban/' ), is_page( 'phong-ban' ) ),
			)
		),
		vgarco_menu_node( __( 'Lĩnh vực hoạt động', 'vgarco' ), home_url( '/linh-vuc-hoat-dong/' ), is_page( 'linh-vuc-hoat-dong' ) ),
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
		vgarco_menu_node( __( 'Tuyển dụng', 'vgarco' ), home_url( '/tuyen-dung/' ), is_singular( 'vi_tri' ) || is_page( 'tuyen-dung' ) ),
		vgarco_menu_node( __( 'Liên hệ', 'vgarco' ), home_url( '/lien-he/' ), is_page( 'lien-he' ) ),
	);
}

/**
 * Mục menu WordPress này có phải trang đang xem không.
 * Chỉ xử lý các dạng website dùng: trang/bài viết, phân loại, trang lưu trữ của kiểu nội dung.
 *
 * @param WP_Post $item Mục menu.
 */
function vgarco_menu_item_is_current( $item ): bool {
	switch ( $item->type ) {
		case 'post_type':
			return is_singular() && (int) get_queried_object_id() === (int) $item->object_id;
		case 'taxonomy':
			return is_tax() && (int) get_queried_object_id() === (int) $item->object_id;
		case 'post_type_archive':
			// Trang chi tiết của cùng kiểu nội dung cũng tô sáng mục này (ví dụ đang xem một dự án → "Dự án").
			return is_post_type_archive( $item->object ) || is_singular( $item->object );
	}
	return false;
}

/**
 * Cây menu của một vị trí.
 *
 * @param string $location Vị trí đăng ký ở inc/setup.php.
 * @return array[] Danh sách nút (xem vgarco_menu_node).
 */
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

/**
 * In một liên kết của menu.
 */
function vgarco_render_menu_link( array $node ): void {
	printf(
		'<a href="%1$s"%2$s>%3$s</a>',
		esc_url( $node['url'] ),
		$node['current'] ? ' aria-current="page"' : '',
		esc_html( $node['title'] )
	);
}

/**
 * In menu chính: mục có mục con thành một nút mở bảng, mục không có con là liên kết thường.
 * Nút và bảng liên kết bằng aria-controls; bảng ẩn mặc định bằng thuộc tính hidden, JS (modules/nav.js) mở/đóng.
 *
 * @param array[] $tree Cây menu.
 */
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

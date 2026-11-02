<?php

$pages = array(
	'home'        => array( 'Trang chủ', 'trang-chu', '' ),
	'about'       => array( 'Về công ty', 'gioi-thieu', 'templates/about.php' ),
	'leadership'  => array( 'Ban lãnh đạo', 'gioi-thieu/ban-lanh-dao', 'templates/leadership.php' ),
	'departments' => array( 'Phòng ban', 'gioi-thieu/phong-ban', 'templates/departments.php' ),
	'services'    => array( 'Lĩnh vực hoạt động', 'linh-vuc-hoat-dong', 'templates/services.php' ),
	'careers'     => array( 'Tuyển dụng', 'tuyen-dung', 'templates/careers.php' ),
	'apply'       => array( 'Ứng tuyển', 'ung-tuyen', 'templates/apply.php' ),
	'contact'     => array( 'Liên hệ', 'lien-he', 'templates/contact.php' ),
	'privacy'     => array( 'Chính sách bảo vệ dữ liệu', 'chinh-sach-bao-ve-du-lieu', '' ),
);
$ids = array();
foreach ( $pages as $key => $def ) {
	list( $title, $path, $template ) = $def;
	$parts    = explode( '/', $path );
	$slug     = end( $parts );
	$parent   = count( $parts ) > 1 ? (int) ( get_page_by_path( $parts[0] )->ID ?? 0 ) : 0;
	$existing = get_page_by_path( $path );
	$content  = 'privacy' === $key ? '<p>Nội dung đang được cập nhật.</p>' : '';
	$id       = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => $parent, 'post_content' => $content ) );
	if ( is_wp_error( $id ) || ! $id ) {
		WP_CLI::error( "không tạo được trang {$path}" );
	}
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	$ids[ $key ] = $id;
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['home'] );
update_option( 'blogdescription', 'Tư vấn thiết kế, giám sát và quản lý dự án xây dựng' );
flush_rewrite_rules();
WP_CLI::success( 'trang: ' . wp_json_encode( $ids ) );

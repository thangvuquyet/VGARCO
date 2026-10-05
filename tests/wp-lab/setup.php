<?php
/**
 * Kích hoạt plugin/theme, đặt đường dẫn đẹp, tạo trang theo mẫu. Do bootstrap.mjs gọi; chạy lại được.
 */

define( 'LAB', __DIR__ . '/.lab' );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/';
require LAB . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';

function out( $m ) {
	echo $m . "\n";
}

// Plugin: shim ACF trước, rồi vgarco-core (kích hoạt chạy vgarco_core_activate: kiểu nội dung, phân loại, dữ liệu mồi), rồi bộ bắt thư.
foreach ( array( 'lab-acf-shim/lab-acf-shim.php', 'vgarco-core/vgarco-core.php', 'lab-mail-capture/lab-mail-capture.php', 'lab-lang/lab-lang.php' ) as $plugin ) {
	if ( ! is_plugin_active( $plugin ) ) {
		$r = activate_plugin( $plugin );
		out( ( is_wp_error( $r ) ? 'LỖI ' . $r->get_error_message() : 'đã kích hoạt' ) . ": {$plugin}" );
	}
}
switch_theme( 'vgarco' );
out( 'theme: ' . get_stylesheet() );

update_option( 'permalink_structure', '/%postname%/' );
update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );
update_option( 'blogdescription', 'Tư vấn thiết kế, giám sát và quản lý dự án xây dựng' );

// Trang theo mẫu (khóa → [tiêu đề, đường dẫn đầy đủ, mẫu]) — đúng bản đồ đường dẫn của phương án 6.6: Ban lãnh đạo và Phòng ban là
// trang CON của Về công ty (/gioi-thieu/ban-lanh-dao/), trang Tuyển dụng ở /tuyen-dung/ cùng tiền tố với chi tiết vị trí (/tuyen-dung/<slug>/).
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
foreach ( $pages as $key => [ $title, $path, $template ] ) {
	$parts    = explode( '/', $path );
	$slug     = end( $parts );
	$parent   = count( $parts ) > 1 ? (int) ( get_page_by_path( $parts[0] )->ID ?? 0 ) : 0;
	$existing = get_page_by_path( $path );
	$id       = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => $parent, 'post_content' => 'privacy' === $key ? '<p>Nội dung chính sách bảo vệ dữ liệu cá nhân (bản thử).</p>' : '' ) );
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	$ids[ $key ] = $id;
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['home'] );
flush_rewrite_rules();
out( 'trang: ' . json_encode( $ids ) );

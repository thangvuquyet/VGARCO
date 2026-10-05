<?php
require __DIR__ . '/lib.php';
same( 'WordPress 7.x', true, version_compare( get_bloginfo( 'version' ), '7.0', '>=' ) );
same( 'PHP 8.3', true, version_compare( PHP_VERSION, '8.3', '>=' ) );
check( 'vgarco-core đang hoạt động', is_plugin_active( 'vgarco-core/vgarco-core.php' ) );
same( 'theme vgarco', 'vgarco', get_stylesheet() );
foreach ( array( 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri', 'ho_so_ut' ) as $t ) {
	check( "kiểu nội dung {$t} đã đăng ký", post_type_exists( $t ) );
}
foreach ( array( 'loai_cong_trinh', 'chuyen_muc', 'noi_lam_viec', 'phong_ban' ) as $t ) {
	check( "phân loại {$t} đã đăng ký", taxonomy_exists( $t ) );
}
// Cài mới bằng WP-CLI: chưa ai mở trang quản trị (admin_init) mà vai trò Nhân sự đã phải có để `wp user create --role=vg_tuyen_dung` chạy được.
$hr = get_role( 'vg_tuyen_dung' );
check( 'vai trò Nhân sự tuyển dụng có ngay sau khi kích hoạt (không chờ admin_init), đủ quyền hồ sơ', $hr && $hr->has_cap( 'vg_view_applications' ) && $hr->has_cap( 'vg_download_cv' ) && $hr->has_cap( 'vg_export_applications' ) && $hr->has_cap( 'vg_delete_applications' ) );
check( 'Quản trị viên có đủ quyền hồ sơ ngay sau khi kích hoạt', get_role( 'administrator' )->has_cap( 'vg_view_applications' ) );
same( '7 loại công trình được tạo sẵn', 7, (int) wp_count_terms( array( 'taxonomy' => 'loai_cong_trinh', 'hide_empty' => false ) ) );
same( '3 chuyên mục tin được tạo sẵn', 3, (int) wp_count_terms( array( 'taxonomy' => 'chuyen_muc', 'hide_empty' => false ) ) );
$o = get_post_type_object( 'ho_so_ut' );
same( 'ho_so_ut riêng tư: không public, không REST, không xuất', array( false, false, false ), array( $o->public, $o->show_in_rest, $o->can_export ) );
check( 'có hàm vgarco_option và đọc được mặc định (shim ACF)', 'Công ty TNHH Tư vấn Xây dựng VGARCO' === vgarco_option( 'phap_nhan.ten' ) );
check( 'lớp lệnh WP-CLI được dựng', class_exists( 'VGARCO_CLI_Command' ) );
check( 'số nhóm trường ACF đăng ký qua acf_add_local_field_group = 14', 14 === count( $GLOBALS['lab_acf_groups'] ) );
check( 'có trang cấu hình VGARCO', isset( $GLOBALS['lab_acf_options']['vgarco-cau-hinh'] ) );
finish();

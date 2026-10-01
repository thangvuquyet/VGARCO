<?php
/**
 * ACF BẢN MIỄN PHÍ thật (không phải shim): website không được sập khi thiếu ACF PRO (hết hạn giấy phép, tắt nhầm plugin).
 * Kỳ vọng: không lỗi nghiêm trọng, trang công khai vẫn hiện (dùng giá trị mặc định), quản trị viên được báo thiếu ACF PRO.
 * Tùy chọn: chỉ chạy khi đã giải nén plugin advanced-custom-fields vào .lab/wordpress/wp-content/plugins/ (README.md).
 * Đây KHÔNG thay cho kiểm thử với ACF PRO thật (trường lặp, trang cấu hình, ACFML) — bản miễn phí không có những phần đó.
 */
require __DIR__ . '/lib.php';

if ( ! is_file( WP_PLUGIN_DIR . '/advanced-custom-fields/acf.php' ) ) {
	echo "BỎ QUA: chưa có plugin advanced-custom-fields trong .lab (tùy chọn)\n";
	exit( 0 );
}

$before = get_option( 'active_plugins' );
$free   = array( 'advanced-custom-fields/acf.php', 'vgarco-core/vgarco-core.php', 'lab-mail-capture/lab-mail-capture.php' );
update_option( 'active_plugins', $free );
@unlink( LAB . '/debug.log' );

try {
	$admin = lab_cookie_for( (int) get_user_by( 'login', 'labadmin' )->ID );
	$cj    = array( 'headers' => array( 'Cookie: ' . $admin ) );

	foreach ( array( '/', '/du-an/', '/tin-tuc/', '/tuyen-dung-trang/', '/lien-he/', '/gioi-thieu/', '/linh-vuc-hoat-dong/', '/tuyen-dung/vt-kts/' ) as $path ) {
		$r = http( 'GET', $path );
		check( "ACF miễn phí: {$path} trả 200 và không có lỗi PHP trong trang", 200 === $r['status'] && ! preg_match( '/Fatal error|Parse error|Uncaught|Warning:|Notice:|Deprecated:/', $r['body'] ) );
	}
	$r = http( 'GET', '/lien-he/' );
	check( 'ACF miễn phí: trang Liên hệ dùng giá trị mặc định (điện thoại 0988 961 666)', str_contains( $r['body'], '0988 961 666' ) );

	$dash = http( 'GET', '/wp-admin/index.php', $cj );
	check( 'ACF miễn phí: quản trị viên thấy thông báo của VGARCO Core về việc thiếu ACF PRO', 200 === $dash['status'] && str_contains( $dash['body'], 'VGARCO Core:' ) && str_contains( $dash['body'], 'cần cài và kích hoạt' ) );
	check( 'ACF miễn phí: ACF thật đang chạy (có asset của plugin advanced-custom-fields)', str_contains( $dash['body'], '/plugins/advanced-custom-fields/' ) );

	$p   = get_posts( array( 'post_type' => 'du_an', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0];
	$ed  = http( 'GET', '/wp-admin/post.php?post=' . $p . '&action=edit', $cj );
	check( 'ACF miễn phí: màn hình sửa dự án mở được (200, không lỗi PHP)', 200 === $ed['status'] && ! preg_match( '/Fatal error|Uncaught/', $ed['body'] ) );
	check( 'ACF thật đọc được nhóm trường của VGARCO: ô "Mã" (field_vg_du_an_ma) có trên màn hình sửa dự án', str_contains( $ed['body'], 'field_vg_du_an_ma' ) );
	$list = http( 'GET', '/wp-admin/edit.php?post_type=du_an', $cj );
	check( 'ACF miễn phí: danh sách dự án trong quản trị mở được', 200 === $list['status'] && ! preg_match( '/Fatal error|Uncaught/', $list['body'] ) );
	$new = http( 'GET', '/wp-admin/post-new.php?post_type=vi_tri', $cj );
	check( 'ACF miễn phí: màn hình thêm vị trí tuyển dụng mở được', 200 === $new['status'] && ! preg_match( '/Fatal error|Uncaught/', $new['body'] ) );
	$cfg = http( 'GET', '/wp-admin/admin.php?page=vgarco-cau-hinh', $cj );
	check( 'ACF miễn phí: trang Cấu hình (chỉ có ở PRO) không gây lỗi nghiêm trọng, chỉ báo không có trang', ! preg_match( '/Fatal error|Uncaught/', $cfg['body'] ) );

	$log = is_file( LAB . '/debug.log' ) ? (string) file_get_contents( LAB . '/debug.log' ) : '';
	check( 'ACF miễn phí: nhật ký lỗi PHP không có lỗi nghiêm trọng' . ( $log ? ' — ' . substr( $log, 0, 300 ) : '' ), ! preg_match( '/Fatal|Uncaught|Parse error/', $log ) );
	if ( $log ) {
		echo "  (ghi chú) debug.log có nội dung, dòng đầu: " . trim( strtok( $log, "\n" ) ) . "\n";
	}
} finally {
	update_option( 'active_plugins', $before ); // trả về như cũ để các kịch bản khác dùng shim
	flush_rewrite_rules();
}
finish();

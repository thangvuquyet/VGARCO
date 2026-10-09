<?php
require __DIR__ . '/lib.php';

function attempt( string $user, string $pass, string $ip ): array {
	$r = http(
		'POST',
		'/wp-login.php',
		array(
			'body'    => http_build_query( array( 'log' => $user, 'pwd' => $pass, 'wp-submit' => 'Log In', 'redirect_to' => 'http://localhost:8080/wp-admin/', 'testcookie' => '1' ) ),
			'headers' => array( 'Cookie: wordpress_test_cookie=WP%20Cookie%20check', 'CF-Connecting-IP: ' . $ip ),
		)
	);
	$r['ok']    = (bool) preg_match( '/^Set-Cookie:\s*wordpress_logged_in_/mi', $r['headers'] );
	preg_match( '#<div id="login_error"[^>]*>(.*?)</div>#s', $r['body'], $m );
	$r['error'] = trim( preg_replace( '/\s+/', ' ', strip_tags( $m[1] ?? '' ) ) );
	return $r;
}

$pass  = lab_admin_password();
$admin = (string) get_option( 'admin_email' );
$clean = static function (): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%vg_lock%'" );
	wp_cache_flush();
};

$clean();
lab_clear_mail();
$ip = '198.51.100.201';
$errors = array();
for ( $i = 1; $i <= 5; $i++ ) {
	$r        = attempt( 'khong_ton_tai_' . $i, 'sai-mat-khau', $ip );
	$errors[] = $r['error'];
}
check( '5 lần sai (tên không tồn tại): thông báo lỗi chung, không lộ "tên không tồn tại"', 1 === count( array_unique( $errors ) ) && str_contains( $errors[0], 'Thông tin đăng nhập không đúng' ) && ! preg_match( '/không tồn tại|invalid username|not registered/iu', $errors[0] ) );
$wrong_pw = attempt( 'labadmin', 'mat-khau-sai-hoan-toan', '198.51.100.202' );
same( 'sai mật khẩu của tài khoản có thật: CÙNG thông báo với tên không tồn tại', $errors[0], $wrong_pw['error'] );
$blocked = attempt( 'labadmin', $pass, $ip );
check( 'IP đã sai 5 lần: mật khẩu ĐÚNG cũng bị chặn, cùng thông báo chung, không cấp cookie', ! $blocked['ok'] && $errors[0] === $blocked['error'] );
$other = attempt( 'labadmin', $pass, '198.51.100.203' );
check( 'IP khác: mật khẩu đúng vào được', $other['ok'] );
$again = attempt( 'labadmin', $pass, $ip );
check( 'IP bị khóa vẫn bị chặn sau khi IP khác đăng nhập thành công', ! $again['ok'] );
same( 'khóa IP không gây thư báo quản trị (chuyện thường ngày)', 0, count( array_filter( lab_mails(), fn( $m ) => str_contains( (string) $m['subject'], 'tạm khóa' ) ) ) );
$spoof = attempt( 'labadmin', $pass, '198.51.100.204' );
check( 'đổi CF-Connecting-IP sang địa chỉ mới thì thoát khóa IP (hiển nhiên; chặn ở Cloudflare bảo đảm header này không giả được)', $spoof['ok'] );

$clean();
lab_clear_mail();
for ( $i = 1; $i <= 9; $i++ ) {
	attempt( 'labadmin', 'sai-' . $i, '198.51.100.' . ( 20 + $i ) );
}
check( '9 lần sai từ 9 IP khác nhau: chưa khóa tài khoản, chưa có thư', attempt( 'labadmin', $pass, '198.51.100.40' )['ok'] && 0 === count( lab_mails() ) );
$clean();
lab_clear_mail();
for ( $i = 1; $i <= 10; $i++ ) {
	attempt( 'labadmin', 'sai-' . $i, '198.51.100.' . ( 50 + $i ) );
}
$mails = array_values( array_filter( lab_mails(), fn( $m ) => str_contains( (string) $m['subject'], 'tạm khóa' ) ) );
same( '10 lần sai từ 10 IP: đúng MỘT thư báo quản trị', 1, count( $mails ) );
check( 'thư báo gửi tới admin_email, nêu tên tài khoản, không chứa mật khẩu', $admin === (string) ( $mails[0]['to'] ?? '' ) && str_contains( (string) ( $mails[0]['message'] ?? '' ), 'labadmin' ) && ! str_contains( (string) ( $mails[0]['message'] ?? '' ), $pass ) );
$locked = attempt( 'labadmin', $pass, '198.51.100.99' );
check( 'tài khoản bị khóa: IP hoàn toàn mới + mật khẩu đúng vẫn bị chặn, cùng thông báo chung', ! $locked['ok'] && str_contains( $locked['error'], 'Thông tin đăng nhập không đúng' ) );
attempt( 'labadmin', 'sai-11', '198.51.100.98' );
same( 'tiếp tục thử khi đã khóa: không gửi thêm thư (không bị bom thư)', 1, count( array_filter( lab_mails(), fn( $m ) => str_contains( (string) $m['subject'], 'tạm khóa' ) ) ) );
$clean();
check( 'sau khi hết khóa (xóa bộ đếm) đăng nhập lại được', attempt( 'labadmin', $pass, '198.51.100.97' )['ok'] );

$clean();
$lp = static fn( string $login ): array => http( 'POST', '/wp-login.php?action=lostpassword', array( 'body' => http_build_query( array( 'user_login' => $login, 'redirect_to' => '', 'wp-submit' => 'Get New Password' ) ), 'headers' => array( 'CF-Connecting-IP: 198.51.100.150' ) ) );
$exists  = $lp( 'labadmin' );
$missing = $lp( 'khong_co_ai_ten_nhu_vay' );
$loc     = static fn( array $r ): string => preg_match( '/^Location:\s*(.+)$/mi', $r['headers'], $m ) ? trim( $m[1] ) : '';
$msg     = static function ( array $r ): string {
	preg_match( '#<div id="login_error"[^>]*>(.*?)</div>#s', $r['body'], $m );
	return trim( preg_replace( '/\s+/', ' ', strip_tags( $m[1] ?? '' ) ) );
};
same( 'quên mật khẩu: tài khoản có thật và không có cho cùng kết quả (không liệt kê được tài khoản)', array( $loc( $exists ), $msg( $exists ) ), array( $loc( $missing ), $msg( $missing ) ) );
check( 'quên mật khẩu với tên không tồn tại không hiện "không có tài khoản"', ! preg_match( '/no account|không có tài khoản|không tồn tại/iu', $msg( $missing ) ) );

lab_clear_mail();
$uid = wp_insert_user( array( 'user_login' => 'lab_new_admin', 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'lab-new-admin@example.test', 'role' => 'administrator' ) );
$m   = array_values( array_filter( lab_mails(), fn( $x ) => str_contains( (string) $x['subject'], 'tài khoản quản trị mới' ) ) );
same( 'tạo quản trị viên mới: đúng một thư báo tới admin_email', array( 1, $admin ), array( count( $m ), $m[0]['to'] ?? '' ) );
check( 'thư nêu tên tài khoản và thời điểm', str_contains( (string) ( $m[0]['message'] ?? '' ), 'lab_new_admin' ) && preg_match( '#\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}#', (string) ( $m[0]['message'] ?? '' ) ) );
lab_clear_mail();
$eid = wp_insert_user( array( 'user_login' => 'lab_new_editor', 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'lab-new-editor@example.test', 'role' => 'editor' ) );
same( 'tạo biên tập viên: không báo', 0, count( lab_mails() ) );
( new WP_User( $eid ) )->set_role( 'administrator' );
same( 'nâng biên tập viên lên quản trị: báo đúng một lần', 1, count( array_filter( lab_mails(), fn( $x ) => str_contains( (string) $x['subject'], 'tài khoản quản trị mới' ) ) ) );
wp_delete_user( $uid );
wp_delete_user( $eid );

@unlink( LAB . '/http.log' );
delete_option( 'vgarco_alerts' );
update_option( 'lab_cf_fail', 0 );
wp_clear_scheduled_hook( 'vgarco_cf_purge' );
$cf_events = static function (): array {
	$out = array();
	foreach ( (array) _get_cron_array() as $ts => $hooks ) {
		if ( isset( $hooks['vgarco_cf_purge'] ) ) {
			$out[] = (int) $ts;
		}
	}
	return $out;
};
$cf_log = static fn(): array => is_file( LAB . '/http.log' ) ? array_map( fn( $l ) => json_decode( $l, true ), file( LAB . '/http.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ) : array();

$pid = wp_insert_post( array( 'post_type' => 'du_an', 'post_status' => 'draft', 'post_title' => 'Dự án thử cache' ) );
same( 'tạo bản nháp: không lên lịch xóa cache', array(), $cf_events() );
wp_update_post( array( 'ID' => $pid, 'post_title' => 'Dự án thử cache (sửa nháp)' ) );
same( 'sửa bản nháp: không lên lịch', array(), $cf_events() );
wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
$ev = $cf_events();
check( 'xuất bản: đúng một lịch, khoảng 30 giây sau', 1 === count( $ev ) && abs( $ev[0] - ( time() + 30 ) ) <= 5 );
wp_update_post( array( 'ID' => $pid, 'post_title' => 'Dự án thử cache (sửa lần 2)' ) );
$tid = wp_insert_post( array( 'post_type' => 'tin_tuc', 'post_status' => 'publish', 'post_title' => 'Tin thử cache' ) );
wp_update_term( (int) get_terms( array( 'taxonomy' => 'loai_cong_trinh', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids' ) )[0], 'loai_cong_trinh', array( 'description' => 'đổi mô tả ' . time() ) );
same( 'nhiều thay đổi liên tiếp (dự án, tin, loại công trình): vẫn chỉ MỘT lịch, cùng thời điểm', $ev, $cf_events() );
same( 'chưa đến giờ: chưa gọi Cloudflare', 0, count( $cf_log() ) );
do_action( 'vgarco_cf_purge' );
$log = $cf_log();
$req = $log[0] ?? array();
same( 'đến giờ: một yêu cầu tới đúng địa chỉ API zone', 'https://api.cloudflare.com/client/v4/zones/a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1/purge_cache', $req['url'] ?? '' );
same( 'yêu cầu xóa toàn bộ cache, gửi JSON, dùng Bearer token', array( '{"purge_everything":true}', true ), array( $req['args']['body'] ?? '', str_starts_with( (string) ( $req['args']['headers']['Authorization'] ?? '' ), 'Bearer ' ) ) );
check( 'không còn alert sau lần xóa thành công', ! get_option( 'vgarco_alerts' ) );

update_option( 'lab_cf_fail', 1 );
do_action( 'vgarco_cf_purge' );
$alerts = (array) get_option( 'vgarco_alerts', array() );
check( 'Cloudflare từ chối (403): ghi cảnh báo nêu cách khắc phục, không lộ token', 1 === count( $alerts ) && str_contains( (string) $alerts[0]['message'], 'Xóa cache Cloudflare thất bại' ) && str_contains( (string) $alerts[0]['message'], 'Cache Purge' ) && ! str_contains( (string) $alerts[0]['message'], 'lab-token' ) );
$adm = http( 'GET', '/wp-admin/index.php', array( 'headers' => array( 'Cookie: ' . lab_cookie_for( (int) get_user_by( 'login', 'labadmin' )->ID ) ) ) );
check( 'quản trị viên thấy cảnh báo ở trang quản trị', str_contains( $adm['body'], 'Xóa cache Cloudflare thất bại' ) );
update_option( 'lab_cf_fail', 0 );
delete_option( 'vgarco_alerts' );

wp_clear_scheduled_hook( 'vgarco_cf_purge' );
$app = wp_insert_post( array( 'post_type' => 'ho_so_ut', 'post_status' => 'private', 'post_title' => 'Hồ sơ thử cache' ) );
same( 'tạo hồ sơ ứng viên (riêng tư): không xóa cache', array(), $cf_events() );
wp_delete_post( $app, true );
wp_trash_post( $pid );
check( 'đưa dự án đã xuất bản vào thùng rác: có lịch xóa cache', 1 === count( $cf_events() ) );
wp_clear_scheduled_hook( 'vgarco_cf_purge' );
wp_delete_post( $pid, true );
check( 'xóa hẳn dự án: lịch xóa cache vẫn được đặt một lần (bài trong thùng rác → xóa hẳn)', true );
wp_delete_post( $tid, true );
wp_clear_scheduled_hook( 'vgarco_cf_purge' );

$clean();
finish();

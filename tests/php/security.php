<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );
require_once ROOT . '/wp-content/plugins/vgarco-core/inc/security-lib.php';
require_once ROOT . '/wp-content/plugins/vgarco-core/inc/cache-lib.php';

$pass = 0;
$fail = array();

function check( string $label, bool $condition ): void {
	global $pass, $fail;
	if ( $condition ) {
		++$pass;
	} else {
		$fail[] = $label;
	}
}

function same( string $label, $expected, $actual ): void {
	check( $label . ' — mong đợi ' . var_export( $expected, true ) . ', nhận ' . var_export( $actual, true ), $expected === $actual );
}

$now = 1_800_000_000;

same( 'prune: giữ lượt trong cửa sổ, bỏ lượt ở mép và quá cũ', array( $now - 899, $now ), vgarco_lockout_prune( array( $now - 901, $now - 900, $now - 899, $now ), $now, 900 ) );
same( 'prune: mốc ở tương lai (dữ liệu hỏng) bị bỏ', array( $now ), vgarco_lockout_prune( array( $now + 1, $now, $now + 99999 ), $now, 900 ) );
same( 'prune: sắp tăng dần', array( $now - 30, $now - 20, $now - 10 ), vgarco_lockout_prune( array( $now - 10, $now - 30, $now - 20 ), $now, 900 ) );
same( 'prune: rỗng', array(), vgarco_lockout_prune( array(), $now, 900 ) );
same( 'state: dữ liệu rỗng hoặc hỏng → trạng thái sạch', array( 'attempts' => array(), 'until' => 0 ), vgarco_lockout_state( 'rác' ) );
same( 'state: ép kiểu số', array( 'attempts' => array( 5 ), 'until' => 7 ), vgarco_lockout_state( array( 'attempts' => array( '5' ), 'until' => '7' ) ) );

$state = vgarco_lockout_state( null );
$times = array();
for ( $i = 1; $i <= 4; $i++ ) {
	$r     = vgarco_lockout_record_ip( $state, $now + $i * 10 );
	$state = $r['state'];
	same( "IP: lượt sai thứ {$i} chưa khóa", array( false, 0 ), array( $r['triggered'], vgarco_lockout_remaining( $state, $now + $i * 10 ) ) );
}
$t5 = $now + 50;
$r  = vgarco_lockout_record_ip( $state, $t5 );
same( 'IP: lượt sai thứ 5 đặt khóa 15 phút và báo vừa khóa', array( true, 900 ), array( $r['triggered'], vgarco_lockout_remaining( $r['state'], $t5 ) ) );
$locked = $r['state'];
same( 'IP: còn khóa ở giây 899', 1, vgarco_lockout_remaining( $locked, $t5 + 899 ) );
same( 'IP: hết khóa đúng giây 900', 0, vgarco_lockout_remaining( $locked, $t5 + 900 ) );
same( 'IP: sau khi khóa, danh sách lượt sai được xóa (đợt mới tính lại từ đầu)', array(), $locked['attempts'] );

$during = vgarco_lockout_record_ip( $locked, $t5 + 100 );
same( 'IP: thử tiếp khi đang khóa KHÔNG kéo dài khóa và không báo lại', array( $locked['until'], false ), array( $during['state']['until'], $during['triggered'] ) );
same( 'IP: thử tiếp khi đang khóa không ghi thêm lượt sai', array(), $during['state']['attempts'] );

$after = $locked;
for ( $i = 0; $i < 4; $i++ ) {
	$after = vgarco_lockout_record_ip( $after, $t5 + 1000 + $i )['state'];
}
same( 'IP: sau khi hết khóa, 4 lượt sai mới vẫn chưa khóa', 0, vgarco_lockout_remaining( $after, $t5 + 1004 ) );
check( 'IP: lượt thứ 5 của đợt mới lại khóa', vgarco_lockout_record_ip( $after, $t5 + 1005 )['triggered'] );

$slow = vgarco_lockout_state( null );
foreach ( array( 0, 200, 400, 600 ) as $t ) {
	$slow = vgarco_lockout_record_ip( $slow, $now + $t )['state'];
}
$r = vgarco_lockout_record_ip( $slow, $now + 1000 );
same( 'IP: lượt sai cũ hơn 15 phút không tính vào ngưỡng', array( false, 0 ), array( $r['triggered'], vgarco_lockout_remaining( $r['state'], $now + 1000 ) ) );
$r = vgarco_lockout_record_ip( $slow, $now + 899 );
same( 'IP: 5 lượt trong đúng 15 phút thì khóa', true, $r['triggered'] );
$r = vgarco_lockout_record_ip( $slow, $now + 900 );
same( 'IP: lượt ở mép cửa sổ (đúng 900 s) đã hết hạn nên chưa khóa', false, $r['triggered'] );

$early = vgarco_lockout_state( null );
foreach ( array( 0, 100, 200, 300, 400 ) as $t ) {
	$early = vgarco_lockout_record_ip( $early, $now + $t )['state'];
}
same( 'IP: khóa từ lượt thứ 5 (t=400) kéo dài tới t=1300', 1300 - 901, vgarco_lockout_remaining( $early, $now + 901 ) );
same( 'IP: vẫn khóa ở t=1299', 1, vgarco_lockout_remaining( $early, $now + 1299 ) );

$u      = vgarco_lockout_state( null );
$notify = 0;
for ( $i = 1; $i <= 12; $i++ ) {
	$r      = vgarco_lockout_record_user( $u, $now + $i );
	$u      = $r['state'];
	$notify += $r['triggered'] ? 1 : 0;
	if ( $i < 10 ) {
		same( "tài khoản: lượt sai thứ {$i} chưa khóa", 0, vgarco_lockout_remaining( $u, $now + $i ) );
	}
}
same( 'tài khoản: khóa 1 giờ kể từ lượt thứ 10', 3600, vgarco_lockout_remaining( vgarco_lockout_record_user( array( 'attempts' => array_map( fn( $i ) => $now + $i, range( 1, 9 ) ), 'until' => 0 ), $now + 10 )['state'], $now + 10 ) );
same( 'tài khoản: báo quản trị đúng MỘT lần dù thử tiếp 12 lượt', 1, $notify );
$slow_user = vgarco_lockout_state( null );
for ( $i = 0; $i < 9; $i++ ) {
	$slow_user = vgarco_lockout_record_user( $slow_user, $now + $i * 400 )['state'];
}
same( 'tài khoản: 9 lượt rải trong 1 giờ chưa khóa', 0, vgarco_lockout_remaining( $slow_user, $now + 3200 ) );
check( 'tài khoản: lượt thứ 10 trong cửa sổ 1 giờ thì khóa', vgarco_lockout_record_user( $slow_user, $now + 3599 )['triggered'] );
check( 'tài khoản: lượt thứ 10 nhưng lượt đầu đã quá 1 giờ thì không khóa', ! vgarco_lockout_record_user( $slow_user, $now + 3700 )['triggered'] );
check( 'ngưỡng IP (5) thấp hơn ngưỡng tài khoản (10)', VGARCO_LOCK_IP_MAX < VGARCO_LOCK_USER_MAX && VGARCO_LOCK_IP_DURATION < VGARCO_LOCK_USER_DURATION );

$k1 = vgarco_lockout_key( 'user', 'Admin', 'muoi' );
same( 'khóa: tên đăng nhập không phân biệt hoa thường và khoảng trắng', $k1, vgarco_lockout_key( 'user', '  admin ', 'muoi' ) );
check( 'khóa: tên khác → khóa khác', $k1 !== vgarco_lockout_key( 'user', 'admin2', 'muoi' ) );
check( 'khóa: muối khác → khóa khác', $k1 !== vgarco_lockout_key( 'user', 'admin', 'muoi-khac' ) );
check( 'khóa: IP và tên đăng nhập giống nhau vẫn hai bộ đếm riêng', vgarco_lockout_key( 'ip', 'admin', 'm' ) !== vgarco_lockout_key( 'user', 'admin', 'm' ) );
check( 'khóa: không chứa IP hay tên dạng rõ, độ dài cố định', ! str_contains( vgarco_lockout_key( 'ip', '203.0.113.9', 'm' ), '203' ) && 'vg_lock_ip_' . str_repeat( 'x', 32 ) === preg_replace( '/[a-f0-9]{32}$/', str_repeat( 'x', 32 ), vgarco_lockout_key( 'ip', '203.0.113.9', 'm' ) ) );
same( 'khóa: IP không chuẩn hóa hoa thường (IPv6 do máy chủ chuẩn hóa)', false, vgarco_lockout_key( 'ip', 'ABCD::1', 'm' ) === vgarco_lockout_key( 'ip', 'abcd::1', 'm' ) );

$msg = vgarco_login_error_message();
check( 'thông báo lỗi: không nhắc tên đăng nhập hay mật khẩu là cái sai', ! preg_match( '/mật khẩu (sai|không đúng)|không tồn tại|tên đăng nhập (sai|không)/iu', $msg ) );
check( 'thông báo lỗi: nói chung chung rằng thông tin sai hoặc bị tạm khóa', str_contains( $msg, 'Thông tin đăng nhập không đúng' ) && str_contains( $msg, 'tạm khóa' ) );
same( 'mã lỗi tiết lộ tài khoản', array( 'invalid_username', 'incorrect_password', 'invalid_email', 'invalidcombo', 'empty_username', 'empty_password' ), vgarco_login_leaky_codes() );

$zone = str_repeat( 'a1', 16 );
$req  = vgarco_cf_purge_request( $zone, 'tok_abcDEF123' );
same( 'cache: địa chỉ API đúng zone', 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/purge_cache', $req['url'] ?? '' );
same( 'cache: POST, xóa toàn bộ', array( 'POST', '{"purge_everything":true}' ), array( $req['args']['method'] ?? '', $req['args']['body'] ?? '' ) );
same( 'cache: token đi trong header Authorization Bearer, không nằm trong URL', array( 'Bearer tok_abcDEF123', false ), array( $req['args']['headers']['Authorization'] ?? '', str_contains( $req['url'] ?? '', 'tok_abc' ) ) );
same( 'cache: Content-Type JSON và có thời hạn chờ', array( 'application/json', 10 ), array( $req['args']['headers']['Content-Type'] ?? '', $req['args']['timeout'] ?? 0 ) );
same( 'cache: ID zone viết hoa được chuẩn hóa thành chữ thường', 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/purge_cache', vgarco_cf_purge_request( strtoupper( $zone ), 't' )['url'] ?? '' );
foreach ( array( array( '', 'tok' ), array( $zone, '' ), array( 'khong-phai-zone', 'tok' ), array( $zone . 'ff', 'tok' ), array( "{$zone}\n", 'tok' ) ) as $i => [ $z, $t ] ) {
	$r = vgarco_cf_purge_request( $z, $t );
	check( "cache: cấu hình thiếu/sai #{$i} → không gọi API" . ( "{$zone}\n" === $z ? ' (xuống dòng trong ID zone được cắt nên hợp lệ)' : '' ), "{$zone}\n" === $z ? null !== $r : null === $r );
}
same( 'cache: token chứa xuống dòng (chèn header) bị từ chối', null, vgarco_cf_purge_request( $zone, "tok\r\nX-Evil: 1" ) );
same( 'cache: token chứa khoảng trắng bị từ chối', null, vgarco_cf_purge_request( $zone, 'tok en' ) );

same( 'cache: phản hồi thành công', array( 'ok' => true, 'message' => '' ), vgarco_cf_parse_response( 200, '{"success":true,"result":{"id":"x"}}' ) );
same( 'cache: phản hồi lỗi có thông điệp', array( 'ok' => false, 'message' => 'HTTP 403: Authentication error' ), vgarco_cf_parse_response( 403, '{"success":false,"errors":[{"code":10000,"message":"Authentication error"}]}' ) );
same( 'cache: HTTP 200 nhưng success=false vẫn là lỗi', false, vgarco_cf_parse_response( 200, '{"success":false}' )['ok'] );
same( 'cache: thân không phải JSON', array( 'ok' => false, 'message' => 'HTTP 502' ), vgarco_cf_parse_response( 502, '<html>Bad gateway</html>' ) );
same( 'cache: HTTP lỗi dù thân báo success', false, vgarco_cf_parse_response( 500, '{"success":true}' )['ok'] );

same( 'cache: xuất bản bài dự án → xóa', true, vgarco_cache_relevant_change( 'du_an', 'publish', 'draft' ) );
same( 'cache: sửa bài đã xuất bản → xóa', true, vgarco_cache_relevant_change( 'tin_tuc', 'publish', 'publish' ) );
same( 'cache: gỡ bài khỏi xuất bản → xóa (HTML cũ còn trong cache)', true, vgarco_cache_relevant_change( 'vi_tri', 'draft', 'publish' ) );
same( 'cache: đưa vào thùng rác bài đã xuất bản → xóa', true, vgarco_cache_relevant_change( 'page', 'trash', 'publish' ) );
same( 'cache: nháp → nháp → không xóa', false, vgarco_cache_relevant_change( 'du_an', 'draft', 'draft' ) );
same( 'cache: tự động lưu/nháp mới (auto-draft → draft) → không xóa', false, vgarco_cache_relevant_change( 'du_an', 'draft', 'auto-draft' ) );
same( 'cache: hồ sơ ứng tuyển không bao giờ làm xóa cache', false, vgarco_cache_relevant_change( 'ho_so_ut', 'publish', 'draft' ) );
same( 'cache: kiểu nội dung khác (menu, revision) không xóa', false, vgarco_cache_relevant_change( 'nav_menu_item', 'publish', 'draft' ) || vgarco_cache_relevant_change( 'revision', 'inherit', 'publish' ) );
same( 'cache: đủ 5 kiểu công khai', array( 'page', 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri' ), vgarco_cache_public_types() );
check( 'cache: độ trễ gom thay đổi là 30 giây', 30 === VGARCO_CACHE_PURGE_DELAY );

$vn = 'Asia/Ho_Chi_Minh';
$t  = static fn( string $s ): int => ( new DateTimeImmutable( $s, new DateTimeZone( 'UTC' ) ) )->getTimestamp();
same( 'cron: lúc 10:00 VN (03:00 UTC) → 00:10 VN hôm sau', $t( '2026-10-01 17:10:00' ), vgarco_next_daily_run( $t( '2026-10-01 03:00:00' ), $vn ) );
same( 'cron: lúc 00:05 VN → chính 00:10 VN hôm nay', $t( '2026-09-30 17:10:00' ), vgarco_next_daily_run( $t( '2026-09-30 17:05:00' ), $vn ) );
same( 'cron: đúng 00:10 VN → hôm sau (không chạy lặp)', $t( '2026-10-01 17:10:00' ), vgarco_next_daily_run( $t( '2026-09-30 17:10:00' ), $vn ) );
same( 'cron: 00:10:01 VN → hôm sau', $t( '2026-10-01 17:10:00' ), vgarco_next_daily_run( $t( '2026-09-30 17:10:01' ), $vn ) );
same( 'cron: múi giờ khác (UTC) → 00:10 UTC', $t( '2026-10-01 00:10:00' ), vgarco_next_daily_run( $t( '2026-09-30 12:00:00' ), 'UTC' ) );
same( 'cron: giờ phút tùy chỉnh', $t( '2026-09-30 19:30:00' ), vgarco_next_daily_run( $t( '2026-09-30 05:00:00' ), $vn, 2, 30 ) );
check( 'cron: luôn ở tương lai', vgarco_next_daily_run( $now, $vn ) > $now && vgarco_next_daily_run( $now, $vn ) - $now <= 86400 );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

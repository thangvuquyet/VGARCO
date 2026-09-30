<?php
/**
 * Khóa đăng nhập sai (mục 8.6 của phương án) — phần THUẦN, kiểm thử bằng dữ liệu giả (tests/php/security.php).
 *
 * Quy tắc:
 *  - 5 lần sai trong 15 phút theo IP → khóa IP 15 phút;
 *  - 10 lần sai trong 1 giờ theo tài khoản → khóa tài khoản 1 giờ và báo email quản trị (đúng một lần mỗi đợt khóa);
 *  - thông báo lỗi luôn chung chung, giống hệt nhau dù tài khoản có tồn tại hay không (chống dò tài khoản).
 *
 * Trạng thái một bộ đếm: ['attempts' => mốc thời gian các lần sai còn trong cửa sổ, 'until' => mốc hết khóa (0 = không khóa)].
 * Lượt bị từ chối vì đang khóa KHÔNG được ghi thêm: kẻ tấn công không kéo dài được khóa, người dùng thật không bị khóa lâu hơn mức đã nêu.
 * Khóa được lưu riêng (until) nên vẫn đúng hạn dù các lượt sai cũ đã trôi khỏi cửa sổ.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

const VGARCO_LOCK_IP_MAX        = 5;
const VGARCO_LOCK_IP_WINDOW     = 15 * 60;
const VGARCO_LOCK_IP_DURATION   = 15 * 60;
const VGARCO_LOCK_USER_MAX      = 10;
const VGARCO_LOCK_USER_WINDOW   = 60 * 60;
const VGARCO_LOCK_USER_DURATION = 60 * 60;

/**
 * Chuẩn hóa dữ liệu đọc từ kho lưu (có thể rỗng hoặc hỏng) thành trạng thái hợp lệ.
 *
 * @param mixed $raw
 * @return array{attempts: int[], until: int}
 */
function vgarco_lockout_state( $raw ): array {
	$raw = is_array( $raw ) ? $raw : array();
	return array(
		'attempts' => array_values( array_map( 'intval', (array) ( $raw['attempts'] ?? array() ) ) ),
		'until'    => (int) ( $raw['until'] ?? 0 ),
	);
}

/**
 * Giữ các lượt sai còn trong cửa sổ thời gian (lượt đúng ở mép cửa sổ coi là đã hết hạn; mốc ở tương lai là dữ liệu hỏng, bỏ).
 *
 * @param int[] $attempts
 * @return int[] Đã sắp tăng dần.
 */
function vgarco_lockout_prune( array $attempts, int $now, int $window ): array {
	$kept = array_values( array_filter( array_map( 'intval', $attempts ), static fn( int $t ): bool => $t > $now - $window && $t <= $now ) );
	sort( $kept );
	return $kept;
}

/** Số giây còn bị khóa (0 = không khóa). */
function vgarco_lockout_remaining( array $state, int $now ): int {
	return max( 0, vgarco_lockout_state( $state )['until'] - $now );
}

/**
 * Ghi thêm một lượt sai. Đang khóa thì không ghi gì. Lượt làm ĐỦ ngưỡng đặt khóa và xóa danh sách lượt sai (đợt mới tính lại từ đầu).
 *
 * @return array{state: array, triggered: bool} triggered = lượt này vừa chuyển từ chưa khóa sang khóa.
 */
function vgarco_lockout_record( array $state, int $now, int $max, int $window, int $duration ): array {
	$state = vgarco_lockout_state( $state );
	if ( $state['until'] > $now ) {
		return array( 'state' => $state, 'triggered' => false );
	}

	$attempts   = vgarco_lockout_prune( $state['attempts'], $now, $window );
	$attempts[] = $now;
	if ( count( $attempts ) >= $max ) {
		return array( 'state' => array( 'attempts' => array(), 'until' => $now + $duration ), 'triggered' => true );
	}
	return array( 'state' => array( 'attempts' => $attempts, 'until' => 0 ), 'triggered' => false );
}

/** Ghi lượt sai theo IP. */
function vgarco_lockout_record_ip( array $state, int $now ): array {
	return vgarco_lockout_record( $state, $now, VGARCO_LOCK_IP_MAX, VGARCO_LOCK_IP_WINDOW, VGARCO_LOCK_IP_DURATION );
}

/** Ghi lượt sai theo tài khoản. */
function vgarco_lockout_record_user( array $state, int $now ): array {
	return vgarco_lockout_record( $state, $now, VGARCO_LOCK_USER_MAX, VGARCO_LOCK_USER_WINDOW, VGARCO_LOCK_USER_DURATION );
}

/**
 * Khóa lưu trữ cho một bộ đếm. Băm có muối: không lưu IP hay tên đăng nhập dạng rõ, và tên viết hoa/thường khác nhau dùng chung bộ đếm.
 *
 * @param string $kind  ip | user
 * @param string $value IP hoặc tên đăng nhập / email.
 */
function vgarco_lockout_key( string $kind, string $value, string $salt ): string {
	$value = 'user' === $kind ? mb_strtolower( trim( $value ) ) : trim( $value );
	return 'vg_lock_' . $kind . '_' . substr( hash_hmac( 'sha256', $value, $salt ), 0, 32 );
}

/**
 * Thông báo lỗi đăng nhập DUY NHẤT: không phân biệt sai tên, sai mật khẩu hay tài khoản không tồn tại.
 * Khi bị khóa cũng dùng đúng thông báo này để không lộ trạng thái của tài khoản.
 */
function vgarco_login_error_message(): string {
	return 'Thông tin đăng nhập không đúng hoặc đã bị tạm khóa do thử quá nhiều lần. Vui lòng thử lại sau ít phút.';
}

/** Các mã lỗi đăng nhập của WordPress tiết lộ tài khoản có tồn tại hay không, cần gộp về một thông báo. */
function vgarco_login_leaky_codes(): array {
	return array( 'invalid_username', 'incorrect_password', 'invalid_email', 'invalidcombo', 'empty_username', 'empty_password' );
}

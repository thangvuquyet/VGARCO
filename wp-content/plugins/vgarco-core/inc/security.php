<?php
/**
 * Đăng nhập an toàn (mục 8.6): khóa sau nhiều lần sai (theo IP và theo tài khoản), một thông báo lỗi chung cho mọi lỗi đăng nhập,
 * báo email khi có tài khoản quản trị mới. Quy tắc đếm nằm ở security-lib.php (có kiểm thử); tệp này chỉ nối vào WordPress.
 *
 * Bổ sung, không thay thế: WP 2FA (bắt buộc mã 2FA), WPS Hide Login (đổi đường dẫn đăng nhập) và một quy tắc rate limit ở Cloudflare.
 * Những việc đã có ở mu-plugin vgarco-loader: tắt XML-RPC, Application Passwords, /?author=N, REST users.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/** Muối cho khóa bộ đếm: gắn với website, không dùng lại được nơi khác. */
function vgarco_lockout_salt(): string {
	return wp_salt( 'auth' );
}

function vgarco_lockout_load( string $kind, string $value ): array {
	return vgarco_lockout_state( get_transient( vgarco_lockout_key( $kind, $value, vgarco_lockout_salt() ) ) );
}

function vgarco_lockout_save( string $kind, string $value, array $state ): void {
	// Sống lâu hơn thời hạn dài nhất (khóa tài khoản 1 giờ) để trạng thái không biến mất giữa chừng.
	set_transient( vgarco_lockout_key( $kind, $value, vgarco_lockout_salt() ), $state, 2 * HOUR_IN_SECONDS );
}

/**
 * Tên tài khoản dùng làm khóa bộ đếm: đăng nhập bằng tên hay bằng email của cùng một người dùng chung một bộ đếm
 * (nếu không, kẻ tấn công có gấp đôi số lần thử). Tên không tồn tại thì giữ nguyên chuỗi đã gõ.
 */
function vgarco_lockout_account( string $typed ): string {
	$user = get_user_by( 'login', $typed );
	if ( ! $user && is_email( $typed ) ) {
		$user = get_user_by( 'email', $typed );
	}
	return $user ? (string) $user->user_login : $typed;
}

/**
 * Chặn đăng nhập khi đang khóa, kể cả khi mật khẩu đúng; không tiết lộ khóa theo IP hay theo tài khoản — luôn cùng một thông báo.
 *
 * Ưu tiên 98: SAU kiểm tra mật khẩu của lõi (ưu tiên 20). Đặt trước thì vô hiệu: wp_authenticate_username_password() nhận WP_Error
 * của bộ lọc trước đó, bỏ qua, tự kiểm tra mật khẩu rồi trả WP_User hợp lệ — mật khẩu đúng vẫn vào được (phát hiện khi chạy trên WordPress 7.1 thật).
 *
 * @param WP_User|WP_Error|null $user
 * @return WP_User|WP_Error|null
 */
function vgarco_auth_guard( $user, $username = '', $password = '' ) {
	if ( '' === (string) $username ) {
		return $user; // lượt không phải đăng nhập bằng mật khẩu (cookie...): không đếm, không chặn
	}
	$now = time();
	if ( vgarco_lockout_remaining( vgarco_lockout_load( 'ip', vgarco_client_ip() ), $now ) > 0
		|| vgarco_lockout_remaining( vgarco_lockout_load( 'user', vgarco_lockout_account( (string) $username ) ), $now ) > 0 ) {
		return new WP_Error( 'vgarco_locked', vgarco_login_error_message() );
	}
	return $user;
}
add_filter( 'authenticate', 'vgarco_auth_guard', 98, 3 );

/**
 * Mọi lỗi đăng nhập thành một thông báo: lõi WordPress phân biệt "tên không tồn tại" với "sai mật khẩu" nên lộ danh sách tài khoản.
 * Ưu tiên 99: chạy sau cùng, sau bộ chặn khóa (98) và mọi bộ kiểm tra khác.
 *
 * @param WP_User|WP_Error|null $user
 * @return WP_User|WP_Error|null
 */
function vgarco_auth_generic_error( $user ) {
	if ( $user instanceof WP_Error && array_intersect( array_merge( vgarco_login_leaky_codes(), array( 'vgarco_locked' ) ), $user->get_error_codes() ) ) {
		return new WP_Error( 'vgarco_login_failed', vgarco_login_error_message() );
	}
	return $user;
}
add_filter( 'authenticate', 'vgarco_auth_generic_error', 99 );

/**
 * Ghi lượt sai. Báo email quản trị đúng một lần khi một TÀI KHOẢN vừa bị khóa (không báo khi chỉ IP bị khóa: đó là chuyện thường ngày).
 */
add_action(
	'wp_login_failed',
	static function ( $username ): void {
		$username = (string) $username;
		if ( '' === $username ) {
			return;
		}
		$now = time();

		$ip = vgarco_lockout_record_ip( vgarco_lockout_load( 'ip', vgarco_client_ip() ), $now );
		vgarco_lockout_save( 'ip', vgarco_client_ip(), $ip['state'] );

		$account = vgarco_lockout_account( $username );
		$user    = vgarco_lockout_record_user( vgarco_lockout_load( 'user', $account ), $now );
		vgarco_lockout_save( 'user', $account, $user['state'] );

		if ( $user['triggered'] ) {
			wp_mail(
				(string) get_option( 'admin_email' ),
				'[VGARCO] Tài khoản bị tạm khóa do đăng nhập sai nhiều lần',
				sprintf(
					"Tài khoản \"%s\" bị tạm khóa %d phút do đăng nhập sai %d lần trong %d phút.\nThời điểm: %s\n\nNếu đây không phải bạn hay đồng nghiệp, có thể đang có người dò mật khẩu: kiểm tra quy tắc rate limit ở Cloudflare và bảo đảm tài khoản đã bật 2FA.",
					sanitize_user( $account, true ),
					(int) ( VGARCO_LOCK_USER_DURATION / 60 ),
					VGARCO_LOCK_USER_MAX,
					(int) ( VGARCO_LOCK_USER_WINDOW / 60 ),
					wp_date( 'd/m/Y H:i:s' )
				)
			);
		}
	}
);

/** Đăng nhập đúng: xóa bộ đếm của tài khoản và của IP (người dùng thật gõ sai vài lần rồi đúng). */
add_action(
	'wp_login',
	static function ( $user_login ): void {
		delete_transient( vgarco_lockout_key( 'user', vgarco_lockout_account( (string) $user_login ), vgarco_lockout_salt() ) );
		delete_transient( vgarco_lockout_key( 'ip', vgarco_client_ip(), vgarco_lockout_salt() ) );
	}
);

/**
 * Quên mật khẩu: lõi WordPress báo "không có tài khoản với tên hoặc email này" khi tài khoản không tồn tại, nên dò được danh sách
 * tài khoản và email quản trị (phát hiện khi chạy trên WordPress 7.1 thật; thông báo đăng nhập chung ở trên không che được lối này).
 * Tài khoản không tồn tại: trả đúng trang "kiểm tra email" như khi có, không gửi thư. Tài khoản có thật: để lõi xử lý.
 */
function vgarco_lostpassword_no_enumeration(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	$login = trim( (string) wp_unslash( $_POST['user_login'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- lõi cũng không dùng nonce ở biểu mẫu này
	if ( '' === $login ) {
		return; // lõi tự báo "hãy nhập tên hoặc email": không tiết lộ gì
	}
	$user = get_user_by( 'login', $login );
	if ( ! $user && is_email( $login ) ) {
		$user = get_user_by( 'email', $login );
	}
	if ( $user ) {
		return;
	}
	$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? (string) wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	wp_safe_redirect( $redirect_to );
	exit;
}
add_action( 'login_form_lostpassword', 'vgarco_lostpassword_no_enumeration' );
add_action( 'login_form_retrievepassword', 'vgarco_lostpassword_no_enumeration' );

/**
 * Báo email quản trị khi có tài khoản quản trị mới (tạo mới hoặc được nâng quyền) — đề phòng kẻ đã vào được tự cấp quyền cho mình.
 */
function vgarco_notify_new_admin( int $user_id ): void {
	static $notified = array();
	$user = get_userdata( $user_id );
	if ( ! $user || isset( $notified[ $user_id ] ) || ! in_array( 'administrator', (array) $user->roles, true ) ) {
		return;
	}
	$notified[ $user_id ] = true;

	wp_mail(
		(string) get_option( 'admin_email' ),
		'[VGARCO] Có tài khoản quản trị mới',
		sprintf(
			"Tài khoản \"%s\" (%s) vừa được tạo hoặc nâng lên quyền Quản trị viên.\nThời điểm: %s\nNgười thực hiện: %s\n\nNếu không ai trong nhóm vận hành thực hiện việc này, đổi mật khẩu các tài khoản quản trị ngay và kiểm tra nhật ký.",
			$user->user_login,
			$user->user_email,
			wp_date( 'd/m/Y H:i:s' ),
			is_user_logged_in() ? wp_get_current_user()->user_login : 'không xác định'
		)
	);
}
add_action( 'user_register', 'vgarco_notify_new_admin' );
add_action(
	'set_user_role',
	static function ( $user_id, $role ): void {
		if ( 'administrator' === $role ) {
			vgarco_notify_new_admin( (int) $user_id );
		}
	},
	10,
	2
);

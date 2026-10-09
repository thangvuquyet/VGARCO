<?php

defined( 'ABSPATH' ) || exit;

function vgarco_lockout_salt(): string {
	return wp_salt( 'auth' );
}

function vgarco_lockout_load( string $kind, string $value ): array {
	return vgarco_lockout_state( get_transient( vgarco_lockout_key( $kind, $value, vgarco_lockout_salt() ) ) );
}

function vgarco_lockout_save( string $kind, string $value, array $state ): void {
	set_transient( vgarco_lockout_key( $kind, $value, vgarco_lockout_salt() ), $state, 2 * HOUR_IN_SECONDS );
}

function vgarco_lockout_account( string $typed ): string {
	$user = get_user_by( 'login', $typed );
	if ( ! $user && is_email( $typed ) ) {
		$user = get_user_by( 'email', $typed );
	}
	return $user ? (string) $user->user_login : $typed;
}

function vgarco_auth_guard( $user, $username = '', $password = '' ) {
	if ( '' === (string) $username ) {
		return $user;
	}
	$now = time();
	if ( vgarco_lockout_remaining( vgarco_lockout_load( 'ip', vgarco_client_ip() ), $now ) > 0
		|| vgarco_lockout_remaining( vgarco_lockout_load( 'user', vgarco_lockout_account( (string) $username ) ), $now ) > 0 ) {
		return new WP_Error( 'vgarco_locked', vgarco_login_error_message() );
	}
	return $user;
}
add_filter( 'authenticate', 'vgarco_auth_guard', 98, 3 );

function vgarco_auth_generic_error( $user ) {
	if ( $user instanceof WP_Error && array_intersect( array_merge( vgarco_login_leaky_codes(), array( 'vgarco_locked' ) ), $user->get_error_codes() ) ) {
		return new WP_Error( 'vgarco_login_failed', vgarco_login_error_message() );
	}
	return $user;
}
add_filter( 'authenticate', 'vgarco_auth_generic_error', 99 );

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

add_action(
	'wp_login',
	static function ( $user_login ): void {
		delete_transient( vgarco_lockout_key( 'user', vgarco_lockout_account( (string) $user_login ), vgarco_lockout_salt() ) );
		delete_transient( vgarco_lockout_key( 'ip', vgarco_client_ip(), vgarco_lockout_salt() ) );
	}
);

function vgarco_lostpassword_no_enumeration(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	$login = trim( (string) wp_unslash( $_POST['user_login'] ?? '' ) );
	if ( '' === $login ) {
		return;
	}
	$user = get_user_by( 'login', $login );
	if ( ! $user && is_email( $login ) ) {
		$user = get_user_by( 'email', $login );
	}
	if ( $user ) {
		return;
	}
	$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? (string) wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm';
	wp_safe_redirect( $redirect_to );
	exit;
}
add_action( 'login_form_lostpassword', 'vgarco_lostpassword_no_enumeration' );
add_action( 'login_form_retrievepassword', 'vgarco_lostpassword_no_enumeration' );

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

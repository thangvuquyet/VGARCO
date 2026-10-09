<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_LOCK_IP_MAX        = 5;
const VGARCO_LOCK_IP_WINDOW     = 15 * 60;
const VGARCO_LOCK_IP_DURATION   = 15 * 60;
const VGARCO_LOCK_USER_MAX      = 10;
const VGARCO_LOCK_USER_WINDOW   = 60 * 60;
const VGARCO_LOCK_USER_DURATION = 60 * 60;

function vgarco_lockout_state( $raw ): array {
	$raw = is_array( $raw ) ? $raw : array();
	return array(
		'attempts' => array_values( array_map( 'intval', (array) ( $raw['attempts'] ?? array() ) ) ),
		'until'    => (int) ( $raw['until'] ?? 0 ),
	);
}

function vgarco_lockout_prune( array $attempts, int $now, int $window ): array {
	$kept = array_values( array_filter( array_map( 'intval', $attempts ), static fn( int $t ): bool => $t > $now - $window && $t <= $now ) );
	sort( $kept );
	return $kept;
}

function vgarco_lockout_remaining( array $state, int $now ): int {
	return max( 0, vgarco_lockout_state( $state )['until'] - $now );
}

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

function vgarco_lockout_record_ip( array $state, int $now ): array {
	return vgarco_lockout_record( $state, $now, VGARCO_LOCK_IP_MAX, VGARCO_LOCK_IP_WINDOW, VGARCO_LOCK_IP_DURATION );
}

function vgarco_lockout_record_user( array $state, int $now ): array {
	return vgarco_lockout_record( $state, $now, VGARCO_LOCK_USER_MAX, VGARCO_LOCK_USER_WINDOW, VGARCO_LOCK_USER_DURATION );
}

function vgarco_lockout_key( string $kind, string $value, string $salt ): string {
	$value = 'user' === $kind ? mb_strtolower( trim( $value ) ) : trim( $value );
	return 'vg_lock_' . $kind . '_' . substr( hash_hmac( 'sha256', $value, $salt ), 0, 32 );
}

function vgarco_login_error_message(): string {
	return 'Thông tin đăng nhập không đúng hoặc đã bị tạm khóa do thử quá nhiều lần. Vui lòng thử lại sau ít phút.';
}

function vgarco_login_leaky_codes(): array {
	return array( 'invalid_username', 'incorrect_password', 'invalid_email', 'invalidcombo', 'empty_username', 'empty_password' );
}

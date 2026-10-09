<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_CACHE_PURGE_DELAY = 30;

function vgarco_cache_public_types(): array {
	return array( 'page', 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri' );
}

function vgarco_cache_relevant_change( string $post_type, string $new_status, string $old_status ): bool {
	if ( ! in_array( $post_type, vgarco_cache_public_types(), true ) ) {
		return false;
	}
	return 'publish' === $new_status || 'publish' === $old_status;
}

function vgarco_cf_purge_request( string $zone, string $token ): ?array {
	$zone  = trim( $zone );
	$token = trim( $token );
	if ( ! preg_match( '/^[a-f0-9]{32}$/i', $zone ) || '' === $token || preg_match( '/[\s\r\n]/', $token ) ) {
		return null;
	}
	return array(
		'url'  => 'https://api.cloudflare.com/client/v4/zones/' . strtolower( $zone ) . '/purge_cache',
		'args' => array(
			'method'  => 'POST',
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => '{"purge_everything":true}',
		),
	);
}

function vgarco_cf_parse_response( int $status, string $body ): array {
	$data = json_decode( $body, true );
	if ( 200 === $status && is_array( $data ) && ! empty( $data['success'] ) ) {
		return array( 'ok' => true, 'message' => '' );
	}
	$error = is_array( $data ) && ! empty( $data['errors'][0]['message'] ) ? (string) $data['errors'][0]['message'] : '';
	return array( 'ok' => false, 'message' => "HTTP {$status}" . ( '' !== $error ? ': ' . $error : '' ) );
}

function vgarco_next_daily_run( int $now, string $timezone, int $hour = 0, int $minute = 10 ): int {
	$tz    = new DateTimeZone( $timezone );
	$local = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz );
	$next  = $local->setTime( $hour, $minute, 0 );
	if ( $next->getTimestamp() <= $now ) {
		$next = $next->modify( '+1 day' )->setTime( $hour, $minute, 0 );
	}
	return $next->getTimestamp();
}

<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_APPLY_MIN_SECONDS      = 4;
const VGARCO_APPLY_MAX_SECONDS      = 7200;
const VGARCO_CAPTCHA_TTL            = 600;
const VGARCO_APPLY_CONSENT_VERSION  = '2026-09';

function vgarco_apply_process( array $in, array $files, array $ctx, array $deps ): array {
	$reply = static fn( int $status, array $body ): array => array( 'status' => $status, 'body' => $body );

	if ( 'POST' !== strtoupper( (string) ( $ctx['method'] ?? '' ) ) || ! vgarco_apply_same_origin( (string) ( $ctx['origin'] ?? '' ), (string) ( $ctx['referer'] ?? '' ), (string) ( $ctx['host'] ?? '' ) ) ) {
		return $reply( 403, array( 'ok' => false, 'code' => 'forbidden' ) );
	}

	$ip = (string) ( $ctx['ip_hash'] ?? '' );
	if ( ! $deps['rate_attempts']( $ip ) || ! $deps['rate_submissions']( $ip ) ) {
		return $reply( 429, array( 'ok' => false, 'code' => 'rate_limited' ) );
	}
	$deps['rate_record']( $ip, false );

	if ( '' !== trim( (string) ( $in['website'] ?? '' ) ) ) {
		return $reply( 400, array( 'ok' => false, 'code' => 'rejected' ) );
	}
	$token = (string) ( $in['token'] ?? '' );
	$entry = '' !== $token ? $deps['token_peek']( $token ) : null;
	if ( $entry ) {
		$elapsed = (int) $ctx['now'] - (int) $entry['issued'];
		if ( $elapsed < VGARCO_APPLY_MIN_SECONDS || $elapsed > VGARCO_APPLY_MAX_SECONDS ) {
			return $reply( 400, array( 'ok' => false, 'code' => 'rejected' ) );
		}
	}

	$captcha_ok = $entry && hash_equals( (string) $entry['hash'], $deps['hash']( (string) ( $in['captcha'] ?? '' ) ) );
	if ( ! $captcha_ok ) {
		if ( $entry ) {
			$deps['token_consume']( $token );
		}
		return $reply(
			400,
			array(
				'ok'      => false,
				'code'    => 'validation',
				'errors'  => array( 'captcha' => 'captcha' ),
				'captcha' => $deps['token_issue'](),
			)
		);
	}

	$job_id = (int) ( $in['job_id'] ?? 0 );
	if ( $job_id <= 0 || ! $deps['job_open']( $job_id ) ) {
		return $reply( 409, array( 'ok' => false, 'code' => 'closed' ) );
	}

	$errors = vgarco_apply_validate_fields( $in );
	$cv     = vgarco_apply_validate_cv( $files['cv'] ?? null );
	if ( ! $cv['ok'] ) {
		$errors['cv'] = $cv['error'];
	}
	if ( $errors ) {
		return $reply( 400, array( 'ok' => false, 'code' => 'validation', 'errors' => $errors ) );
	}

	if ( ! $deps['token_consume']( $token ) ) {
		return $reply( 400, array( 'ok' => false, 'code' => 'rejected' ) );
	}

	try {
		$stored = $deps['save_cv']( (string) $files['cv']['tmp_name'], $cv['ext'] );
	} catch ( Throwable $e ) {
		return $reply( 500, array( 'ok' => false, 'code' => 'server' ) );
	}

	try {
		$record_id = $deps['insert'](
			array(
				'job_id'       => $job_id,
				'lang'         => in_array( $in['lang'] ?? '', array( 'vi', 'de' ), true ) ? $in['lang'] : 'vi',
				'name'         => trim( (string) $in['name'] ),
				'email'        => trim( (string) $in['email'] ),
				'phone'        => trim( (string) $in['phone'] ),
				'cv_path'      => $stored['path'],
				'cv_name'      => $cv['name'],
				'cv_mime'      => $cv['mime'],
				'cv_size'      => $cv['size'],
				'consent_at'   => (int) $ctx['now'],
				'consent_ver'  => VGARCO_APPLY_CONSENT_VERSION,
				'ip_hash'      => $ip,
			)
		);
	} catch ( Throwable $e ) {
		$deps['delete_cv']( $stored['path'] );
		return $reply( 500, array( 'ok' => false, 'code' => 'server' ) );
	}

	$deps['rate_record']( $ip, true );

	$mail = array( 'hr' => false, 'candidate' => false );
	try {
		$mail = $deps['mail']( $record_id );
	} catch ( Throwable $e ) {
		unset( $e );
	}

	return $reply( 200, array( 'ok' => true, 'id' => $record_id, 'mail' => $mail ) );
}

function vgarco_apply_same_origin( string $origin, string $referer, string $host ): bool {
	if ( '' === $host ) {
		return false;
	}
	foreach ( array( $origin, $referer ) as $candidate ) {
		if ( '' !== $candidate ) {
			$found = parse_url( $candidate, PHP_URL_HOST );
			return is_string( $found ) && 0 === strcasecmp( $found, $host );
		}
	}
	return false;
}

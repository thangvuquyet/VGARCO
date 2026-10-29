<?php

defined( 'ABSPATH' ) || exit;

function vgarco_dot_get( $data, string $path ) {
	foreach ( explode( '.', $path ) as $key ) {
		if ( ! is_array( $data ) || ! array_key_exists( $key, $data ) ) {
			return null;
		}
		$data = $data[ $key ];
	}
	return $data;
}

function vgarco_option_defaults(): array {
	return array(
		'phap_nhan'       => array(
			'ten'               => 'Công ty TNHH Tư vấn Xây dựng VGARCO',
			'ma_so_thue'        => '2901635947',
			'dia_chi_dang_ky'   => 'Số 75, ngõ 6, đường Đặng Văn Ngữ, phường Kim Liên, Hà Nội',
			'dia_chi_van_phong' => "Nhà H30, ngõ 68 Trung Kính,\nphường Yên Hòa, Hà Nội",
		),
		'lien_he'         => array(
			'dien_thoai'      => '0988 961 666',
			'dien_thoai_tel'  => '+84988961666',
			'email_chung'     => 'vgarco.cc@gmail.com',
			'email_tuyen_dung' => '',
		),
		'mang_xa_hoi'     => array(
			'zalo'     => '',
			'facebook' => '',
		),
		'chi_so_gioi_thieu' => array(
			array(
				'so'     => '13',
				'hau_to' => '+',
				'nhan'   => __( 'Năm hình thành và phát triển', 'vgarco-core' ),
			),
			array(
				'so'     => '20',
				'hau_to' => '',
				'nhan'   => __( 'Dự án trong hồ sơ năng lực', 'vgarco-core' ),
			),
			array(
				'so'     => '03',
				'hau_to' => '',
				'nhan'   => __( 'Quốc gia có dự án', 'vgarco-core' ),
			),
			array(
				'so'     => '06',
				'hau_to' => '',
				'nhan'   => __( 'Nhóm chuyên môn phối hợp', 'vgarco-core' ),
			),
		),
		'chi_so_trang_chu' => array(
			array(
				'so'     => '13',
				'hau_to' => '+',
				'nhan'   => __( 'Năm hình thành và phát triển', 'vgarco-core' ),
			),
			array(
				'so'     => '03',
				'hau_to' => '',
				'nhan'   => __( 'Quốc gia có dự án', 'vgarco-core' ),
			),
			array(
				'so'     => '06',
				'hau_to' => '',
				'nhan'   => __( 'Nhóm chuyên môn phối hợp', 'vgarco-core' ),
			),
		),
	);
}

if ( ! function_exists( 'vgarco_option' ) ) {
	function vgarco_option( string $path, $fallback = '' ) {
		$root  = explode( '.', $path )[0];
		$saved = function_exists( 'get_field' ) ? get_field( $root, 'option' ) : null;
		$value = vgarco_dot_get( array( $root => $saved ), $path );

		if ( null !== $value && '' !== $value && array() !== $value && false !== $value ) {
			return $value;
		}

		$default = vgarco_dot_get( vgarco_option_defaults(), $path );
		return $default ?? $fallback;
	}
}

function vgarco_client_ip(): string {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $key ) {
		$value = isset( $_SERVER[ $key ] ) ? trim( (string) wp_unslash( $_SERVER[ $key ] ) ) : '';
		if ( '' !== $value && false !== filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return $value;
		}
	}
	return '0.0.0.0';
}

function vgarco_with_language( string $lang, callable $fn ) {
	global $l10n;

	$locale = 'de' === $lang ? 'de_DE' : 'vi';
	$files  = array(
		'vgarco'      => get_theme_file_path( "languages/{$locale}.mo" ),
		'vgarco-core' => VGARCO_CORE_DIR . "languages/vgarco-core-{$locale}.mo",
	);

	$saved = array();
	foreach ( $files as $domain => $file ) {
		$saved[ $domain ] = $l10n[ $domain ] ?? null;
		unset( $l10n[ $domain ] );
		if ( is_readable( $file ) ) {
			load_textdomain( $domain, $file, $locale );
		}
	}
	do_action( 'wpml_switch_language', $lang );

	try {
		return $fn();
	} finally {
		do_action( 'wpml_switch_language', null );
		foreach ( $saved as $domain => $translations ) {
			if ( null === $translations ) {
				unset( $l10n[ $domain ] );
			} else {
				$l10n[ $domain ] = $translations;
			}
		}
	}
}

if ( ! function_exists( 'vgarco_directions_url' ) ) {
	function vgarco_directions_url(): string {
		$custom = vgarco_option( 'chi_duong_url' );
		if ( is_string( $custom ) && '' !== $custom ) {
			return $custom;
		}
		$address = trim( (string) preg_replace( '/\s+/u', ' ', (string) vgarco_option( 'phap_nhan.dia_chi_van_phong' ) ) );
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
	}
}

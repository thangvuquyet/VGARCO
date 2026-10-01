<?php
/**
 * Hàm dùng chung: đọc trang cấu hình kèm giá trị mặc định.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Đọc một giá trị lồng nhau bằng đường dẫn chấm, ví dụ "lien_he.dien_thoai".
 *
 * @param mixed  $data Mảng dữ liệu.
 * @param string $path Đường dẫn.
 * @return mixed|null null nếu không có.
 */
function vgarco_dot_get( $data, string $path ) {
	foreach ( explode( '.', $path ) as $key ) {
		if ( ! is_array( $data ) || ! array_key_exists( $key, $data ) ) {
			return null;
		}
		$data = $data[ $key ];
	}
	return $data;
}

/**
 * Giá trị mặc định của trang cấu hình — lấy từ hồ sơ năng lực (NOI DUNG WEB.docx).
 *
 * Vì sao có mặc định trong mã: cài mới chưa ai lưu trang cấu hình thì đầu trang và chân trang vẫn
 * hiển thị đúng. Sau khi quản trị viên lưu, giá trị đã lưu luôn thắng.
 *
 * @return array
 */
function vgarco_option_defaults(): array {
	return array(
		'phap_nhan'       => array(
			'ten'               => 'Công ty TNHH Tư vấn Xây dựng VGARCO',
			'ma_so_thue'        => '2901635947',
			'dia_chi_dang_ky'   => 'Số 75, ngõ 6, đường Đặng Văn Ngữ, phường Kim Liên, Hà Nội',
			// Có xuống dòng: bản thiết kế ngắt dòng sau tên phố. Liên kết "Chỉ đường" tự nối các dòng lại.
			'dia_chi_van_phong' => "Nhà H30, ngõ 68 Trung Kính,\nphường Yên Hòa, Hà Nội",
		),
		'lien_he'         => array(
			'dien_thoai'      => '0988 961 666',
			'dien_thoai_tel'  => '+84988961666',
			// Tạm dùng địa chỉ trong hồ sơ. Đổi sang info@vgarco.vn khi hộp thư theo tên miền hoạt động.
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

/**
 * Đọc một mục cấu hình: giá trị đã lưu, nếu trống thì mặc định trong mã, nếu vẫn không có thì $fallback.
 *
 * @param string $path     Đường dẫn chấm, phần đầu là tên trường cấp cao nhất. Ví dụ "lien_he.email_chung".
 * @param mixed  $fallback Trả về khi không có gì.
 * @return mixed
 */
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

/**
 * IP người gửi. Sau Cloudflare mọi kết nối đến từ IP Cloudflare nên phải đọc CF-Connecting-IP; máy chủ gốc chỉ nhận
 * kết nối từ Cloudflare (Authenticated Origin Pulls, mục 10.1) nên header này không giả mạo được từ ngoài.
 */
function vgarco_client_ip(): string {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $key ) {
		$value = isset( $_SERVER[ $key ] ) ? trim( (string) wp_unslash( $_SERVER[ $key ] ) ) : '';
		if ( '' !== $value && false !== filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return $value;
		}
	}
	return '0.0.0.0';
}

/**
 * Chạy $fn với bản dịch của VGARCO ở một ngôn ngữ, rồi trả về như cũ. Mẫu thư mặc định và chuỗi giao diện phải theo ngôn ngữ của người nhận,
 * không theo ngôn ngữ của yêu cầu hiện tại (cron chạy ở ngôn ngữ mặc định; thư báo nhân sự luôn tiếng Việt dù ứng viên nộp bằng tiếng Đức).
 *
 * Nạp THẲNG tệp .mo của theme và plugin thay vì dùng switch_to_locale(): hàm đó từ chối đổi sang ngôn ngữ chưa cài gói ngôn ngữ lõi
 * (thử trên WordPress 7.1: switch_to_locale('de_DE') trả false) và không nạp lại bản dịch ở đường dẫn riêng. Tiếng Việt là ngôn ngữ gốc
 * của chuỗi nên không có tệp .mo: bỏ bản dịch đang nạp là ra đúng chuỗi gốc.
 */
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

/**
 * Liên kết "Chỉ đường" mở Google Maps ở thẻ mới (yêu cầu 30). Không nhúng bản đồ.
 */
function vgarco_directions_url(): string {
	$custom = vgarco_option( 'chi_duong_url' );
	if ( is_string( $custom ) && '' !== $custom ) {
		return $custom;
	}
	// Địa chỉ nhiều dòng: nối thành một dòng để truy vấn bản đồ không chứa ký tự xuống dòng.
	$address = trim( (string) preg_replace( '/\s+/u', ' ', (string) vgarco_option( 'phap_nhan.dia_chi_van_phong' ) ) );
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
}

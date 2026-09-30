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

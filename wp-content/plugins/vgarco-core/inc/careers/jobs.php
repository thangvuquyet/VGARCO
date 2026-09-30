<?php
/**
 * Vị trí tuyển dụng: trạng thái nhận hồ sơ (mục 6.4 của phương án). Dùng chung cho giao diện (danh sách, chi tiết,
 * trang ứng tuyển) và cho API nộp hồ sơ — máy chủ luôn kiểm tra lại, không tin trang có thể đang nằm trong cache.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Một vị trí đang nhận hồ sơ khi: đã xuất bản, trạng thái "đang nhận", và hạn nộp trống hoặc chưa qua.
 * Hạn nộp tính đến hết ngày đó theo múi giờ của website.
 */
function vgarco_job_is_open( int $post_id ): bool {
	if ( 'vi_tri' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		return false;
	}
	if ( 'da_dong' === (string) get_field( 'trang_thai', $post_id ) ) {
		return false;
	}
	$deadline = trim( (string) get_field( 'han_nop', $post_id ) ); // định dạng Ymd
	return '' === $deadline || $deadline >= wp_date( 'Ymd' );
}

/**
 * Tên loại hình để hiển thị.
 *
 * @param string $type toan_thoi_gian | ban_thoi_gian | thuc_tap
 */
function vgarco_job_type_label( string $type ): string {
	$labels = array(
		'toan_thoi_gian' => __( 'Toàn thời gian', 'vgarco-core' ),
		'ban_thoi_gian'  => __( 'Bán thời gian', 'vgarco-core' ),
		'thuc_tap'       => __( 'Thực tập', 'vgarco-core' ),
	);
	return $labels[ $type ] ?? $labels['toan_thoi_gian'];
}

/**
 * Chuỗi để so khớp tìm kiếm: chữ thường, bỏ dấu (tiếng Việt: đ → d; tiếng Đức: ä → ae, ß → ss theo bảng của WordPress).
 * Truy vấn và dữ liệu đi qua cùng một hàm nên gõ có dấu hay không dấu đều tìm được.
 */
function vgarco_fold( string $text ): string {
	return mb_strtolower( remove_accents( $text ) );
}

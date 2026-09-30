<?php
/**
 * Nội dung trang tĩnh: đọc trường ACF của trang hiện tại, thiếu thì dùng bản mặc định trong mã.
 *
 * Vì sao có mặc định trong mã: cài mới chưa ai nhập gì thì các trang Về công ty, Phòng ban... vẫn hiển thị
 * đúng nội dung của bản thiết kế; bản mặc định là chuỗi gettext nên bản tiếng Đức lấy từ languages/ (.po).
 * Biên tập viên chỉ cần nhập vào trường ACF khi muốn thay bản mặc định — giá trị đã nhập luôn thắng.
 * Chỉ áp dụng cho chữ. Ảnh và danh sách phụ thuộc dữ liệu (thành viên, bài viết...) không có mặc định:
 * trống thì khối tự ẩn.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

/**
 * Giá trị chữ của một trường ACF, thiếu thì $default.
 *
 * @param string   $name    Tên trường.
 * @param string   $default Chữ mặc định (đã qua __()).
 * @param int|null $post_id Trang chứa trường; null = trang đang xem.
 */
function vgarco_field_text( string $name, string $default = '', ?int $post_id = null ): string {
	$value = function_exists( 'get_field' ) ? get_field( $name, $post_id ?? (int) get_queried_object_id() ) : null;
	$value = is_string( $value ) ? trim( $value ) : '';
	return '' !== $value ? $value : $default;
}

/**
 * Tiêu đề có ngắt dòng và cụm nhấn <em>: trường ACF hoặc bản mặc định, đã lọc qua vgarco_heading().
 */
function vgarco_field_heading( string $name, string $default, ?int $post_id = null ): string {
	return vgarco_heading( vgarco_field_text( $name, $default, $post_id ) );
}

/**
 * Đoạn chữ có ngắt dòng chủ ý: trường ACF hoặc bản mặc định, đã thoát HTML, xuống dòng thành <br>.
 */
function vgarco_field_lines( string $name, string $default, ?int $post_id = null ): string {
	return vgarco_lines( vgarco_field_text( $name, $default, $post_id ) );
}

/**
 * ID ảnh của một trường ảnh; 0 nếu chưa chọn.
 */
function vgarco_field_image( string $name, ?int $post_id = null ): int {
	$value = function_exists( 'get_field' ) ? get_field( $name, $post_id ?? (int) get_queried_object_id() ) : null;
	return is_numeric( $value ) ? (int) $value : 0;
}

/**
 * Các dòng của một trường lặp. Dòng nào mọi ô đều trống thì bỏ. Không còn dòng nào thì dùng $defaults.
 *
 * @param string[] $keys     Tên các ô con cần lấy (ô không có trả về chuỗi rỗng).
 * @param array[]  $defaults Mỗi phần tử là mảng cùng khóa với $keys.
 * @return array[]
 */
function vgarco_field_rows( string $name, array $keys, array $defaults = array(), ?int $post_id = null ): array {
	$saved = function_exists( 'get_field' ) ? get_field( $name, $post_id ?? (int) get_queried_object_id() ) : null;
	$rows  = array();

	foreach ( is_array( $saved ) ? $saved : array() as $row ) {
		$clean = array();
		foreach ( $keys as $key ) {
			$value         = $row[ $key ] ?? '';
			$clean[ $key ] = is_string( $value ) ? trim( $value ) : $value;
		}
		if ( array_filter( $clean, static fn( $v ) => '' !== $v && null !== $v && 0 !== $v ) ) {
			$rows[] = $clean;
		}
	}

	return $rows ?: $defaults;
}

/**
 * Tách chuỗi nhiều dòng thành danh sách (bỏ dòng trống). Dùng cho ô "mỗi dòng một mục".
 *
 * @return string[]
 */
function vgarco_split_lines( string $text ): array {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ?: array() ), static fn( $l ) => '' !== $l ) );
}

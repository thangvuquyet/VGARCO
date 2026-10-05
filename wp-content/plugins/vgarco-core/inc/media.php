<?php
/**
 * Xử lý ảnh (mục 7.7 của phương án).
 *
 * Ảnh được cắt tay đúng tỉ lệ TRƯỚC khi tải lên (đặc tả 6.2), nên WordPress chỉ cần thu nhỏ theo
 * chiều rộng, tuyệt đối không cắt lại: cắt tự động sẽ xén mất mái hoặc nền công trình.
 * AVIF/WebP do plugin "Modern Image Formats" sinh từ các kích thước này.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Các chiều rộng được sinh (px). Khớp thuộc tính srcset/sizes trong mẫu trang.
 *
 * @return int[]
 */
function vgarco_image_widths(): array {
	return array( 480, 800, 1200, 1600, 2560 );
}

add_action(
	'after_setup_theme',
	static function (): void {
		foreach ( vgarco_image_widths() as $width ) {
			// Chiều cao 0 = không giới hạn; crop false = chỉ thu nhỏ, giữ nguyên tỉ lệ ảnh gốc.
			add_image_size( "vg-{$width}", $width, 0, false );
		}
	}
);

// Bỏ các kích thước mặc định không dùng, tránh sinh tệp rác trên đĩa.
add_filter(
	'intermediate_image_sizes_advanced',
	static function ( array $sizes ): array {
		unset( $sizes['medium_large'], $sizes['1536x1536'], $sizes['2048x2048'] );
		return $sizes;
	}
);

// Ảnh gốc lớn hơn 2560 px được WordPress thu về 2560 px khi tải lên.
add_filter(
	'big_image_size_threshold',
	static function (): int {
		return 2560;
	}
);

// Chất lượng AVIF/WebP có chủ đích (xem media-lib.php): mặc định của WordPress làm ảnh AVIF lớn gấp đôi trần dung lượng của kế hoạch.
add_filter( 'wp_editor_set_quality', 'vgarco_image_quality', 10, 2 );

/*
 * Siêu dữ liệu ảnh (tọa độ GPS, giờ chụp, thiết bị).
 *
 * WordPress giữ nguyên tệp gốc đã tải lên và phục vụ công khai trong uploads, còn các bản thu nhỏ do GD sinh ra thì đã sạch. Đo trên ảnh thật của
 * dự án: 22 trong 113 ảnh JPEG có GPS (Bảo tàng Lịch sử QĐND Lào, Nhà Đại sứ, Trường Lý luận chính trị...). Kế hoạch (mục 9.1) yêu cầu gỡ khi xuất ảnh,
 * nhưng một người biên tập tải thẳng ảnh điện thoại lên là lộ — nên gỡ ngay ở máy chủ, không dựa vào quy trình thủ công.
 */

// Chỉ nhận JPEG và PNG: định dạng ảnh khác (WebP, AVIF, HEIC, TIFF, GIF) cũng mang siêu dữ liệu mà ta chưa gỡ được. Tệp không phải ảnh (PDF...) giữ nguyên.
add_filter(
	'upload_mimes',
	static function ( array $mimes ): array {
		foreach ( $mimes as $extension => $mime ) {
			if ( str_starts_with( (string) $mime, 'image/' ) && ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
				unset( $mimes[ $extension ] );
			}
		}
		return $mimes;
	}
);

/**
 * Gỡ siêu dữ liệu của ảnh vừa tải lên, TRƯỚC khi WordPress tạo các bản thu nhỏ (cũng chạy cho ảnh nhập bằng `wp vgarco import`).
 * JPEG có hướng xoay (ảnh dọc chụp bằng điện thoại) được xoay thẳng rồi mới gỡ, vì hướng xoay nằm trong chính EXIF sắp bị bỏ.
 * Không xử lý được thì XÓA tệp và từ chối: để nguyên là để lộ tọa độ.
 *
 * @param array $upload Kết quả của wp_handle_upload: file, url, type (hoặc error).
 * @return array
 */
function vgarco_sanitize_uploaded_image( array $upload ): array {
	$type = (string) ( $upload['type'] ?? '' );
	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || ! in_array( $type, array( 'image/jpeg', 'image/png' ), true ) ) {
		return $upload;
	}

	$file   = (string) $upload['file'];
	$reject = static function ( string $reason ) use ( $file ): array {
		wp_delete_file( $file );
		return array( 'error' => 'Không xử lý được ảnh này (' . $reason . '). Hãy xuất lại ảnh thành JPEG hoặc PNG rồi tải lên.' );
	};

	$bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $bytes ) {
		return $reject( 'không đọc được tệp' );
	}

	if ( 'image/png' === $type ) {
		$clean = vgarco_png_strip_metadata( $bytes );
	} else {
		$orientation = vgarco_jpeg_orientation( $bytes );
		if ( $orientation > 1 ) {
			// Hướng xoay do ta tự đọc (không cần phần mở rộng exif của PHP), lõi chỉ việc xoay.
			$force  = static fn(): int => $orientation;
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				return $reject( 'không xoay được ảnh dọc' );
			}
			add_filter( 'wp_image_maybe_exif_rotate', $force );
			$editor->maybe_exif_rotate();
			remove_filter( 'wp_image_maybe_exif_rotate', $force );
			$editor->set_quality( 92 );
			$saved = $editor->save( $file, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				return $reject( 'không lưu được ảnh sau khi xoay' );
			}
			$bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $bytes ) {
				return $reject( 'không đọc lại được ảnh sau khi xoay' );
			}
		}
		$clean = vgarco_jpeg_strip_metadata( $bytes );
	}

	if ( null === $clean ) {
		return $reject( 'tệp ảnh bị hỏng' );
	}
	if ( $clean !== $bytes && false === file_put_contents( $file, $clean ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $reject( 'không ghi được tệp' );
	}
	return $upload;
}
add_filter( 'wp_handle_upload', 'vgarco_sanitize_uploaded_image' );

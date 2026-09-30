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

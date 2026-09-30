<?php
/**
 * Hàm dùng trong mẫu trang.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

/**
 * Biểu tượng SVG nội tuyến từ assets/icons/<tên>.svg. Tệp là tài sản của theme (tin cậy).
 * Thêm aria-hidden: biểu tượng chỉ trang trí, nhãn đọc màn hình nằm ở nút/liên kết chứa nó.
 *
 * @param string $name  Tên tệp không đuôi: chỉ chữ thường, số, gạch ngang.
 * @param string $class Lớp CSS tùy chọn.
 * @return string Mã SVG hoặc chuỗi rỗng.
 */
function vgarco_icon( string $name, string $class = '' ): string {
	static $cache = array();

	if ( ! preg_match( '/^[a-z0-9-]+$/', $name ) ) {
		return '';
	}

	if ( ! isset( $cache[ $name ] ) ) {
		$file           = get_theme_file_path( "assets/icons/{$name}.svg" );
		$cache[ $name ] = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	if ( '' === $cache[ $name ] ) {
		return '';
	}

	$attributes = 'aria-hidden="true" focusable="false"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' );
	return (string) preg_replace( '/^<svg\b/', '<svg ' . $attributes, $cache[ $name ], 1 );
}

/**
 * Thoát HTML rồi đổi xuống dòng thành <br>. Dùng cho chuỗi có ngắt dòng chủ ý (địa chỉ, khẩu hiệu).
 * Người biên tập quyết định chỗ ngắt dòng, kể cả ở bản tiếng Đức, thay vì để trình duyệt tự ngắt.
 *
 * @param string $text Văn bản thuần, có thể có ký tự xuống dòng.
 * @return string HTML đã thoát, an toàn để in trực tiếp.
 */
function vgarco_lines( string $text ): string {
	// Không dùng nl2br(): nó giữ lại ký tự xuống dòng sau <br>.
	return (string) preg_replace( '/\r\n|\r|\n/', '<br>', esc_html( trim( $text ) ) );
}

/**
 * Đường dẫn vị trí ("Trang chủ / Dự án / Tên dự án"). Mục cuối là trang hiện tại, không có liên kết.
 *
 * @param array[] $trail    Mỗi phần tử: [ nhãn, địa chỉ ] — bỏ địa chỉ ở mục cuối.
 * @param string  $modifier Lớp bổ sung (ví dụ "breadcrumb--detail" cho trang chi tiết).
 */
function vgarco_breadcrumb( array $trail, string $modifier = '' ): void {
	echo '<nav class="breadcrumb wrap' . ( '' !== $modifier ? ' ' . esc_attr( $modifier ) : '' ) . '" aria-label="' . esc_attr__( 'Đường dẫn vị trí', 'vgarco' ) . '"><ol>';
	$last = count( $trail ) - 1;
	foreach ( $trail as $i => $crumb ) {
		if ( $i === $last ) {
			printf( '<li aria-current="page"><span>%s</span></li>', esc_html( $crumb[0] ) );
		} else {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $crumb[1] ), esc_html( $crumb[0] ) );
		}
	}
	echo '</ol></nav>';
}

/**
 * Số có chữ số 0 đứng đầu ("07"), dùng cho số thứ tự và số lượng ở tiêu đề trang.
 */
function vgarco_pad( int $number ): string {
	return sprintf( '%02d', $number );
}

/**
 * Khóa loại trang: JS dùng để nạp đúng module (data-vg-page ở thẻ body).
 */
function vgarco_page_key(): string {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_404() ) {
		return 'notfound';
	}
	if ( is_singular( 'du_an' ) ) {
		return 'project';
	}
	if ( is_post_type_archive( 'du_an' ) || is_tax( 'loai_cong_trinh' ) ) {
		return 'projects';
	}
	if ( is_singular( 'tin_tuc' ) ) {
		return 'article';
	}
	if ( is_post_type_archive( 'tin_tuc' ) || is_tax( 'chuyen_muc' ) ) {
		return 'news';
	}
	if ( is_singular( 'vi_tri' ) ) {
		return 'job';
	}
	if ( is_page() ) {
		$template = get_page_template_slug();
		return $template ? sanitize_key( pathinfo( $template, PATHINFO_FILENAME ) ) : 'page';
	}
	return 'default';
}

/**
 * Bộ chuyển ngôn ngữ VI / DE (UC-10).
 *
 * Dùng liên kết thật (không phải nút + JS): chuyển ngôn ngữ hoạt động không cần JS và công cụ tìm kiếm thấy được.
 * Chỉ hiện khi có ít nhất 2 ngôn ngữ đang hiển thị với khách — ngôn ngữ ở chế độ ẩn (DE trước Mốc 2)
 * WPML tự loại khỏi danh sách với khách vãng lai.
 *
 * Còn thiếu: nhánh "trang chưa có bản dịch" (UC-10 2a) — làm cùng logic chuyển ngôn ngữ ở bước sau.
 */
function vgarco_language_switcher(): void {
	$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

	if ( ! is_array( $languages ) || count( $languages ) < 2 ) {
		return;
	}

	printf( '<div class="language-switch" role="group" aria-label="%s">', esc_attr__( 'Ngôn ngữ', 'vgarco' ) );
	foreach ( $languages as $code => $language ) {
		printf(
			'<a href="%1$s" lang="%2$s" hreflang="%2$s"%3$s aria-label="%4$s">%5$s</a>',
			esc_url( $language['url'] ),
			esc_attr( $code ),
			! empty( $language['active'] ) ? ' aria-current="true"' : '',
			esc_attr( $language['native_name'] ?? strtoupper( $code ) ),
			esc_html( strtoupper( $code ) )
		);
	}
	echo '</div>';
}

/**
 * Kênh mạng xã hội ở chân trang: có địa chỉ thì là liên kết, chưa có thì ghi "Đang cập nhật" (yêu cầu 29).
 *
 * @param string $label Tên kênh cho trình đọc màn hình.
 * @param string $url   Địa chỉ, có thể rỗng.
 * @param string $glyph Ký tự hiển thị trong vòng tròn.
 */
function vgarco_social_item( string $label, string $url, string $glyph ): void {
	if ( '' !== $url ) {
		printf(
			'<a href="%1$s" target="_blank" rel="noopener" aria-label="%2$s">%3$s</a>',
			esc_url( $url ),
			esc_attr( $label ),
			esc_html( $glyph )
		);
		return;
	}

	printf(
		'<span title="%1$s" role="img" aria-label="%1$s">%2$s</span>',
		/* translators: %s: tên kênh mạng xã hội, ví dụ Zalo. */
		esc_attr( sprintf( __( '%s — đang cập nhật', 'vgarco' ), $label ) ),
		esc_html( $glyph )
	);
}

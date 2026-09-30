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
	vgarco_breadcrumb_trail( $trail ); // nhớ lại để seo.php in BreadcrumbList ở cuối trang
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
 * Đường dẫn vị trí của trang hiện tại: đặt khi mẫu trang gọi vgarco_breadcrumb(), đọc ở chân trang để in JSON-LD BreadcrumbList.
 *
 * @param array[]|null $set Đặt đường dẫn mới; null để chỉ đọc.
 * @return array[] Mỗi phần tử: [nhãn, địa chỉ] hoặc [nhãn] cho mục cuối.
 */
function vgarco_breadcrumb_trail( ?array $set = null ): array {
	static $trail = array();
	if ( null !== $set ) {
		$trail = $set;
	}
	return $trail;
}

/**
 * Phân trang (UC-06: quá 9 bài thì chia trang). Không có gì để in khi chỉ một trang.
 *
 * @param int      $current Trang hiện tại (từ 1).
 * @param int      $total   Tổng số trang.
 * @param callable $url     Nhận số trang, trả địa chỉ.
 */
function vgarco_pagination( int $current, int $total, callable $url ): string {
	if ( $total < 2 ) {
		return '';
	}
	$current = max( 1, min( $current, $total ) );

	$item = static function ( string $label, int $page, string $aria = '', bool $is_current = false ) use ( $url ): string {
		if ( $is_current ) {
			return sprintf( '<li><span aria-current="page">%s</span></li>', esc_html( $label ) );
		}
		return sprintf(
			'<li><a href="%s"%s>%s</a></li>',
			esc_url( $url( $page ) ),
			'' !== $aria ? ' aria-label="' . esc_attr( $aria ) . '"' : '',
			esc_html( $label )
		);
	};

	$html = '<nav class="pagination" aria-label="' . esc_attr__( 'Phân trang', 'vgarco' ) . '"><ul>';
	if ( $current > 1 ) {
		$html .= $item( '←', $current - 1, __( 'Trang trước', 'vgarco' ) );
	}
	for ( $page = 1; $page <= $total; $page++ ) {
		$html .= $item( (string) $page, $page, '', $page === $current );
	}
	if ( $current < $total ) {
		$html .= $item( '→', $current + 1, __( 'Trang sau', 'vgarco' ) );
	}
	return $html . '</ul></nav>';
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
 * Các mục của bộ chuyển ngôn ngữ (UC-10) — phần THUẦN, kiểm thử được.
 *
 * Có bản dịch → liên kết thẳng tới trang tương ứng. Chưa có bản dịch (2a) → liên kết tới trang danh mục tương ứng của ngôn ngữ đích
 * (dự án → danh mục dự án; tin → trang tin; vị trí → trang tuyển dụng; trang khác → trang chủ), và đánh dấu "missing" để JS báo
 * "nội dung đang được cập nhật" ở trang đích.
 *
 * @param array<string,array> $languages Kết quả wpml_active_languages (skip_missing = 0): mã → [url, active, native_name, missing].
 * @param array<string,string> $fallbacks Mã ngôn ngữ → địa chỉ dự phòng (chỉ cần cho ngôn ngữ thiếu bản dịch).
 * @return array[] Mỗi mục: code, url, active (bool), missing (bool), name.
 */
function vgarco_switcher_items( array $languages, array $fallbacks ): array {
	$items = array();
	foreach ( $languages as $code => $language ) {
		$active  = ! empty( $language['active'] );
		$missing = ! $active && ! empty( $language['missing'] );
		$items[] = array(
			'code'    => (string) $code,
			'url'     => $missing && isset( $fallbacks[ $code ] ) && '' !== $fallbacks[ $code ] ? (string) $fallbacks[ $code ] : (string) ( $language['url'] ?? '' ),
			'active'  => $active,
			'missing' => $missing,
			'name'    => (string) ( $language['native_name'] ?? strtoupper( (string) $code ) ),
		);
	}
	return $items;
}

/**
 * Địa chỉ dự phòng cho mỗi ngôn ngữ thiếu bản dịch của trang hiện tại, theo loại trang. Địa chỉ đi qua WPML nên đúng ngôn ngữ đích
 * (kể cả phần đường dẫn đã dịch).
 *
 * @param array<string,array> $languages Xem vgarco_switcher_items().
 * @return array<string,string>
 */
function vgarco_switcher_fallbacks( array $languages ): array {
	$target = home_url( '/' );
	if ( is_singular( 'du_an' ) || is_tax( 'loai_cong_trinh' ) ) {
		$target = get_post_type_archive_link( 'du_an' ) ?: $target;
	} elseif ( is_singular( 'tin_tuc' ) || is_tax( 'chuyen_muc' ) ) {
		$target = get_post_type_archive_link( 'tin_tuc' ) ?: $target;
	} elseif ( is_singular( 'vi_tri' ) || 'apply' === vgarco_page_key() ) {
		$target = vgarco_page_url( 'careers' );
	}

	$fallbacks = array();
	foreach ( $languages as $code => $language ) {
		if ( ! empty( $language['missing'] ) ) {
			$fallbacks[ $code ] = (string) apply_filters( 'wpml_permalink', $target, $code );
		}
	}
	return $fallbacks;
}

/**
 * Bộ chuyển ngôn ngữ VI / DE.
 *
 * Dùng liên kết thật (không phải nút + JS): chuyển ngôn ngữ hoạt động không cần JS và công cụ tìm kiếm thấy được.
 * Chỉ hiện khi có ít nhất 2 ngôn ngữ đang hiển thị với khách — ngôn ngữ ở chế độ ẩn (DE trước Mốc 2)
 * WPML tự loại khỏi danh sách với khách vãng lai.
 */
function vgarco_language_switcher(): void {
	$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

	if ( ! is_array( $languages ) || count( $languages ) < 2 ) {
		return;
	}

	printf( '<div class="language-switch" role="group" aria-label="%s">', esc_attr__( 'Ngôn ngữ', 'vgarco' ) );
	foreach ( vgarco_switcher_items( $languages, vgarco_switcher_fallbacks( $languages ) ) as $item ) {
		printf(
			'<a href="%1$s" lang="%2$s" hreflang="%2$s"%3$s%4$s aria-label="%5$s">%6$s</a>',
			esc_url( $item['url'] ),
			esc_attr( $item['code'] ),
			$item['active'] ? ' aria-current="true"' : '',
			$item['missing'] ? ' data-lang-missing' : '',
			esc_attr( $item['name'] ),
			esc_html( strtoupper( $item['code'] ) )
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

/**
 * Các mục chính cho trang 404 (yêu cầu 35): địa chỉ, nhãn, lời nhắn theo ngữ cảnh. Địa chỉ lấy qua WPML nên đúng ngôn ngữ đang xem.
 * Mục có 'match' => true dùng để đoán ngữ cảnh từ đường dẫn bị lỗi.
 *
 * @return array<string,array{label:string,url:string,message:string,action:string,match:bool}>
 */
function vgarco_error_sections(): array {
	return array(
		'du_an'   => array(
			'label'   => __( 'Dự án', 'vgarco' ),
			'url'     => get_post_type_archive_link( 'du_an' ) ?: home_url( '/du-an/' ),
			'message' => __( 'Không tìm thấy dự án này. Dự án có thể đã được gỡ hoặc địa chỉ đã thay đổi — bạn có thể xem toàn bộ danh mục dự án.', 'vgarco' ),
			'action'  => __( 'Xem tất cả dự án', 'vgarco' ),
			'match'   => true,
		),
		'tin_tuc' => array(
			'label'   => __( 'Tin tức', 'vgarco' ),
			'url'     => get_post_type_archive_link( 'tin_tuc' ) ?: home_url( '/tin-tuc/' ),
			'message' => __( 'Không tìm thấy bài viết này. Bài có thể đã được gỡ hoặc địa chỉ đã thay đổi — bạn có thể xem các tin mới nhất.', 'vgarco' ),
			'action'  => __( 'Xem tin tức', 'vgarco' ),
			'match'   => true,
		),
		'careers' => array(
			'label'   => __( 'Tuyển dụng', 'vgarco' ),
			'url'     => vgarco_page_url( 'careers' ),
			'message' => __( 'Không tìm thấy vị trí này. Vị trí có thể đã ngừng nhận hồ sơ — bạn có thể xem các vị trí đang mở.', 'vgarco' ),
			'action'  => __( 'Xem vị trí đang tuyển', 'vgarco' ),
			'match'   => true,
		),
		'contact' => array(
			'label'   => __( 'Liên hệ', 'vgarco' ),
			'url'     => vgarco_page_url( 'contact' ),
			'message' => '',
			'action'  => __( 'Liên hệ VGARCO', 'vgarco' ),
			'match'   => false,
		),
	);
}

/**
 * Đoán mục của một đường dẫn: mục nào có đường dẫn là tiền tố của $path (dài nhất thắng). So khớp theo từng đoạn nên
 * "/tuyen-dung-khac/" không khớp mục "/tuyen-dung/". Không phân biệt hoa thường.
 *
 * @param string                     $path     Đường dẫn của yêu cầu bị lỗi, ví dụ "/du-an/khong-co/".
 * @param array<string,array>        $sections Kết quả của vgarco_error_sections().
 * @return string Khóa của mục, hoặc '' nếu không mục nào khớp.
 */
function vgarco_url_section( string $path, array $sections ): string {
	$path = strtolower( '/' . trim( $path, '/' ) . '/' );
	$best = '';
	$len  = 0;

	foreach ( $sections as $key => $section ) {
		if ( empty( $section['match'] ) ) {
			continue;
		}
		$prefix = strtolower( '/' . trim( (string) wp_parse_url( (string) $section['url'], PHP_URL_PATH ), '/' ) . '/' );
		if ( '//' === $prefix || '/' === $prefix ) {
			continue; // mục nằm ở gốc site: khớp mọi thứ, vô nghĩa
		}
		if ( str_starts_with( $path, $prefix ) && strlen( $prefix ) > $len ) {
			$best = (string) $key;
			$len  = strlen( $prefix );
		}
	}
	return $best;
}

<?php

defined( 'ABSPATH' ) || exit;

function vgarco_icon( string $name, string $class = '' ): string {
	static $cache = array();

	if ( ! preg_match( '/^[a-z0-9-]+$/', $name ) ) {
		return '';
	}

	if ( ! isset( $cache[ $name ] ) ) {
		$file           = get_theme_file_path( "assets/icons/{$name}.svg" );
		$cache[ $name ] = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : '';
	}

	if ( '' === $cache[ $name ] ) {
		return '';
	}

	$attributes = 'aria-hidden="true" focusable="false"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' );
	return (string) preg_replace( '/^<svg\b/', '<svg ' . $attributes, $cache[ $name ], 1 );
}

function vgarco_lines( string $text ): string {
	return (string) preg_replace( '/\r\n|\r|\n/', '<br>', esc_html( trim( $text ) ) );
}

function vgarco_breadcrumb( array $trail, string $modifier = '' ): void {
	vgarco_breadcrumb_trail( $trail );
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

function vgarco_breadcrumb_trail( ?array $set = null ): array {
	static $trail = array();
	if ( null !== $set ) {
		$trail = $set;
	}
	return $trail;
}

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

function vgarco_pad( int $number ): string {
	return sprintf( '%02d', $number );
}

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

function vgarco_switcher_fallbacks( array $languages ): array {
	$target  = home_url( '/' );
	$page_id = 0;
	if ( is_singular( 'du_an' ) || is_tax( 'loai_cong_trinh' ) ) {
		$target = get_post_type_archive_link( 'du_an' ) ?: $target;
	} elseif ( is_singular( 'tin_tuc' ) || is_tax( 'chuyen_muc' ) ) {
		$target = get_post_type_archive_link( 'tin_tuc' ) ?: $target;
	} elseif ( is_singular( 'vi_tri' ) || 'apply' === vgarco_page_key() ) {
		$target  = vgarco_page_url( 'careers' );
		$page_id = vgarco_page_id( 'careers' );
	}

	$fallbacks = array();
	foreach ( $languages as $code => $language ) {
		if ( empty( $language['missing'] ) ) {
			continue;
		}
		$translated         = $page_id ? (int) apply_filters( 'wpml_object_id', $page_id, 'page', false, $code ) : 0;
		$fallbacks[ $code ] = $translated && $translated !== $page_id ? (string) get_permalink( $translated ) : (string) apply_filters( 'wpml_permalink', $target, $code );
	}
	return $fallbacks;
}

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
		esc_attr( sprintf( __( '%s — đang cập nhật', 'vgarco' ), $label ) ),
		esc_html( $glyph )
	);
}

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
			continue;
		}
		if ( str_starts_with( $path, $prefix ) && strlen( $prefix ) > $len ) {
			$best = (string) $key;
			$len  = strlen( $prefix );
		}
	}
	return $best;
}

<?php
/**
 * SEO cơ bản trong theme, không dùng plugin (mục 8.9): tiêu đề theo mẫu, mô tả, canonical, Open Graph, JSON-LD
 * (Organization ở trang chủ, BreadcrumbList, JobPosting cho vị trí đang nhận hồ sơ) và noindex cho trang không nên lập chỉ mục.
 * hreflang do WPML sinh; sơ đồ trang là sơ đồ lõi của WordPress. Phần dựng thẻ nằm ở seo-lib.php (có kiểm thử).
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

require_once get_theme_file_path( 'inc/seo-lib.php' );

/* -------------------------------------------------------------------------
 * Tiêu đề
 * ---------------------------------------------------------------------- */

add_filter(
	'document_title_separator',
	static fn(): string => '—'
);

/**
 * Tiêu đề: "Tên trang — Mục — VGARCO" (dự án, tin, tuyển dụng có mục); trang chủ "VGARCO — Khẩu hiệu".
 */
add_filter(
	'document_title_parts',
	static function ( array $parts ): array {
		$site = 'VGARCO';

		if ( is_front_page() ) {
			return array(
				'title'   => $site,
				'tagline' => __( 'Tư vấn thiết kế, giám sát và quản lý dự án xây dựng', 'vgarco' ),
			);
		}
		if ( is_404() ) {
			return array( 'title' => __( 'Không tìm thấy trang', 'vgarco' ), 'site' => $site );
		}

		$section = '';
		if ( is_singular( 'du_an' ) || is_post_type_archive( 'du_an' ) || is_tax( 'loai_cong_trinh' ) ) {
			$section = __( 'Dự án', 'vgarco' );
		} elseif ( is_singular( 'tin_tuc' ) || is_post_type_archive( 'tin_tuc' ) || is_tax( 'chuyen_muc' ) ) {
			$section = __( 'Tin tức', 'vgarco' );
		} elseif ( is_singular( 'vi_tri' ) ) {
			$section = __( 'Tuyển dụng', 'vgarco' );
		}

		// Trang danh mục có tên trùng tên mục thì không lặp ("Dự án — Dự án — VGARCO").
		$title = trim( (string) ( $parts['title'] ?? '' ) );
		$out   = array( 'title' => $title );
		if ( '' !== $section && $section !== $title ) {
			$out['section'] = $section;
		}
		if ( isset( $parts['page'] ) ) {
			$out['page'] = $parts['page']; // "Trang 2" của trang có phân trang
		}
		$out['site'] = $site;
		return $out;
	}
);

/* -------------------------------------------------------------------------
 * Robots
 * ---------------------------------------------------------------------- */

/**
 * Không lập chỉ mục: trang 404, kết quả tìm kiếm, trang Ứng tuyển (mục 8.9) và TOÀN BỘ môi trường không phải production
 * (staging không bao giờ được xuất hiện trên Google).
 */
function vgarco_seo_noindex(): bool {
	if ( is_404() || is_search() || 'apply' === vgarco_page_key() ) {
		return true;
	}
	return 'production' !== wp_get_environment_type();
}

add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( vgarco_seo_noindex() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = 'production' !== wp_get_environment_type(); // trang 404/ứng tuyển vẫn cho đi theo liên kết
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
);

// WordPress tự in rel=canonical cho trang đơn: thay bằng bản của theme (có cả trang danh mục, loại, chuyên mục).
remove_action( 'wp_head', 'rel_canonical' );

/* -------------------------------------------------------------------------
 * Dữ liệu của trang
 * ---------------------------------------------------------------------- */

/**
 * Địa chỉ tuyệt đối: WordPress luôn trả địa chỉ tuyệt đối nên thường không đổi gì; địa chỉ bắt đầu bằng "/" (plugin hoặc bộ lọc
 * trả đường dẫn tương đối) được nối với địa chỉ gốc của website, vì thẻ canonical/OG và JSON-LD bắt buộc dùng địa chỉ tuyệt đối.
 */
function vgarco_seo_absolute( string $url ): string {
	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) . '://' . (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return str_starts_with( $base, '://' ) ? $url : $base . $url;
	}
	return $url;
}

/** Mô tả mặc định của website, dùng khi trang không có mô tả riêng. */
function vgarco_seo_default_description(): string {
	return __( 'Tư vấn thiết kế, giám sát và quản lý dự án xây dựng: công trình ngoại giao, quân sự, giáo dục, văn hóa, y tế và hạ tầng tại Việt Nam, Lào và CHLB Đức.', 'vgarco' );
}

/**
 * Ảnh chia sẻ: địa chỉ, kích thước, mô tả. Rỗng nếu chưa có ảnh.
 *
 * @return array{url:string,width:int,height:int}
 */
function vgarco_seo_image( int $attachment_id ): array {
	if ( $attachment_id <= 0 ) {
		return array( 'url' => '', 'width' => 0, 'height' => 0 );
	}
	$src = wp_get_attachment_image_src( $attachment_id, 'vg-1200' );
	return is_array( $src ) ? array( 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] ) : array( 'url' => '', 'width' => 0, 'height' => 0 );
}

/**
 * Địa chỉ chuẩn (canonical) của trang hiện tại; rỗng với trang không nên có (404).
 */
function vgarco_seo_canonical(): string {
	return vgarco_seo_absolute( vgarco_seo_canonical_raw() );
}

/** Địa chỉ chuẩn như WordPress trả (chưa chuẩn hóa thành tuyệt đối). */
function vgarco_seo_canonical_raw(): string {
	$paged = (int) get_query_var( 'paged' );

	if ( is_404() ) {
		return '';
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_singular() ) {
		return (string) get_permalink( (int) get_queried_object_id() );
	}
	if ( is_tax() ) {
		$link = get_term_link( get_queried_object() );
		$url  = is_wp_error( $link ) ? '' : (string) $link;
	} elseif ( is_post_type_archive( 'du_an' ) ) {
		$url = (string) get_post_type_archive_link( 'du_an' );
	} elseif ( is_post_type_archive( 'tin_tuc' ) ) {
		$url = (string) get_post_type_archive_link( 'tin_tuc' );
	} else {
		return '';
	}
	return $paged > 1 ? (string) get_pagenum_link( $paged ) : $url;
}

/**
 * Toàn bộ thẻ cần in cho trang hiện tại.
 *
 * @return array Xem vgarco_seo_tags().
 */
function vgarco_seo_context(): array {
	$id          = (int) get_queried_object_id();
	$locale      = str_starts_with( (string) determine_locale(), 'de' ) ? 'de_DE' : 'vi_VN';
	$description = '';
	$image_id    = 0;
	$type        = 'website';
	$published   = '';

	if ( is_singular( 'du_an' ) ) {
		$description = trim( (string) get_field( 'mo_ta_seo', $id ) ) ?: (string) get_post_field( 'post_content', $id );
		$image_id    = (int) ( get_field( 'anh_dau_trang', $id ) ?: get_field( 'anh_the', $id ) );
	} elseif ( is_singular( 'tin_tuc' ) ) {
		$description = vgarco_excerpt( $id, 40 );
		$image_id    = (int) get_post_thumbnail_id( $id );
		$type        = 'article';
		$published   = (string) get_the_date( 'c', $id );
	} elseif ( is_singular( 'vi_tri' ) ) {
		$description = trim( (string) get_field( 'tom_tat', $id ) ) ?: (string) get_field( 'mo_ta_chung', $id );
	} elseif ( is_singular() ) {
		$description = vgarco_excerpt( $id, 40 );
	}

	if ( '' === trim( wp_strip_all_tags( $description ) ) ) {
		$description = vgarco_seo_default_description();
	}
	if ( $image_id <= 0 ) {
		$image_id = (int) vgarco_option( 'anh_hero_mac_dinh' );
	}
	$image = vgarco_seo_image( $image_id );

	$alts  = array();
	$langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
	if ( is_array( $langs ) ) {
		foreach ( array_keys( $langs ) as $code ) {
			$alts[] = 'de' === $code ? 'de_DE' : 'vi_VN';
		}
	}

	return array(
		'description' => $description,
		'canonical'   => vgarco_seo_canonical(),
		'title'       => wp_get_document_title(),
		'type'        => $type,
		'image'       => $image['url'],
		'image_width' => $image['width'],
		'image_height' => $image['height'],
		'image_alt'   => '' !== $image['url'] ? ( is_singular() ? get_the_title( $id ) : 'VGARCO' ) : '',
		'locale'      => $locale,
		'alternates'  => array_values( array_unique( $alts ) ),
		'site_name'   => 'VGARCO',
		'published'   => $published,
	);
}

/** Dữ liệu tổ chức lấy từ trang Cấu hình. */
function vgarco_seo_org_data(): array {
	return array(
		'name'    => (string) vgarco_option( 'phap_nhan.ten' ),
		'url'     => vgarco_seo_absolute( home_url( '/' ) ),
		'logo'    => vgarco_seo_absolute( get_theme_file_uri( 'assets/images/logo.svg' ) ),
		'phone'   => (string) vgarco_option( 'lien_he.dien_thoai_tel' ),
		'email'   => (string) vgarco_option( 'lien_he.email_chung' ),
		'street'  => (string) vgarco_option( 'phap_nhan.dia_chi_van_phong' ),
		'country' => 'VN',
		'same_as' => array( (string) vgarco_option( 'mang_xa_hoi.zalo' ), (string) vgarco_option( 'mang_xa_hoi.facebook' ) ),
	);
}

/* -------------------------------------------------------------------------
 * Đầu ra
 * ---------------------------------------------------------------------- */

add_action(
	'wp_head',
	static function (): void {
		echo vgarco_seo_tags( vgarco_seo_context() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã thoát trong vgarco_seo_tags().

		if ( is_front_page() ) {
			echo vgarco_seo_jsonld( vgarco_seo_org( vgarco_seo_org_data() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON đã mã hóa an toàn.
		}

		if ( is_singular( 'vi_tri' ) ) {
			$job = vgarco_job( (int) get_queried_object_id() );
			echo vgarco_seo_jsonld( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON đã mã hóa an toàn.
				vgarco_seo_job(
					array(
						'open'             => $job['open'],
						'title'            => $job['title'],
						'url'              => vgarco_seo_absolute( $job['url'] ),
						'description_html' => vgarco_seo_job_html(
							$job['summary'] ?: $job['description'],
							array(
								array( __( 'Công việc chính', 'vgarco' ), $job['responsibilities'] ),
								array( __( 'Yêu cầu', 'vgarco' ), $job['requirements'] ),
								array( __( 'Quyền lợi', 'vgarco' ), $job['benefits'] ),
							)
						),
						'posted'           => (string) get_the_date( 'Y-m-d', $job['id'] ),
						'deadline'         => $job['deadline'],
						'type'             => $job['type'],
						'location'         => $job['location']['name'],
						'org'              => vgarco_seo_org_data(),
					)
				)
			);
		}
	},
	1
);

// BreadcrumbList nằm cuối trang: đường dẫn vị trí được dựng trong mẫu (vgarco_breadcrumb) sau khi <head> đã in xong.
add_action(
	'wp_footer',
	static function (): void {
		$trail = vgarco_breadcrumb_trail();
		if ( $trail ) {
			echo vgarco_seo_jsonld( vgarco_seo_breadcrumbs( array_map( static fn( array $c ): array => isset( $c[1] ) ? array( $c[0], vgarco_seo_absolute( (string) $c[1] ) ) : array( $c[0] ), $trail ), vgarco_seo_canonical() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON đã mã hóa an toàn.
		}
	},
	1
);

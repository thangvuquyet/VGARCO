<?php

defined( 'ABSPATH' ) || exit;

require_once get_theme_file_path( 'inc/seo-lib.php' );

add_filter(
	'document_title_separator',
	static fn(): string => '—'
);

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

		$title = trim( (string) ( $parts['title'] ?? '' ) );
		$out   = array( 'title' => $title );
		if ( '' !== $section && $section !== $title ) {
			$out['section'] = $section;
		}
		if ( isset( $parts['page'] ) ) {
			$out['page'] = $parts['page'];
		}
		$out['site'] = $site;
		return $out;
	}
);

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
			$robots['nofollow'] = 'production' !== wp_get_environment_type();
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
);

remove_action( 'wp_head', 'rel_canonical' );

function vgarco_seo_absolute( string $url ): string {
	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) . '://' . (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return str_starts_with( $base, '://' ) ? $url : $base . $url;
	}
	return $url;
}

function vgarco_seo_default_description(): string {
	return __( 'Tư vấn thiết kế, giám sát và quản lý dự án xây dựng: công trình ngoại giao, quân sự, giáo dục, văn hóa, y tế và hạ tầng tại Việt Nam, Lào và CHLB Đức.', 'vgarco' );
}

function vgarco_seo_image( int $attachment_id ): array {
	if ( $attachment_id > 0 ) {
		foreach ( array( 'vg-1200', 'full' ) as $size ) {
			$src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( is_array( $src ) && vgarco_seo_share_format_ok( (string) $src[0] ) ) {
				return array( 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
			}
		}
	}
	return array( 'url' => '', 'width' => 0, 'height' => 0 );
}

function vgarco_seo_default_image( string $locale ): array {
	return array(
		'url'    => vgarco_seo_absolute( get_theme_file_uri( 'assets/images/' . vgarco_share_image_file( $locale ) ) ),
		'width'  => 1200,
		'height' => 630,
	);
}

function vgarco_seo_canonical(): string {
	return vgarco_seo_absolute( vgarco_seo_canonical_raw() );
}

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
	$alt   = is_singular() ? get_the_title( $id ) : 'VGARCO';
	if ( '' === $image['url'] ) {
		$image = vgarco_seo_default_image( $locale );
		$alt   = 'VGARCO';
	}

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
		'image_alt'   => $alt,
		'locale'      => $locale,
		'alternates'  => array_values( array_unique( $alts ) ),
		'site_name'   => 'VGARCO',
		'published'   => $published,
	);
}

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

add_filter(
	'get_site_icon_url',
	static fn( $url, $size = 512 ): string => (int) get_option( 'site_icon' ) > 0 ? (string) $url : get_theme_file_uri( 'assets/images/' . vgarco_site_icon_file( (int) $size ) ),
	10,
	2
);

add_action(
	'wp_head',
	static function (): void {
		if ( (int) get_option( 'site_icon' ) <= 0 ) {
			printf( '<link rel="icon" href="%s" type="image/svg+xml" sizes="any">' . "\n", esc_url( get_theme_file_uri( 'assets/images/favicon.svg' ) ) );
		}
	},
	98
);

add_action(
	'wp_head',
	static function (): void {
		echo vgarco_seo_tags( vgarco_seo_context() );

		if ( is_front_page() ) {
			echo vgarco_seo_jsonld( vgarco_seo_org( vgarco_seo_org_data() ) );
		}

		if ( is_singular( 'vi_tri' ) ) {
			$job = vgarco_job( (int) get_queried_object_id() );
			echo vgarco_seo_jsonld(
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

add_action(
	'wp_footer',
	static function (): void {
		$trail = vgarco_breadcrumb_trail();
		if ( $trail ) {
			echo vgarco_seo_jsonld( vgarco_seo_breadcrumbs( array_map( static fn( array $c ): array => isset( $c[1] ) ? array( $c[0], vgarco_seo_absolute( (string) $c[1] ) ) : array( $c[0] ), $trail ), vgarco_seo_canonical() ) );
		}
	},
	1
);

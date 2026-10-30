<?php

defined( 'ABSPATH' ) || exit;

function vgarco_page_id( string $key ): int {
	static $cache = array();

	if ( ! array_key_exists( $key, $cache ) ) {
		$ids = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => '_wp_page_template',
				'meta_value'       => "templates/{$key}.php",
				'suppress_filters' => false,
			)
		);
		$cache[ $key ] = $ids ? (int) $ids[0] : 0;
	}
	return $cache[ $key ];
}

function vgarco_page_url( string $key ): string {
	static $cache = array();

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$fallbacks = array(
		'about'       => '/gioi-thieu/',
		'leadership'  => '/gioi-thieu/ban-lanh-dao/',
		'departments' => '/gioi-thieu/phong-ban/',
		'services'    => '/linh-vuc-hoat-dong/',
		'careers'     => '/tuyen-dung/',
		'apply'       => '/ung-tuyen/',
		'contact'     => '/lien-he/',
		'privacy'     => '/chinh-sach-bao-ve-du-lieu/',
	);

	$id            = vgarco_page_id( $key );
	$cache[ $key ] = $id ? (string) get_permalink( $id ) : home_url( $fallbacks[ $key ] ?? '/' );
	return $cache[ $key ];
}

function vgarco_heading( string $text ): string {
	$safe = wp_kses( trim( $text ), array( 'em' => array() ) );
	return (string) preg_replace( '/\r\n|\r|\n/', '<br>', $safe );
}

function vgarco_kind_label( string $kind ): string {
	return 'cong_trinh' === $kind ? __( 'Ảnh công trình', 'vgarco' ) : __( 'Phối cảnh thiết kế', 'vgarco' );
}

function vgarco_image( int $id, array $args = array() ): string {
	if ( $id <= 0 ) {
		return '';
	}

	$args = array_merge(
		array(
			'size'     => 'vg-1200',
			'sizes'    => '100vw',
			'alt'      => '',
			'class'    => '',
			'priority' => false,
		),
		$args
	);

	$attr = array(
		'alt'      => $args['alt'],
		'sizes'    => $args['sizes'],
		'loading'  => 'lazy',
		'decoding' => 'async',
	);
	if ( ! empty( $args['attr'] ) ) {
		$attr = array_merge( $attr, $args['attr'] );
	}
	if ( '' !== $args['class'] ) {
		$attr['class'] = $args['class'];
	}
	if ( $args['priority'] ) {
		$attr['loading']       = 'eager';
		$attr['fetchpriority'] = 'high';
		$attr['decoding']      = 'sync';
	}

	return (string) wp_get_attachment_image( $id, $args['size'], false, $attr );
}

function vgarco_image_url( int $id, string $size = 'vg-2560' ): string {
	return $id > 0 ? (string) wp_get_attachment_image_url( $id, $size ) : '';
}

function vgarco_project_types( int $post_id ): array {
	$terms = get_the_terms( $post_id, 'loai_cong_trinh' );
	if ( ! is_array( $terms ) ) {
		return array();
	}

	$types = array();
	foreach ( $terms as $term ) {
		$url = get_term_link( $term );
		if ( ! is_wp_error( $url ) ) {
			$types[] = array(
				'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'url'  => $url,
			);
		}
	}
	return $types;
}

function vgarco_project( int $post_id ): array {
	$text = static fn( string $name ): string => trim( (string) get_field( $name, $post_id ) );
	$kind = $text( 'loai_anh_dai_dien' ) ?: 'thiet_ke';

	$published_parties = (bool) get_field( 'cong_bo_chu_dau_tu', $post_id );

	return array(
		'id'          => $post_id,
		'title'       => get_the_title( $post_id ),
		'url'         => (string) get_permalink( $post_id ),
		'code'        => $text( 'ma' ),
		'location'    => $text( 'dia_diem' ),
		'years'       => $text( 'thoi_gian' ),
		'country'     => $text( 'quoc_gia' ),
		'role'        => $text( 'vai_tro' ),
		'scope'       => $text( 'pham_vi' ),
		'owner'       => $published_parties ? $text( 'chu_dau_tu' ) : '',
		'partner'     => $published_parties ? $text( 'doi_tac' ) : '',
		'kind'        => $kind,
		'kind_label'  => vgarco_kind_label( $kind ),
		'types'       => vgarco_project_types( $post_id ),
		'card_image'  => (int) get_field( 'anh_the', $post_id ),
		'hero_image'  => (int) get_field( 'anh_dau_trang', $post_id ),
		'slide_image' => (int) get_field( 'anh_trinh_chieu', $post_id ),
		'menu_order'  => (int) get_post_field( 'menu_order', $post_id ),
	);
}

function vgarco_project_ids( array $args = array() ): array {
	$ids = get_posts(
		array_merge(
			array(
				'post_type'        => 'du_an',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			),
			$args
		)
	);
	return array_map( 'intval', $ids );
}

function vgarco_flagged_project_ids( string $flag, int $limit ): array {
	return vgarco_project_ids(
		array(
			'posts_per_page' => $limit,
			'meta_key'       => $flag,
			'meta_value'     => '1',
		)
	);
}

function vgarco_hero_slides(): array {
	$slides = array();

	foreach ( vgarco_flagged_project_ids( 'noi_bat', 5 ) as $id ) {
		$project = vgarco_project( $id );
		$image   = $project['slide_image'] ?: $project['hero_image'];
		if ( $image <= 0 ) {
			continue;
		}
		$slides[] = array(
			'image_id'   => $image,
			'alt'        => $project['kind_label'] . ' — ' . $project['title'],
			'category'   => $project['types'][0]['name'] ?? '',
			'title'      => $project['title'],
			'url'        => $project['url'],
			'kind_label' => $project['kind_label'],
		);
	}

	if ( ! $slides ) {
		$default = (int) vgarco_option( 'anh_hero_mac_dinh', 0 );
		if ( $default > 0 ) {
			$slides[] = array(
				'image_id'   => $default,
				'alt'        => '',
				'category'   => '',
				'title'      => '',
				'url'        => '',
				'kind_label' => '',
			);
		}
	}

	return $slides;
}

function vgarco_showcase_projects( int $count = 3 ): array {
	$ids = vgarco_flagged_project_ids( 'tieu_bieu', $count );

	if ( count( $ids ) < $count ) {
		$more = vgarco_project_ids( array( 'posts_per_page' => $count + count( $ids ), 'post__not_in' => $ids ) );
		$ids  = array_slice( array_merge( $ids, $more ), 0, $count );
	}

	return array_map( 'vgarco_project', $ids );
}

function vgarco_catalogue( $term = null ): array {
	$args = array();
	if ( $term ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'loai_cong_trinh',
				'field'    => 'term_id',
				'terms'    => array( (int) $term->term_id ),
			),
		);
	}

	$projects = array_map( 'vgarco_project', vgarco_project_ids( $args ) );
	$name     = $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '';

	return array(
		'term'     => $term,
		'projects' => $projects,
		'count'    => count( $projects ),
		'title'    => $term ? $name : __( 'Tất cả dự án', 'vgarco' ),
		'note'     => $term
			? sprintf( __( 'Các dự án thuộc nhóm %s.', 'vgarco' ), mb_strtolower( $name ) )
			: __( 'Các công trình trong hồ sơ VGARCO.', 'vgarco' ),
	);
}

function vgarco_story(): ?array {
	$option     = (array) vgarco_option( 'cau_chuyen', array() );
	$project_id = (int) ( $option['du_an'] ?? 0 );
	$design     = (int) ( $option['anh_thiet_ke'] ?? 0 );

	if ( $project_id <= 0 || $design <= 0 || 'publish' !== get_post_status( $project_id ) ) {
		return null;
	}

	$project = vgarco_project( $project_id );
	$title   = trim( (string) ( $option['tieu_de'] ?? '' ) );

	return array(
		'project' => $project,
		'design'  => $design,
		'built'   => (int) ( $option['anh_thuc_te'] ?? 0 ),
		'title'   => '' !== $title ? $title : $project['title'],
		'text'    => trim( (string) ( $option['mo_ta'] ?? '' ) ),
	);
}

function vgarco_project_detail( int $post_id ): array {
	$project = vgarco_project( $post_id );

	$facts = array_filter(
		array(
			array( __( 'Địa điểm', 'vgarco' ), $project['location'] ),
			array( __( 'Thời gian', 'vgarco' ), $project['years'] ),
			array( __( 'Chủ đầu tư', 'vgarco' ), $project['owner'] ),
			array( __( 'Đối tác', 'vgarco' ), $project['partner'] ),
			array( __( 'Vai trò VGARCO', 'vgarco' ), $project['role'] ),
		),
		static fn( array $row ): bool => '' !== $row[1]
	);

	$gallery = array();
	foreach ( (array) get_field( 'thu_vien', $post_id ) as $row ) {
		$image = (int) ( $row['anh'] ?? 0 );
		if ( $image <= 0 ) {
			continue;
		}
		$kind      = ( $row['loai_anh'] ?? '' ) ?: 'thiet_ke';
		$gallery[] = array(
			'image'      => $image,
			'kind'       => $kind,
			'kind_label' => vgarco_kind_label( $kind ),
			'caption'    => trim( (string) ( $row['chu_thich'] ?? '' ) ),
		);
	}

	$ids   = vgarco_project_ids();
	$pos   = array_search( $post_id, $ids, true );
	$total = count( $ids );
	$next  = ( false !== $pos && $total > 1 ) ? $ids[ ( $pos + 1 ) % $total ] : 0;

	return array(
		'project' => $project,
		'content' => (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $post_id ) ),
		'facts'   => array_values( $facts ),
		'gallery' => $gallery,
		'index'   => false === $pos ? 1 : $pos + 1,
		'total'   => max( $total, 1 ),
		'next'    => $next ? vgarco_project( $next ) : null,
	);
}

function vgarco_home_services( int $count = 3 ): array {
	$ids = get_posts(
		array(
			'post_type'        => 'linh_vuc',
			'post_status'      => 'publish',
			'posts_per_page'   => $count,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'meta_key'         => 'hien_trang_chu',
			'meta_value'       => '1',
			'suppress_filters' => false,
		)
	);

	$services = array();
	foreach ( $ids as $id ) {
		$anchor     = trim( (string) get_field( 'neo', $id ) );
		$services[] = array(
			'title' => get_the_title( $id ),
			'note'  => trim( (string) get_field( 'tom_tat_trang_chu', $id ) ),
			'url'   => vgarco_page_url( 'services' ) . ( '' !== $anchor ? '#' . $anchor : '' ),
		);
	}
	return $services;
}

function vgarco_services(): array {
	$ids = get_posts(
		array(
			'post_type'        => 'linh_vuc',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	$services = array();
	foreach ( $ids as $id ) {
		$anchor = trim( (string) get_field( 'neo', $id ) );
		$ref_id = (int) get_field( 'du_an_tham_khao', $id );
		$ref    = ( $ref_id > 0 && 'publish' === get_post_status( $ref_id ) ) ? vgarco_project( $ref_id ) : null;
		if ( $ref && ( $ref['card_image'] ?: $ref['hero_image'] ) <= 0 ) {
			$ref = null;
		}

		$services[] = array(
			'id'          => $id,
			'title'       => get_the_title( $id ),
			'anchor'      => '' !== $anchor ? $anchor : (string) $id,
			'description' => (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $id ) ),
			'reference'   => $ref,
		);
	}
	return $services;
}

function vgarco_job( int $id ): array {
	$term = static function ( string $taxonomy ) use ( $id ): array {
		$terms = get_the_terms( $id, $taxonomy );
		$first = is_array( $terms ) && $terms ? reset( $terms ) : null;
		return array(
			'slug' => $first ? (string) $first->slug : '',
			'name' => $first ? html_entity_decode( $first->name, ENT_QUOTES, 'UTF-8' ) : '',
		);
	};
	$lines = static fn( string $name ): array => vgarco_split_lines( (string) get_field( $name, $id ) );

	$type     = (string) ( get_field( 'loai_hinh', $id ) ?: 'toan_thoi_gian' );
	$location = $term( 'noi_lam_viec' );
	$dept     = $term( 'phong_ban' );
	$summary  = trim( (string) get_field( 'tom_tat', $id ) );
	$title    = get_the_title( $id );

	return array(
		'id'               => $id,
		'title'            => $title,
		'url'              => (string) get_permalink( $id ),
		'apply_url'        => add_query_arg( 'id', $id, vgarco_page_url( 'apply' ) ),
		'summary'          => $summary,
		'description'      => trim( (string) get_field( 'mo_ta_chung', $id ) ),
		'responsibilities' => $lines( 'cong_viec' ),
		'requirements'     => $lines( 'yeu_cau' ),
		'benefits'         => $lines( 'quyen_loi' ),
		'location'         => $location,
		'department'       => $dept,
		'type'             => $type,
		'type_label'       => vgarco_job_type_label( $type ),
		'deadline'         => trim( (string) get_field( 'han_nop', $id ) ),
		'open'             => vgarco_job_is_open( $id ),
		'search'           => vgarco_fold( implode( ' ', array( $title, $summary, $location['name'], $dept['name'], vgarco_job_type_label( $type ) ) ) ),
	);
}

function vgarco_open_jobs(): array {
	$ids = get_posts(
		array(
			'post_type'        => 'vi_tri',
			'post_status'      => 'publish',
			'posts_per_page'   => 100,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	$jobs = array();
	foreach ( $ids as $id ) {
		$job = vgarco_job( (int) $id );
		if ( $job['open'] ) {
			$jobs[] = $job;
		}
	}
	return $jobs;
}

function vgarco_job_filters( array $jobs ): array {
	$out = array( 'locations' => array(), 'departments' => array() );
	foreach ( $jobs as $job ) {
		if ( '' !== $job['location']['slug'] ) {
			$out['locations'][ $job['location']['slug'] ] = $job['location']['name'];
		}
		if ( '' !== $job['department']['slug'] ) {
			$out['departments'][ $job['department']['slug'] ] = $job['department']['name'];
		}
	}
	return $out;
}

function vgarco_filter_jobs( array $jobs, string $query, string $location, string $department ): array {
	$words = array_filter( preg_split( '/s+/u', vgarco_fold( trim( $query ) ) ) ?: array() );

	return array_values(
		array_filter(
			$jobs,
			static function ( array $job ) use ( $words, $location, $department ): bool {
				if ( '' !== $location && $job['location']['slug'] !== $location ) {
					return false;
				}
				if ( '' !== $department && $job['department']['slug'] !== $department ) {
					return false;
				}
				foreach ( $words as $word ) {
					if ( ! str_contains( $job['search'], $word ) ) {
						return false;
					}
				}
				return true;
			}
		)
	);
}

function vgarco_excerpt( int $post_id, int $words = 24 ): string {
	$excerpt = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
	if ( '' !== $excerpt ) {
		return wp_strip_all_tags( $excerpt );
	}
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) ), $words, '…' );
}

function vgarco_latest_news( int $count = 3 ): array {
	$ids = get_posts(
		array(
			'post_type'        => 'tin_tuc',
			'post_status'      => 'publish',
			'posts_per_page'   => $count,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	return array_map( 'vgarco_news_item', array_map( 'intval', $ids ) );
}

function vgarco_news_item( int $id ): array {
	$terms = get_the_terms( $id, 'chuyen_muc' );
	$term  = is_array( $terms ) && $terms ? reset( $terms ) : null;
	$label = trim( (string) get_field( 'nhan_chu_de', $id ) );
	$name  = $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '';

	return array(
		'id'       => $id,
		'title'    => get_the_title( $id ),
		'url'      => (string) get_permalink( $id ),
		'excerpt'  => vgarco_excerpt( $id ),
		'image_id' => (int) get_post_thumbnail_id( $id ),
		'label'    => '' !== $label ? $label : $name,
		'category' => $name,
		'topic'    => $label,
		'date'     => get_the_date( 'm.Y', $id ),
		'iso'      => get_the_date( 'Y-m-d', $id ),
	);
}

function vgarco_news_categories( int $current_id = 0 ): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'chuyen_muc',
			'hide_empty' => false,
			'orderby'    => 'term_id',
		)
	);
	$out   = array();
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}
		$out[] = array(
			'name'    => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			'url'     => $url,
			'current' => $current_id > 0 && (int) $term->term_id === $current_id,
		);
	}
	return $out;
}

function vgarco_news_article( int $id ): array {
	$ids = array_map(
		'intval',
		get_posts(
			array(
				'post_type'        => 'tin_tuc',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		)
	);
	$pos = array_search( $id, $ids, true );

	return array(
		'item'    => vgarco_news_item( $id ),
		'content' => (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $id ) ),
		'lead'    => trim( (string) get_field( 'doan_dan', $id ) ),
		'caption' => trim( (string) get_field( 'chu_thich_anh', $id ) ),
		'prev'    => ( false !== $pos && $pos > 0 ) ? vgarco_news_item( $ids[ $pos - 1 ] ) : null,
		'next'    => ( false !== $pos && $pos < count( $ids ) - 1 ) ? vgarco_news_item( $ids[ $pos + 1 ] ) : null,
	);
}

<?php

defined( 'ABSPATH' ) || exit;

function vgarco_seed_term_map(): array {
	return array(
		'loai_cong_trinh' => array(
			'ngoai-giao-cong-quyen' => 'Ngoại giao & công quyền',
			'quan-su-an-ninh'       => 'Quân sự & an ninh',
			'giao-duc-dao-tao'      => 'Giáo dục & đào tạo',
			'van-hoa-di-tich'       => 'Văn hóa & di tích',
			'y-te'                  => 'Y tế',
			'cong-nghiep-ha-tang'   => 'Công nghiệp & hạ tầng',
			'nha-o-dan-dung'        => 'Nhà ở & công trình dân dụng',
		),
		'chuyen_muc'      => array(
			'tin-tuc-cong-ty'   => 'Tin tức công ty',
			'thong-tin-bao-chi' => 'Thông tin báo chí',
			'su-kien'           => 'Sự kiện',
		),
	);
}

function vgarco_seed_terms(): void {
	foreach ( vgarco_seed_term_map() as $taxonomy => $terms ) {
		foreach ( $terms as $slug => $name ) {
			if ( ! term_exists( $slug, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
			}
		}
	}
}

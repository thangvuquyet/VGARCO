<?php
/**
 * Dữ liệu mồi cho các phân loại cố định. Chạy một lần khi kích hoạt plugin; chạy lại an toàn.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Các phân loại cố định: taxonomy => [ slug => tên ].
 *
 * Tên và thứ tự theo UC-04 (7 loại công trình) và UC-06 (3 chuyên mục) của đặc tả.
 * Nhóm "Y tế" hiện chưa có dự án nào — vẫn tạo sẵn; hiển thị trong menu hay không do
 * quyết định của chủ đầu tư (mục 3.2 #3).
 *
 * @return array
 */
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

<?php
/**
 * Tìm kiếm dự án (UC-09, mục 8.1 của phương án): chuẩn hóa và xếp hạng. Phần thuần — chỉ cần vgarco_fold() (remove_accents của WordPress),
 * không truy vấn CSDL — nên kiểm thử được bằng dữ liệu giả (tests/php/search.php). Chỉ mục và đường REST nằm ở search.php.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

const VGARCO_SEARCH_MIN_CHARS = 3;
const VGARCO_SEARCH_MAX_CHARS = 100;
const VGARCO_SEARCH_LIMIT     = 10;

/**
 * Chuỗi truy vấn đã chuẩn hóa: bỏ dấu, chữ thường, gộp khoảng trắng, cắt tối đa 100 ký tự.
 * Truy vấn và chỉ mục đi qua cùng một hàm (vgarco_fold) nên gõ có dấu hay không dấu đều khớp.
 */
function vgarco_search_normalize( string $text ): string {
	$text = preg_replace( '/\s+/u', ' ', trim( $text ) ) ?? '';
	return trim( vgarco_fold( mb_substr( $text, 0, VGARCO_SEARCH_MAX_CHARS ) ) );
}

/**
 * Thêm các khóa đã chuẩn hóa vào một mục của chỉ mục (làm một lần khi dựng chỉ mục, không làm mỗi lần tìm).
 *
 * @param array $item title, location, years, types (mảng tên), url, thumb.
 * @return array Mục kèm 'fold_title' và 'fold_all'.
 */
function vgarco_search_prepare( array $item ): array {
	$item['fold_title'] = vgarco_search_normalize( (string) $item['title'] );
	$item['fold_all']   = vgarco_search_normalize(
		implode( ' ', array_merge( array( (string) $item['title'], (string) $item['location'], (string) $item['years'] ), array_map( 'strval', (array) $item['types'] ) ) )
	);
	return $item;
}

/**
 * Tìm trong chỉ mục.
 *
 * - Dưới 3 ký tự: trả rỗng (UC-09 3a; trình duyệt vốn đã không gửi).
 * - Mọi từ trong truy vấn đều phải xuất hiện trong tên, địa điểm, năm hoặc tên loại công trình.
 * - Xếp hạng: tên bắt đầu bằng cả truy vấn → có từ trong tên bắt đầu bằng từ đầu tiên → còn lại; cùng hạng thì giữ thứ tự chỉ mục
 *   (đã theo "Thứ tự" chủ đầu tư đặt, rồi theo tên).
 * - Tối đa 10 kết quả.
 *
 * @param array[] $index Các mục đã qua vgarco_search_prepare(), theo thứ tự hiển thị.
 * @return array[] Mỗi kết quả: title, location, years, url, thumb.
 */
function vgarco_search_rank( array $index, string $query, int $limit = VGARCO_SEARCH_LIMIT ): array {
	$folded = vgarco_search_normalize( $query );
	if ( mb_strlen( $folded ) < VGARCO_SEARCH_MIN_CHARS ) {
		return array();
	}

	$words = explode( ' ', $folded );
	$first = $words[0];

	$scored = array();
	foreach ( array_values( $index ) as $position => $item ) {
		foreach ( $words as $word ) {
			if ( ! str_contains( $item['fold_all'], $word ) ) {
				continue 2;
			}
		}

		$rank = 2;
		if ( str_starts_with( $item['fold_title'], $folded ) ) {
			$rank = 0;
		} else {
			foreach ( explode( ' ', $item['fold_title'] ) as $title_word ) {
				if ( str_starts_with( $title_word, $first ) ) {
					$rank = 1;
					break;
				}
			}
		}
		$scored[] = array( $rank, $position, $item );
	}

	usort(
		$scored,
		static fn( array $a, array $b ): int => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] )
	);

	$results = array();
	foreach ( array_slice( $scored, 0, max( 0, $limit ) ) as $entry ) {
		$item      = $entry[2];
		$results[] = array(
			'title'    => (string) $item['title'],
			'location' => (string) $item['location'],
			'years'    => (string) $item['years'],
			'url'      => (string) $item['url'],
			'thumb'    => (string) $item['thumb'],
		);
	}
	return $results;
}

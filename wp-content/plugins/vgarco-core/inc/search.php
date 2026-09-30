<?php
/**
 * Tìm kiếm dự án (UC-09, mục 8.1): chỉ mục theo ngôn ngữ và đường REST GET /wp-json/vgarco/v1/search.
 * Logic so khớp nằm ở search-lib.php.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chỉ mục của một ngôn ngữ: mọi dự án đã xuất bản, theo thứ tự "Thứ tự" rồi tên. Lưu trong transient (dùng object cache nếu có,
 * ví dụ Redis; không thì bảng options) và bị xóa khi dự án hay loại công trình thay đổi.
 *
 * @return array[]
 */
function vgarco_search_index( string $lang ): array {
	$lang = 'de' === $lang ? 'de' : 'vi';
	$key  = 'vgarco_search_' . $lang;

	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$index = vgarco_with_language(
		$lang,
		static function (): array {
			$ids = get_posts(
				array(
					'post_type'        => 'du_an',
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'orderby'          => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => false,
				)
			);

			$items = array();
			foreach ( $ids as $id ) {
				$image = (int) ( get_field( 'anh_the', $id ) ?: get_field( 'anh_dau_trang', $id ) ?: get_field( 'anh_trinh_chieu', $id ) );
				$terms = get_the_terms( $id, 'loai_cong_trinh' );

				$items[] = vgarco_search_prepare(
					array(
						'title'    => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
						'location' => trim( (string) get_field( 'dia_diem', $id ) ),
						'years'    => trim( (string) get_field( 'thoi_gian', $id ) ),
						'types'    => is_array( $terms ) ? array_map( static fn( $t ): string => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ), $terms ) : array(),
						'url'      => (string) get_permalink( $id ),
						'thumb'    => $image > 0 ? (string) wp_get_attachment_image_url( $image, 'thumbnail' ) : '',
					)
				);
			}
			return $items;
		}
	);

	set_transient( $key, $index, DAY_IN_SECONDS );
	return $index;
}

function vgarco_search_flush(): void {
	delete_transient( 'vgarco_search_vi' );
	delete_transient( 'vgarco_search_de' );
}

// Đổi dự án hay loại công trình là chỉ mục cũ. ACF ghi trường sau save_post nên bắt thêm acf/save_post ở mức ưu tiên sau.
add_action( 'save_post_du_an', 'vgarco_search_flush' );
add_action( 'acf/save_post', 'vgarco_search_flush', 20 );
add_action( 'trashed_post', 'vgarco_search_flush' );
add_action( 'untrashed_post', 'vgarco_search_flush' );
add_action( 'deleted_post', 'vgarco_search_flush' );
add_action( 'set_object_terms', 'vgarco_search_flush' );
add_action(
	'edited_term',
	static function ( $term_id, $tt_id, $taxonomy ): void {
		if ( 'loai_cong_trinh' === $taxonomy ) {
			vgarco_search_flush();
		}
	},
	10,
	3
);
add_action(
	'delete_term',
	static function ( $term, $tt_id, $taxonomy ): void {
		if ( 'loai_cong_trinh' === $taxonomy ) {
			vgarco_search_flush();
		}
	},
	10,
	3
);

add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'vgarco/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'lang' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'vi', 'de' ),
					),
				),
				'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
					$query = mb_substr( (string) $request->get_param( 'q' ), 0, VGARCO_SEARCH_MAX_CHARS );
					$lang  = (string) $request->get_param( 'lang' );

					$response = new WP_REST_Response(
						array(
							'q'       => $query,
							'results' => vgarco_search_rank( vgarco_search_index( $lang ), $query ),
						)
					);
					// Cloudflare cache 5 phút theo đầy đủ chuỗi truy vấn (mục 8.1).
					$response->header( 'Cache-Control', 'public, max-age=300' );
					return $response;
				},
			)
		);
	}
);

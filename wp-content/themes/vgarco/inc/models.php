<?php
/**
 * Lớp dữ liệu cho mẫu trang: đọc bài viết và trường ACF, trả về mảng thuần đã chuẩn hóa.
 *
 * Mẫu trang chỉ gọi các hàm ở đây rồi in ra, không tự truy vấn. Nhờ vậy:
 *   - trường trống được xử lý ở MỘT chỗ (ẩn thay vì để ô trống — UC-04 3a, UC-05 4a);
 *   - kiểm thử được bằng dữ liệu giả (tests/php/render.php).
 *
 * Mọi truy vấn phải có 'suppress_filters' => false: get_posts() mặc định BỎ QUA bộ lọc ngôn ngữ của WPML,
 * dẫn tới trang tiếng Đức hiện dự án tiếng Việt lẫn vào.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Địa chỉ các trang tĩnh
 * ---------------------------------------------------------------------- */

/**
 * Địa chỉ của trang dùng một mẫu (templates/<khóa>.php). Chưa có trang nào dùng mẫu đó thì trả địa chỉ dự phòng
 * theo đường dẫn tiếng Việt — đủ cho local/staging; bản tiếng Đức cần trang thật (WPML dịch địa chỉ).
 *
 * @param string $key about | leadership | departments | services | careers | apply | contact | privacy
 */
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

	$ids = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => "templates/{$key}.php", // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'suppress_filters' => false,
		)
	);

	$cache[ $key ] = $ids ? (string) get_permalink( $ids[0] ) : home_url( $fallbacks[ $key ] ?? '/' );
	return $cache[ $key ];
}

/* -------------------------------------------------------------------------
 * Chuỗi và ảnh
 * ---------------------------------------------------------------------- */

/**
 * Tiêu đề có ngắt dòng chủ ý và cụm nhấn <em>: chỉ cho phép thẻ <em>, đổi xuống dòng thành <br>.
 * Người dịch quyết định chỗ ngắt dòng và cụm nhấn của từng ngôn ngữ ngay trong chuỗi gettext.
 *
 * @param string $text Ví dụ "Mỗi công trình,\nmột <em>dấu ấn.</em>".
 * @return string HTML an toàn để in trực tiếp.
 */
function vgarco_heading( string $text ): string {
	$safe = wp_kses( trim( $text ), array( 'em' => array() ) );
	return (string) preg_replace( '/\r\n|\r|\n/', '<br>', $safe );
}

/**
 * Nhãn loại ảnh: phân biệt phương án thiết kế với công trình thực tế (đặc tả 6.2).
 *
 * @param string $kind thiet_ke | cong_trinh
 */
function vgarco_kind_label( string $kind ): string {
	return 'cong_trinh' === $kind ? __( 'Ảnh công trình', 'vgarco' ) : __( 'Phối cảnh thiết kế', 'vgarco' );
}

/**
 * Thẻ <img> đáp ứng (srcset từ các kích thước vg-*). Ảnh đầu tiên trên màn hình (LCP) dùng $args['priority'].
 *
 * @param int   $id   ID tệp đính kèm; <= 0 trả chuỗi rỗng.
 * @param array $args size, sizes, alt (bắt buộc ghi rõ, kể cả rỗng cho ảnh trang trí), class, priority, attr (thuộc tính thêm).
 */
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

/**
 * Địa chỉ tệp ảnh ở một kích thước (dùng cho phóng to). Không có ảnh thì trả chuỗi rỗng.
 */
function vgarco_image_url( int $id, string $size = 'vg-2560' ): string {
	return $id > 0 ? (string) wp_get_attachment_image_url( $id, $size ) : '';
}

/* -------------------------------------------------------------------------
 * Dự án
 * ---------------------------------------------------------------------- */

/**
 * Các loại công trình của một dự án.
 *
 * @return array[] Mỗi phần tử: name, url.
 */
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

/**
 * Một dự án dưới dạng mảng thuần. Trường trống là chuỗi rỗng — mẫu trang ẩn các trường rỗng.
 * Chủ đầu tư và đối tác chỉ có khi được phép công bố (mục 3.2 #8 của phương án).
 */
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

/**
 * ID các dự án đã xuất bản theo "Thứ tự" chủ đầu tư đặt.
 *
 * @param array $args Ghi đè tham số get_posts (ví dụ meta_key/meta_value, posts_per_page).
 * @return int[]
 */
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

/**
 * Các dự án được đánh dấu theo một cờ ("noi_bat" hoặc "tieu_bieu").
 *
 * @return int[]
 */
function vgarco_flagged_project_ids( string $flag, int $limit ): array {
	return vgarco_project_ids(
		array(
			'posts_per_page' => $limit,
			'meta_key'       => $flag, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
}

/**
 * Các trang trình chiếu ở đầu trang chủ: tối đa 5 dự án nổi bật (yêu cầu 1).
 * Chưa có dự án nào nổi bật thì dùng ảnh mặc định trong trang cấu hình, không kèm chú thích (UC-01 3c).
 *
 * @return array[] Mỗi phần tử: image_id, alt, category, title, url, kind_label.
 */
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

/**
 * Ba dự án tiêu biểu ở trang chủ: những dự án được đánh dấu "tiêu biểu"; thiếu thì lấy tiếp theo thứ tự.
 *
 * @return array[] Dự án (xem vgarco_project).
 */
function vgarco_showcase_projects( int $count = 3 ): array {
	$ids = vgarco_flagged_project_ids( 'tieu_bieu', $count );

	if ( count( $ids ) < $count ) {
		$more = vgarco_project_ids( array( 'posts_per_page' => $count + count( $ids ), 'post__not_in' => $ids ) );
		$ids  = array_slice( array_merge( $ids, $more ), 0, $count );
	}

	return array_map( 'vgarco_project', $ids );
}

/**
 * Danh mục dự án (UC-04): tất cả dự án, hoặc chỉ các dự án của một loại công trình.
 *
 * @param object|null $term Thuật ngữ của loai_cong_trinh; null = tất cả.
 * @return array title, note, count, projects (xem vgarco_project), term.
 */
function vgarco_catalogue( $term = null ): array {
	$args = array();
	if ( $term ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
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
			/* translators: %s: tên loại công trình, viết thường. */
			? sprintf( __( 'Các dự án thuộc nhóm %s.', 'vgarco' ), mb_strtolower( $name ) )
			: __( 'Các công trình trong hồ sơ VGARCO.', 'vgarco' ),
	);
}

/**
 * Khối "Câu chuyện một công trình" (UC-04 6a/6b): một dự án chọn lọc, chuyển giữa ảnh phối cảnh và ảnh công trình.
 * Chưa cấu hình dự án hoặc thiếu ảnh phối cảnh thì không hiển thị. Thiếu ảnh công trình thì ẩn nút chuyển.
 *
 * @return array|null project, design (ID ảnh), built (ID ảnh hoặc 0), title, text.
 */
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

/**
 * Chi tiết một dự án (UC-05).
 *
 * @return array project, content (HTML), facts [[nhãn, giá trị]] đã bỏ dòng trống, gallery, index, total, next.
 */
function vgarco_project_detail( int $post_id ): array {
	$project = vgarco_project( $post_id );

	// Thông tin dự án: dòng nào trống thì ẩn, không để ô trống (UC-05 4a).
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

	// Thư viện: mỗi dòng có ảnh, loại ảnh và chú thích theo từng ngôn ngữ (yêu cầu 27).
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

	// Vị trí trong danh mục ("01 / 19") và dự án tiếp theo, quay vòng về dự án đầu (UC-05 8a).
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

/* -------------------------------------------------------------------------
 * Lĩnh vực hoạt động
 * ---------------------------------------------------------------------- */

/**
 * Lĩnh vực hiển thị ở trang chủ (tối đa $count, theo thứ tự).
 *
 * @return array[] Mỗi phần tử: title, note, url (neo trên trang Lĩnh vực hoạt động).
 */
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
			'meta_key'         => 'hien_trang_chu', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
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

/* -------------------------------------------------------------------------
 * Tin tức
 * ---------------------------------------------------------------------- */

/**
 * Đoạn tóm tắt của bài: dùng ô "Trích dẫn" nếu có, không thì cắt từ nội dung.
 */
function vgarco_excerpt( int $post_id, int $words = 24 ): string {
	$excerpt = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
	if ( '' !== $excerpt ) {
		return wp_strip_all_tags( $excerpt );
	}
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) ), $words, '…' );
}

/**
 * Tin mới nhất, kèm chuyên mục đầu tiên.
 *
 * @return array[] Mỗi phần tử: id, title, url, excerpt, image_id, label, category, date.
 */
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

	$news = array();
	foreach ( $ids as $id ) {
		$terms  = get_the_terms( $id, 'chuyen_muc' );
		$term   = is_array( $terms ) && $terms ? reset( $terms ) : null;
		$label  = trim( (string) get_field( 'nhan_chu_de', $id ) );
		$news[] = array(
			'id'       => (int) $id,
			'title'    => get_the_title( $id ),
			'url'      => (string) get_permalink( $id ),
			'excerpt'  => vgarco_excerpt( (int) $id ),
			'image_id' => (int) get_post_thumbnail_id( $id ),
			'label'    => '' !== $label ? $label : ( $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '' ),
			'category' => $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '',
			'date'     => get_the_date( 'm.Y', $id ),
		);
	}
	return $news;
}

<?php
/**
 * Dữ liệu giả và hàm giả của WordPress cho tests/php/render.php.
 * Dữ liệu lấy từ hồ sơ năng lực thật (tên dự án, địa điểm, năm, vai trò) để bố cục được thử với độ dài chữ thật.
 *
 * Ảnh: tệp trong bản thiết kế HTML, phục vụ dưới /img/ bởi tests/serve.mjs (chỉ dùng khi phát triển).
 */

$GLOBALS['fx'] = array(
	'images'  => array(),
	'posts'   => array(),
	'fields'  => array(),
	'terms'   => array(),
	'options' => array(),
	'urls'    => array(),
);

// VG_FX_EMPTY=1: website mới cài, chưa có nội dung nào — để thử các trạng thái rỗng (khối tự ẩn, không lỗi).
$fx_empty = (bool) getenv( 'VG_FX_EMPTY' );

$img_dir = getenv( 'VG_IMG_DIR' ) ?: 'C:/Users/ThangVq/OneDrive - Mobifone/Documents/ChatGPT/Project1/vgarco-preview/assets';

/** Đăng ký một ảnh giả, trả về ID. Kích thước đọc từ tệp thật nếu có. */
function fx_image( string $file ): int {
	global $img_dir;
	static $next = 900;
	$size = is_readable( "{$img_dir}/{$file}" ) ? @getimagesize( "{$img_dir}/{$file}" ) : false;
	$GLOBALS['fx']['images'][ ++$next ] = array( $file, $size[0] ?? 1600, $size[1] ?? 1000 );
	return $next;
}

/** Đăng ký một bài viết giả. */
function fx_post( int $id, string $type, string $title, array $fields = array(), array $extra = array() ): void {
	$GLOBALS['fx']['posts'][ $id ]  = array_merge(
		array(
			'type'       => $type,
			'title'      => $title,
			'menu_order' => $id,
			'excerpt'    => '',
			'content'    => '',
			'date'       => '2026-09-01',
			'thumb'      => 0,
			'url'        => '/' . $type . '/' . $id . '/',
		),
		$extra
	);
	$GLOBALS['fx']['fields'][ $id ] = $fields;
}

$types = array(
	'giao-duc'   => 'Giáo dục & đào tạo',
	'quoc-phong' => 'Quân sự & an ninh',
	'ngoai-giao' => 'Ngoại giao & công quyền',
	'van-hoa'    => 'Văn hóa & di tích',
	'ha-tang'    => 'Công nghiệp & hạ tầng',
	'nha-o'      => 'Nhà ở & công trình dân dụng',
	'y-te'       => 'Y tế', // chưa có dự án nào: thử trạng thái danh mục rỗng (UC-04 5b)
);

/* --- Dự án: 19 dự án trong hồ sơ (tests/php/data/projects.json, lấy từ bản thiết kế HTML) ---
 * Bảy dự án đầu giữ ID và cờ nổi bật/tiêu biểu mà các phép kiểm tra trang chủ dựa vào.
 * VG_FX_ORDER=proto: sắp theo thứ tự của bản thiết kế (để so sánh bố cục danh mục với bản thiết kế). */
$category_slug = array(
	'diplomatic'     => 'ngoai-giao',
	'security'       => 'quoc-phong',
	'education'      => 'giao-duc',
	'culture'        => 'van-hoa',
	'infrastructure' => 'ha-tang',
	'residential'    => 'nha-o',
);
// key => [ID, ảnh đại diện, loại ảnh, nổi bật, tiêu biểu]
$pinned = array(
	'ambassador'    => array( 101, 'project-images/ambassador-05.jpg', 'thiet_ke', true, false ),
	'savannakhet'   => array( 102, 'project-images/savannakhet-pctt.jpg', 'thiet_ke', true, false ),
	'specialforces' => array( 103, 'project-images/specialforces-06.jpg', 'thiet_ke', true, false ),
	'tower'         => array( 104, 'tower.jpg', 'thiet_ke', false, true ),
	'academy'       => array( 105, 'academy.png', 'thiet_ke', false, true ),
	'museum'        => array( 106, 'museum-built.jpg', 'cong_trinh', false, true ),
	'villa'         => array( 107, 'german-villa.jpg', 'thiet_ke', false, false ),
);
$proto_order = (bool) getenv( 'VG_FX_ORDER' );
$next_id     = 108;

foreach ( $fx_empty ? array() : json_decode( (string) file_get_contents( __DIR__ . '/data/projects.json' ), true ) as $row ) {
	$pin      = $pinned[ $row['key'] ] ?? null;
	$id       = $pin ? $pin[0] : $next_id++;
	$image    = fx_image( $pin ? $pin[1] : $row['image'] );
	$kind     = $pin ? $pin[2] : $row['kind'];
	$featured = $pin ? $pin[3] : false;
	$showcase = $pin ? $pin[4] : false;

	// Thư viện = các ảnh ngoài ảnh đầu trang. Bản thiết kế bỏ ảnh đầu của mảng gallery vì nó là ảnh bìa.
	$library = array();
	foreach ( array_slice( $row['gallery'], 1 ) as $i => $file ) {
		$label     = $row['labels'][ $i + 1 ] ?? 'Phối cảnh thiết kế';
		$library[] = array(
			'anh'       => fx_image( $file ),
			'loai_anh'  => str_starts_with( $label, 'Ảnh công trình' ) || str_starts_with( $label, 'Ảnh thi công' ) ? 'cong_trinh' : 'thiet_ke',
			'chu_thich' => $label,
		);
	}

	fx_post(
		$id,
		'du_an',
		$row['title'],
		array(
			'dia_diem'           => $row['location'],
			'thoi_gian'          => $row['year'],
			'chu_dau_tu'         => $row['owner'],
			'doi_tac'            => $row['partner'],
			'vai_tro'            => $row['role'],
			'pham_vi'            => $row['role'],
			'loai_anh_dai_dien'  => $kind,
			'anh_the'            => $image,
			'anh_dau_trang'      => $image,
			'anh_trinh_chieu'    => $featured ? $image : 0,
			'noi_bat'            => $featured ? '1' : '0',
			'tieu_bieu'          => $showcase ? '1' : '0',
			'cong_bo_chu_dau_tu' => ( ( $row['owner'] || $row['partner'] ) && ! getenv( 'VG_FX_HIDE_PARTIES' ) ) ? '1' : '0',
			'thu_vien'           => $library,
		),
		array(
			'url'        => '/du-an/' . $id . '/',
			'menu_order' => $proto_order ? $row['order'] : $id,
			'content'    => '<p>' . htmlspecialchars( $row['description'], ENT_QUOTES, 'UTF-8' ) . '</p>',
		)
	);
	$GLOBALS['fx']['terms'][ $id ] = array( 'loai_cong_trinh' => array( $category_slug[ $row['category'] ] ) );
}

/* --- Lĩnh vực --- */
foreach ( $fx_empty ? array() : array(
	array( 201, 'Thiết kế kiến trúc', 'thiet-ke-kien-truc', 'Ý tưởng · Hồ sơ' ),
	array( 202, 'Quản lý dự án', 'quan-ly-du-an', 'Tiến độ · Chi phí' ),
	array( 203, 'Giám sát thi công', 'giam-sat-thi-cong', 'Chất lượng · Hiện trường' ),
) as $s ) {
	fx_post( $s[0], 'linh_vuc', $s[1], array( 'neo' => $s[2], 'tom_tat_trang_chu' => $s[3], 'hien_trang_chu' => '1' ) );
}

/* --- Tin tức --- */
foreach ( $fx_empty ? array() : array(
	array( 301, 'Từ ý tưởng đến một công trình có thể vận hành', 'Một quy trình rõ ràng giúp kiến trúc, kỹ thuật và triển khai cùng đi theo một mục tiêu.', 'tower.jpg', 'Góc nhìn chuyên môn', 'tin-tuc' ),
	array( 302, 'Dấu ấn hợp tác trong các dự án quốc tế', 'Thông tin về hoạt động hợp tác và các dự án của VGARCO.', 'museum-render.jpg', 'Thông tin báo chí', 'bao-chi' ),
	array( 303, 'Chia sẻ chuyên môn cùng đội ngũ VGARCO', 'Các hoạt động chia sẻ chuyên môn và kết nối cùng đội ngũ VGARCO.', 'german-villa.jpg', 'Sự kiện', 'su-kien' ),
) as $n ) {
	fx_post( $n[0], 'tin_tuc', $n[1], array( 'nhan_chu_de' => $n[4] ), array( 'excerpt' => $n[2], 'thumb' => fx_image( $n[3] ), 'date' => '2026-09-0' . ( $n[0] - 300 ), 'url' => '/tin-tuc/' . $n[0] . '/' ) );
	$GLOBALS['fx']['terms'][ $n[0] ] = array( 'chuyen_muc' => array( $n[5] ) );
}

/* --- Trang tĩnh --- */
// VG_FX_PAGE_FULL=1: biên tập viên đã chọn ảnh (bìa, cách làm việc, sự ghi nhận). VG_FX_PAGE_OVERRIDE=1: đã nhập chữ ghi đè bản mặc định.
$page_full     = (bool) getenv( 'VG_FX_PAGE_FULL' );
$page_override = (bool) getenv( 'VG_FX_PAGE_OVERRIDE' );

fx_post(
	501,
	'page',
	'Về công ty',
	array_merge(
		$page_full ? array(
			'bia_anh'            => fx_image( 'school.jpg' ),
			'bia_chu_thich_trai' => 'Trường THCS chất lượng cao Bắc Từ Liêm',
			'bia_chu_thich_phai' => 'Hà Nội, Việt Nam / Ảnh công trình',
			'cach_lam_anh'       => fx_image( 'german-villa.jpg' ),
			'cach_lam_chu_thich' => 'Niedernhausen, Đức / Phối cảnh thiết kế',
			'ghi_nhan_anh'       => fx_image( 'award.jpg' ),
			'ghi_nhan_anh_alt'   => 'Bằng khen trong tư liệu dự án Bảo tàng Quân đội nhân dân Lào',
			'ghi_nhan_tieu_de'   => "Niềm tin từ\nnhững công trình.",
			'ghi_nhan_mo_ta'     => 'Bằng khen của Tổng cục Chính trị Quân đội nhân dân Lào ghi nhận đóng góp trong dự án Bảo tàng Quân đội nhân dân Lào.',
			'ghi_nhan_chu_thich' => 'Tư liệu mang tên 3SEVEN, tên gọi trước đây của VGARCO.',
			'ghi_nhan_bai_viet'  => getenv( 'VG_FX_NO_AWARD_POST' ) ? 0 : 301,
		) : array(),
		$page_override ? array(
			// Nội dung độc hại: mọi trường phải được lọc hoặc thoát trước khi in.
			'hero_tieu_de'   => "Tiêu đề do biên tập viên nhập.<img src=x onerror=alert(1)>\n<em>Ghi đè bản mặc định.</em>",
			'hero_mo_ta_1'   => '<b>đậm</b><script>alert(1)</script>Dòng ngắn',
			'hanh_trinh_moc' => array(
				array( 'nam' => '2013', 'dong_phu' => '19.08.2013', 'tieu_de' => 'Mốc duy nhất', 'mo_ta' => 'Chỉ nhập một mốc.' ),
				array( 'nam' => '', 'dong_phu' => '', 'tieu_de' => '', 'mo_ta' => '' ), // dòng trống hoàn toàn: phải bị bỏ
			),
			'pham_vi_quoc_gia' => "Việt Nam\n\nLào\n  \nĐức\nÁo",
		) : array()
	),
	array( 'url' => '/gioi-thieu/' )
);

/* --- Cấu hình (ACF Options) --- */
$GLOBALS['fx']['options'] = $fx_empty ? array() : array(
	'doi_tac'           => array(
		array( 'ten' => 'AVITYCO', 'viet_tat' => 'AV', 'mo_ta' => 'Kiến trúc & Xây dựng' ),
		array( 'ten' => 'Kiến trúc Việt', 'viet_tat' => 'KV', 'mo_ta' => 'Tư vấn kiến trúc' ),
		array( 'ten' => 'FBN Studio', 'viet_tat' => 'FBN', 'mo_ta' => 'Ferri Nguyen GbR' ),
		array( 'ten' => 'Quản lý bay miền Bắc', 'viet_tat' => 'QLB', 'mo_ta' => 'Hạ tầng hàng không' ),
		array( 'ten' => 'Học viện Kỹ thuật & Công nghệ An ninh', 'viet_tat' => 'HA', 'mo_ta' => 'Đào tạo chuyên ngành' ),
	),
	'anh_hero_mac_dinh' => fx_image( 'project-images/ambassador-05.jpg' ),
	'cau_chuyen'       => array(
		'du_an'        => 106,
		'tieu_de'      => "Giữ lại ký ức.
<em>Viết tiếp giá trị.</em>",
		'mo_ta'        => 'Cải tạo Bảo tàng Lịch sử Quân đội nhân dân Lào. Một hành trình từ phương án thiết kế đến hình hài công trình thực tế.',
		'anh_thiet_ke' => fx_image( 'museum-render.jpg' ),
		'anh_thuc_te'  => getenv( 'VG_FX_NO_BUILT' ) ? 0 : fx_image( 'museum-built.jpg' ), // VG_FX_NO_BUILT=1: chưa có ảnh công trình (UC-04 6b)
	),
);

$GLOBALS['fx']['term_names'] = $types + array(
	'tin-tuc'  => 'Tin tức công ty',
	'bao-chi'  => 'Thông tin báo chí',
	'su-kien'  => 'Sự kiện',
);

/* ========== Hàm giả ========== */

function get_field( $name, $post_id = false ) {
	if ( 'option' === $post_id ) {
		return $GLOBALS['fx']['options'][ $name ] ?? null;
	}
	return $GLOBALS['fx']['fields'][ $post_id ][ $name ] ?? null;
}

function get_post_status( $id ) {
	if ( getenv( 'VG_FX_STORY_DRAFT' ) && 106 === $id ) {
		return 'draft'; // dự án của khối câu chuyện đã bị gỡ về bản nháp
	}
	return isset( $GLOBALS['fx']['posts'][ $id ] ) ? 'publish' : false;
}

function get_the_title( $id ) {
	return $GLOBALS['fx']['posts'][ $id ]['title'] ?? '';
}

function get_permalink( $id ) {
	return $GLOBALS['fx']['posts'][ $id ]['url'] ?? '/?p=' . $id;
}

function get_post_field( $field, $id ) {
	$p = $GLOBALS['fx']['posts'][ $id ] ?? array();
	return array(
		'menu_order'   => $p['menu_order'] ?? 0,
		'post_excerpt' => $p['excerpt'] ?? '',
		'post_content' => $p['content'] ?? '',
	)[ $field ] ?? '';
}

function get_post_thumbnail_id( $id ) {
	return $GLOBALS['fx']['posts'][ $id ]['thumb'] ?? 0;
}

function get_the_date( $format, $id ) {
	return date( str_replace( 'm.Y', 'm.Y', $format ), strtotime( $GLOBALS['fx']['posts'][ $id ]['date'] ?? 'now' ) );
}

function get_the_terms( $id, $taxonomy ) {
	$slugs = $GLOBALS['fx']['terms'][ $id ][ $taxonomy ] ?? array();
	if ( ! $slugs ) {
		return false;
	}
	return array_map(
		static fn( $slug ) => (object) array(
			'slug'     => $slug,
			'name'     => $GLOBALS['fx']['term_names'][ $slug ] ?? $slug,
			'taxonomy' => $taxonomy,
			'term_id'  => crc32( $slug ),
		),
		$slugs
	);
}

/** get_posts giả: lọc theo kiểu, cờ meta, sắp theo menu_order/ngày, cắt theo posts_per_page. */
function get_posts( $args = array() ) {
	$type  = $args['post_type'] ?? 'post';
	$posts = array_filter( $GLOBALS['fx']['posts'], static fn( $p ) => $p['type'] === $type );

	if ( isset( $args['meta_key'] ) && '_wp_page_template' !== $args['meta_key'] ) {
		$posts = array_filter(
			$posts,
			static fn( $p, $id ) => (string) ( $GLOBALS['fx']['fields'][ $id ][ $args['meta_key'] ] ?? '' ) === (string) $args['meta_value'],
			ARRAY_FILTER_USE_BOTH
		);
	}
	if ( 'page' === $type ) {
		return array(); // trang tĩnh: dùng địa chỉ dự phòng
	}
	if ( ! empty( $args['tax_query'] ) ) {
		foreach ( $args['tax_query'] as $clause ) {
			$posts = array_filter(
				$posts,
				static function ( $p, $id ) use ( $clause ) {
					foreach ( $GLOBALS['fx']['terms'][ $id ][ $clause['taxonomy'] ] ?? array() as $slug ) {
						if ( in_array( crc32( $slug ), array_map( 'intval', $clause['terms'] ), true ) ) {
							return true;
						}
					}
					return false;
				},
				ARRAY_FILTER_USE_BOTH
			);
		}
	}
	if ( ! empty( $args['post__not_in'] ) ) {
		$posts = array_diff_key( $posts, array_flip( $args['post__not_in'] ) );
	}

	$ids = array_keys( $posts );
	if ( 'date' === ( $args['orderby'] ?? '' ) ) {
		usort( $ids, static fn( $a, $b ) => strcmp( $posts[ $b ]['date'], $posts[ $a ]['date'] ) );
	} else {
		usort( $ids, static fn( $a, $b ) => $posts[ $a ]['menu_order'] <=> $posts[ $b ]['menu_order'] );
	}

	$limit = $args['posts_per_page'] ?? -1;
	return $limit > 0 ? array_slice( $ids, 0, $limit ) : $ids;
}

function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
	$img = $GLOBALS['fx']['images'][ $id ] ?? null;
	if ( ! $img ) {
		return '';
	}
	$attr = array_merge( array( 'src' => '/img/' . $img[0], 'width' => $img[1], 'height' => $img[2] ), $attr );
	$html = '';
	foreach ( $attr as $name => $value ) {
		$html .= sprintf( ' %s="%s"', $name, htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ) );
	}
	return "<img{$html}>";
}

function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	$img = $GLOBALS['fx']['images'][ $id ] ?? null;
	return $img ? '/img/' . $img[0] : false;
}

function wp_kses( $text, $allowed ) {
	return strip_tags( $text, '<' . implode( '><', array_keys( $allowed ) ) . '>' );
}
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( $s ) );
}
function strip_shortcodes( $s ) {
	return $s;
}
function wp_trim_words( $text, $n, $more = '…' ) {
	$words = preg_split( '/\s+/u', trim( $text ) );
	return count( $words ) > $n ? implode( ' ', array_slice( $words, 0, $n ) ) . $more : $text;
}

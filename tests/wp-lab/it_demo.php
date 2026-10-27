<?php
require __DIR__ . '/lib.php';

$photos = rtrim( str_replace( '\\', '/', (string) ( getenv( 'VG_DEMO_PHOTOS' ) ?: dirname( REPO ) . '/vgarco-demo-photos' ) ), '/' );
if ( ! is_file( $photos . '/images.csv' ) || ! is_dir( $photos . '/masters' ) ) {
	echo "BỎ QUA: chưa có ảnh minh họa ở {$photos} (chạy tests/wp-lab/demo/prepare-photos.php, hoặc đặt VG_DEMO_PHOTOS)\n";
	exit( 0 );
}

$by_ma = static function ( string $ma ): int {
	$ids = get_posts( array( 'post_type' => 'du_an', 'post_status' => 'any', 'meta_key' => 'ma', 'meta_value' => $ma, 'fields' => 'ids', 'posts_per_page' => 1 ) );
	return (int) ( $ids[0] ?? 0 );
};

foreach ( get_posts( array( 'post_type' => array( 'du_an', 'attachment' ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}

$cli = new VGARCO_CLI_Command();
WP_CLI::$lines = array();
try {
	$cli->import( array( 'projects', REPO . '/content/projects.csv' ), array( 'images' => $photos . '/masters', 'images-csv' => $photos . '/images.csv', 'status' => 'publish' ) );
	$import_error = '';
} catch ( RuntimeException $e ) {
	$import_error = $e->getMessage();
}
same( 'nhập dự án kèm ảnh: không lỗi', '', $import_error );
$projects = get_posts( array( 'post_type' => 'du_an', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
same( 'nhập dự án kèm ảnh: 23 dự án đã công bố', 23, count( $projects ) );
$with_images = array_filter( $projects, static fn( $id ) => (int) get_field( 'anh_the', $id ) > 0 );
same( 'nhập dự án kèm ảnh: 16 dự án có ảnh, 7 dự án để trống có chủ ý', 16, count( $with_images ) );
foreach ( array( 'dai-kiem-soat-khong-luu-dong-hoi', 'hoc-vien-ky-thuat-va-cong-nghe-an-ninh', 'biet-thu-tai-niedernhausen', 'truong-bien-phong-quan-doi-nhan-dan-lao-giai-doan-2', 'truong-thcs-ngo-quyen', 'nha-may-nhua-grand-plastic', 'dai-phat-thanh-truyen-hinh-hua-phan' ) as $ma ) {
	same( "dự án chưa có ảnh riêng ({$ma}) để trống, không dùng ảnh của dự án khác", 0, (int) get_field( 'anh_the', $by_ma( $ma ) ) );
}

foreach ( array( 'tin-tuc-cong-ty' => 'Tin tức công ty', 'thong-tin-bao-chi' => 'Thông tin báo chí', 'su-kien' => 'Sự kiện' ) as $slug => $name ) {
	if ( ! term_exists( $slug, 'chuyen_muc' ) ) {
		wp_insert_term( $name, 'chuyen_muc', array( 'slug' => $slug ) );
	}
}
exec( escapeshellarg( PHP_BINARY ) . ' -c ' . escapeshellarg( LAB . '/php.ini' ) . ' ' . escapeshellarg( __DIR__ . '/it_seed.php' ) . ' 2>&1', $seed_out, $seed_status );
same( 'dữ liệu tin và vị trí: dựng được', 0, $seed_status );

$thumb_of = static fn( string $ma ): int => (int) get_field( 'anh_the', $by_ma( $ma ) );
$news     = array(
	'Từ ý tưởng đến một công trình có thể vận hành'  => array( 'truong-thcs-chat-luong-cao-bac-tu-liem', 'Một quy trình rõ ràng giúp kiến trúc, kỹ thuật và triển khai cùng đi theo một mục tiêu.', 'Góc nhìn chuyên môn' ),
	'Dấu ấn hợp tác trong các dự án quốc tế'         => array( 'bao-tang-lich-su-quan-doi-nhan-dan-lao', 'Thông tin về hoạt động hợp tác và các dự án của VGARCO.', 'Thông tin báo chí' ),
	'Chia sẻ chuyên môn cùng đội ngũ VGARCO'         => array( 'nha-dai-su', 'Các hoạt động chia sẻ chuyên môn và kết nối cùng đội ngũ VGARCO.', 'Sự kiện' ),
);
foreach ( get_posts( array( 'post_type' => 'tin_tuc', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $post ) {
	[ $ma, $excerpt, $label ] = $news[ $post->post_title ] ?? array( 'cong-trinh-dac-cong', '', 'Tin' );
	$image_id                 = $thumb_of( $ma );
	$body                     = '<p>Đoạn mở đầu của bài: nêu vấn đề và phạm vi của nội dung.</p><h2>Một cách tiếp cận thống nhất</h2><p>Nội dung tiếp theo, trình bày cách làm và các lựa chọn đã cân nhắc.</p>'
		. '<blockquote>Giá trị của một giải pháp nằm ở khả năng giữ được logic thiết kế từ ý tưởng đến công trình.</blockquote>'
		. ( $image_id ? '<figure class="wp-block-image">' . wp_get_attachment_image( $image_id, 'large' ) . '<figcaption>Góc nhìn dự án</figcaption></figure>' : '' )
		. '<h2>Từ trao đổi đến triển khai</h2><p>Đoạn kết nối các bước còn lại và các điểm cần theo dõi khi thực hiện.</p>';
	wp_update_post( array( 'ID' => $post->ID, 'post_excerpt' => $excerpt, 'post_content' => $body ) );
	if ( $image_id ) {
		set_post_thumbnail( $post->ID, $image_id );
	}
	update_field( 'nhan_chu_de', $label, $post->ID );
	update_field( 'doan_dan', 'Đoạn dẫn của bài: ' . $post->post_title . '.', $post->ID );
	update_field( 'chu_thich_anh', 'Ảnh: dự án trong hồ sơ năng lực VGARCO', $post->ID );
}

$job_id = wp_insert_post( array( 'post_type' => 'vi_tri', 'post_status' => 'publish', 'post_title' => 'Kỹ sư giám sát', 'post_name' => 'vt-giam-sat' ) );
foreach ( array( 'trang_thai' => 'dang_nhan', 'han_nop' => gmdate( 'Ymd', strtotime( '+45 days' ) ), 'loai_hinh' => 'toan_thoi_gian', 'tom_tat' => 'Giám sát thi công tại hiện trường.', 'mo_ta_chung' => 'Giám sát chất lượng, tiến độ và an toàn thi công.', 'cong_viec' => "Giám sát thi công\nNghiệm thu khối lượng", 'yeu_cau' => "Tốt nghiệp xây dựng\n3 năm kinh nghiệm", 'quyen_loi' => "Bảo hiểm đầy đủ\nThưởng dự án" ) as $k => $v ) {
	update_field( $k, $v, $job_id );
}
wp_set_object_terms( $job_id, 'nghe-an', 'noi_lam_viec' );
wp_set_object_terms( $job_id, 'ket-cau', 'phong_ban' );

$services = array(
	array( 'Tư vấn thiết kế kiến trúc', 'thiet-ke-kien-truc', 'Ý tưởng · Hồ sơ', 'bao-tang-lich-su-quan-doi-nhan-dan-lao', 'Thiết kế quy hoạch, kiến trúc công trình, nội ngoại thất; phát triển phương án từ ý tưởng đến hồ sơ thiết kế.' ),
	array( 'Thiết kế kết cấu', 'thiet-ke-ket-cau', '', 'truong-thcs-chat-luong-cao-bac-tu-liem', 'Giải pháp kết cấu cho công trình dân dụng và công nghiệp, phối hợp với kiến trúc và các bộ môn kỹ thuật.' ),
	array( 'Hạ tầng, cảnh quan & cơ điện', 'ha-tang-canh-quan-co-dien', '', 'du-an-ban-con', 'Thiết kế hạ tầng kỹ thuật, cảnh quan, điện, cấp thoát nước, thông gió và phòng cháy chữa cháy.' ),
	array( 'Lập dự án & quản lý dự án', 'lap-du-an-quan-ly-du-an', 'Tiến độ · Chi phí', 'cong-trinh-dac-cong', 'Báo cáo nghiên cứu khả thi, tổng mức đầu tư, dự toán; hồ sơ mời thầu, đánh giá dự thầu và quản lý dự án.' ),
	array( 'Giám sát thi công', 'giam-sat-thi-cong', 'Chất lượng · Hiện trường', 'nha-dai-su', 'Giám sát xây dựng, hoàn thiện và lắp đặt thiết bị công trình trong quá trình triển khai.' ),
	array( 'Thẩm tra & khảo sát', 'tham-tra-khao-sat', '', 'truong-thcs-tay-tuu', 'Thẩm tra thiết kế và dự toán; khảo sát địa chất công trình, địa chất thủy văn và địa hình.' ),
);
foreach ( $services as $i => [ $title, $anchor, $summary, $ref_ma, $text ] ) {
	$id = wp_insert_post( array( 'post_type' => 'linh_vuc', 'post_status' => 'publish', 'post_title' => $title, 'menu_order' => $i + 1, 'post_content' => '<p>' . esc_html( $text ) . '</p>' ) );
	update_field( 'neo', $anchor, $id );
	update_field( 'tom_tat_trang_chu', $summary, $id );
	update_field( 'hien_trang_chu', '' !== $summary ? '1' : '0', $id );
	update_field( 'du_an_tham_khao', $by_ma( $ref_ma ), $id );
}

$museum = $by_ma( 'bao-tang-lich-su-quan-doi-nhan-dan-lao' );
$kinds  = array( 'thiet_ke' => 0, 'cong_trinh' => 0 );
foreach ( (array) get_field( 'thu_vien', $museum ) as $row ) {
	if ( isset( $kinds[ $row['loai_anh'] ?? '' ] ) && 0 === $kinds[ $row['loai_anh'] ] ) {
		$kinds[ $row['loai_anh'] ] = (int) $row['anh'];
	}
}
update_field(
	'doi_tac',
	array(
		array( 'ten' => 'AVITYCO', 'viet_tat' => 'AV', 'mo_ta' => 'Kiến trúc & Xây dựng' ),
		array( 'ten' => 'Kiến trúc Việt', 'viet_tat' => 'KV', 'mo_ta' => 'Tư vấn kiến trúc' ),
		array( 'ten' => 'FBN Studio', 'viet_tat' => 'FBN', 'mo_ta' => 'Ferri Nguyen GbR' ),
		array( 'ten' => 'Quản lý bay miền Bắc', 'viet_tat' => 'QLB', 'mo_ta' => 'Hạ tầng hàng không' ),
		array( 'ten' => 'Học viện Kỹ thuật & Công nghệ An ninh', 'viet_tat' => 'HA', 'mo_ta' => 'Đào tạo chuyên ngành' ),
	),
	'option'
);
update_field( 'anh_hero_mac_dinh', (int) get_field( 'anh_dau_trang', $by_ma( 'tong-lanh-su-quan-viet-nam-tai-savannakhet' ) ), 'option' );
update_field(
	'cau_chuyen',
	array(
		'du_an'        => $museum,
		'tieu_de'      => "Giữ lại ký ức.\n<em>Viết tiếp giá trị.</em>",
		'mo_ta'        => 'Cải tạo Bảo tàng Lịch sử Quân đội nhân dân Lào. Một hành trình từ phương án thiết kế đến hình hài công trình thực tế.',
		'anh_thiet_ke' => $kinds['thiet_ke'],
		'anh_thuc_te'  => $kinds['cong_trinh'],
	),
	'option'
);

$home = http( 'GET', '/' );
same( 'trang chủ: 200', 200, $home['status'] );
$html = $home['body'];
same( 'trang chủ: 5 ảnh trình chiếu, mỗi ảnh có liên kết dự án (5 dự án nổi bật đều có ảnh)', array( 5, 5 ), array( preg_match_all( '/class="showcase-image[ "]/', $html ), substr_count( $html, 'class="hero-project-link"' ) ) );
same( 'trang chủ: 5 chấm chuyển trang trình chiếu', 5, substr_count( $html, 'class="showcase-dot"' ) );
same( 'trang chủ: 3 lĩnh vực có tóm tắt hiện ở trang chủ', 3, substr_count( $html, 'class="service-num"' ) );
same( 'trang chủ: dải đối tác có 5 đối tác (mỗi đối tác hai lần để chạy vòng)', 10, substr_count( $html, 'class="partner-logo"' ) );
check( 'trang chủ: có bộ chuyển ngôn ngữ VI/DE', str_contains( $html, 'class="language-switch"' ) && str_contains( $html, 'lab_lang=de' ) );
check( 'trang chủ: không có thẻ ảnh với src rỗng', ! str_contains( $html, 'src=""' ) );
$services_page = http( 'GET', '/linh-vuc-hoat-dong/' );
same( 'trang lĩnh vực: 200, đủ 6 lĩnh vực', array( 200, 6 ), array( $services_page['status'], substr_count( $services_page['body'], 'class="service-item"' ) ) );
$catalogue = http( 'GET', '/du-an/' );
same( 'danh mục dự án: 200', 200, $catalogue['status'] );
same( 'danh mục dự án: 23 thẻ dự án', 23, substr_count( $catalogue['body'], 'class="project-card"' ) );
check( 'danh mục dự án: có khối câu chuyện công trình', str_contains( $catalogue['body'], 'Giữ lại ký ức' ) );
$detail = http( 'GET', '/du-an/' . get_post( $museum )->post_name . '/' );
same( 'chi tiết dự án có ảnh: 200, thư viện 6 ảnh', array( 200, 6 ), array( $detail['status'], preg_match_all( '/class="gallery-slide[ "]/', $detail['body'] ) ) );
check( 'chi tiết dự án đã công bố bên liên quan: hiện chủ đầu tư và đối tác theo dữ liệu khách cung cấp', str_contains( $detail['body'], 'Tổng cục Chính trị Quân đội nhân dân Lào' ) && str_contains( $detail['body'], 'Lavinaco 36' ) );
$unpublished = http( 'GET', '/du-an/' . get_post( $by_ma( 'nha-dai-su' ) )->post_name . '/' );
check( 'chi tiết dự án chưa có dữ liệu bên liên quan: không hiện hàng Chủ đầu tư/Đối tác', 200 === $unpublished['status'] && ! str_contains( $unpublished['body'], 'Chủ đầu tư' ) && ! str_contains( $unpublished['body'], 'Đối tác' ) );
$blank = http( 'GET', '/du-an/' . get_post( $by_ma( 'biet-thu-tai-niedernhausen' ) )->post_name . '/' );
same( 'chi tiết dự án chưa có ảnh: 200, không lỗi PHP', array( 200, false ), array( $blank['status'], str_contains( $blank['body'], 'Fatal error' ) || str_contains( $blank['body'], 'Warning:' ) ) );

finish();

<?php
/**
 * Dữ liệu mẫu cho các kiểm thử sau: tin tức, vị trí tuyển dụng (đang mở / quá hạn / đã đóng), nơi làm việc, phòng ban, dịch vụ.
 * Chạy lại được (dọn rồi tạo lại bài tin/vị trí/lĩnh vực).
 */
require __DIR__ . '/lib.php';

foreach ( get_posts( array( 'post_type' => array( 'tin_tuc', 'vi_tri', 'linh_vuc', 'ho_so_ut' ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( 'noi_lam_viec' => array( 'ha-noi' => 'Hà Nội', 'nghe-an' => 'Nghệ An' ), 'phong_ban' => array( 'kien-truc' => 'Kiến trúc', 'ket-cau' => 'Kết cấu' ) ) as $tax => $terms ) {
	foreach ( $terms as $slug => $name ) {
		if ( ! term_exists( $slug, $tax ) ) {
			wp_insert_term( $name, $tax, array( 'slug' => $slug ) );
		}
	}
}

$images = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 3, 'fields' => 'ids' ) );
$news   = array(
	array( 'Từ ý tưởng đến một công trình có thể vận hành', 'tin-tuc-cong-ty' ),
	array( 'Dấu ấn hợp tác trong các dự án quốc tế', 'thong-tin-bao-chi' ),
	array( 'Chia sẻ chuyên môn cùng đội ngũ VGARCO', 'su-kien' ),
);
foreach ( $news as $i => [ $title, $cat ] ) {
	$id = wp_insert_post( array( 'post_type' => 'tin_tuc', 'post_status' => 'publish', 'post_title' => $title, 'post_excerpt' => 'Tóm tắt của ' . $title . '.', 'post_content' => '<p>Đoạn mở đầu.</p><h2>Một cách tiếp cận</h2><p>Nội dung.</p>', 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i + 1 ) . ' days' ) ) ) );
	wp_set_object_terms( $id, $cat, 'chuyen_muc' );
	if ( $images ) {
		set_post_thumbnail( $id, $images[ $i % count( $images ) ] );
	}
	update_field( 'nhan_chu_de', 'Góc nhìn chuyên môn', $id );
}

$jobs = array(
	'kts'    => array( 'Kiến trúc sư thiết kế', 'dang_nhan', gmdate( 'Ymd', strtotime( '+30 days' ) ), 'ha-noi', 'kien-truc', 'toan_thoi_gian' ),
	'ketcau' => array( 'Kỹ sư kết cấu', 'dang_nhan', '', 'nghe-an', 'ket-cau', 'toan_thoi_gian' ),
	'cu'     => array( 'Thực tập sinh quá hạn', 'dang_nhan', gmdate( 'Ymd', strtotime( '-1 day' ) ), 'ha-noi', 'kien-truc', 'thuc_tap' ),
	'dong'   => array( 'Vị trí đã đóng', 'da_dong', '', 'ha-noi', 'ket-cau', 'ban_thoi_gian' ),
);
$ids = array();
foreach ( $jobs as $key => [ $title, $state, $deadline, $loc, $dept, $type ] ) {
	$id = wp_insert_post( array( 'post_type' => 'vi_tri', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'vt-' . $key ) );
	update_field( 'trang_thai', $state, $id );
	update_field( 'han_nop', $deadline, $id );
	update_field( 'loai_hinh', $type, $id );
	update_field( 'tom_tat', 'Tóm tắt ' . $title, $id );
	update_field( 'mo_ta_chung', 'Mô tả chung ' . $title, $id );
	update_field( 'cong_viec', "Thiết kế phương án\nPhối hợp bộ môn", $id );
	update_field( 'yeu_cau', "Tốt nghiệp đại học\n2 năm kinh nghiệm", $id );
	update_field( 'quyen_loi', "Bảo hiểm đầy đủ\nThưởng dự án", $id );
	wp_set_object_terms( $id, $loc, 'noi_lam_viec' );
	wp_set_object_terms( $id, $dept, 'phong_ban' );
	$ids[ $key ] = $id;
}
update_option( 'lab_job_ids', $ids, false );
echo 'tin: ' . count( $news ) . ', vị trí: ' . json_encode( $ids ) . "\n";

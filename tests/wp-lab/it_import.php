<?php
require __DIR__ . '/lib.php';
foreach ( get_posts( array( 'post_type' => array( 'du_an', 'attachment' ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
$tmp = LAB . '/tmp-import';
@mkdir( $tmp . '/imgs/bao-tang', 0777, true );
foreach ( glob( $tmp . '/*.csv' ) as $f ) {
	unlink( $f );
}

$cli  = new VGARCO_CLI_Command();
$csv  = REPO . '/content/projects.csv';
$runs = static function ( array $args, array $assoc ) use ( $cli ): array {
	WP_CLI::$lines = array();
	try {
		$cli->import( $args, $assoc );
		$error = '';
	} catch ( RuntimeException $e ) {
		$error = $e->getMessage();
	}
	return array( 'lines' => WP_CLI::$lines, 'error' => $error );
};
$count = static fn( string $status = 'any' ): int => count( get_posts( array( 'post_type' => 'du_an', 'post_status' => $status, 'posts_per_page' => -1, 'fields' => 'ids' ) ) );

$r = $runs( array( 'projects', $csv ), array( 'dry-run' => true ) );
same( 'dry-run: báo 19 dự án mới', true, str_contains( implode( "\n", $r['lines'] ), 'Xem trước: 19 tạo mới, 0 cập nhật, 0 không đổi' ) );
same( 'dry-run: không có bài nào được tạo', 0, $count() );

$r = $runs( array( 'projects', $csv ), array() );
same( 'nhập: không lỗi', '', $r['error'] );
same( 'nhập: 19 bài, trạng thái nháp', array( 19, 19 ), array( $count(), $count( 'draft' ) ) );
$museum = get_posts( array( 'post_type' => 'du_an', 'post_status' => 'any', 'meta_key' => 'ma', 'meta_value' => 'bao-tang-lich-su-quan-doi-nhan-dan-lao', 'fields' => 'ids' ) );
same( 'nhập: tìm được dự án theo ma', 1, count( $museum ) );
$id = (int) ( $museum[0] ?? 0 );
$p  = get_post( $id );
same( 'nhập: tên, thứ tự, nội dung', array( 'Bảo tàng Lịch sử Quân đội nhân dân Lào', 0, true ), array( $p->post_title, (int) $p->menu_order, str_starts_with( $p->post_content, '<p>Cải tạo Bảo tàng' ) ) );
same( 'nhập: siêu dữ liệu', array( 'Viêng Chăn, Lào', '2019–2020', 'la', 'thiet_ke' ), array( get_field( 'dia_diem', $id ), get_field( 'thoi_gian', $id ), get_field( 'quoc_gia', $id ), get_field( 'loai_anh_dai_dien', $id ) ) );
same( 'nhập: cờ tiêu biểu bật, nổi bật tắt, công bố chủ đầu tư tắt', array( true, false, false ), array( (bool) get_field( 'tieu_bieu', $id ), (bool) get_field( 'noi_bat', $id ), (bool) get_field( 'cong_bo_chu_dau_tu', $id ) ) );
same( 'nhập: loại công trình gắn đúng', array( 'van-hoa-di-tich' ), wp_get_object_terms( $id, 'loai_cong_trinh', array( 'fields' => 'slugs' ) ) );

$r = $runs( array( 'projects', $csv ), array() );
same( 'chạy lại: 0 tạo mới, 0 cập nhật, 19 không đổi', true, str_contains( implode( "\n", $r['lines'] ), 'Xong: 0 tạo mới, 0 cập nhật, 19 không đổi' ) );
same( 'chạy lại: vẫn 19 bài', 19, $count() );

$text = (string) file_get_contents( $csv );
$mod  = str_replace( 'Bảo tàng Lịch sử Quân đội nhân dân Lào;;van-hoa-di-tich', 'Bảo tàng Lịch sử QĐND Lào;;van-hoa-di-tich', $text );
file_put_contents( $tmp . '/mod.csv', $mod );
update_field( 'vai_tro', 'Vai trò nhập tay trong trang quản trị', $id );
$r = $runs( array( 'projects', $tmp . '/mod.csv' ), array() );
same( 'sửa tên: đúng 1 cập nhật', true, str_contains( implode( "\n", $r['lines'] ), '0 tạo mới, 1 cập nhật, 18 không đổi' ) );
same( 'sửa tên: bài đổi tên', 'Bảo tàng Lịch sử QĐND Lào', get_post( $id )->post_title );
same( 'sửa tay + bảng có giá trị: bảng thắng', 'Tư vấn thiết kế cải tạo', get_field( 'vai_tro', $id ) );
$blank = preg_replace( '/;Tư vấn thiết kế cải tạo;/u', ';;', $mod, 1 );
file_put_contents( $tmp . '/blank.csv', $blank );
update_field( 'vai_tro', 'Vai trò nhập tay lần hai', $id );
$runs( array( 'projects', $tmp . '/blank.csv' ), array() );
same( 'ô trống trong bảng KHÔNG xóa dữ liệu nhập tay', 'Vai trò nhập tay lần hai', get_field( 'vai_tro', $id ) );

file_put_contents( $tmp . '/bad.csv', "ma;ten_vi;quoc_gia\nA-sai;;Sao Hỏa\nok;Ten;vn\nok;Trung;vn\n" );
$before = $count();
$r      = $runs( array( 'projects', $tmp . '/bad.csv' ), array() );
$joined = implode( ' ', $r['lines'] );
same( 'tệp lỗi: dừng, báo mọi lỗi, không ghi gì', array( true, $before ), array( str_contains( $joined, 'Dòng 2' ) && str_contains( $joined, 'trùng' ) && str_contains( $r['error'], 'KHÔNG ghi gì' ), $count() ) );

foreach ( array( 'hero', 'the', 'slide', 'pc1', 'pc2' ) as $i => $name ) {
	$im = imagecreatetruecolor( 1600, 900 );
	imagefilledrectangle( $im, 0, 0, 1600, 900, imagecolorallocate( $im, 40 + $i * 30, 90, 120 ) );
	imagestring( $im, 5, 50, 50, "Anh thu {$name}", imagecolorallocate( $im, 255, 255, 255 ) );
	imagejpeg( $im, "{$tmp}/imgs/bao-tang/{$name}.jpg", 85 );
}
copy( "{$tmp}/imgs/bao-tang/the.jpg", "{$tmp}/imgs/bao-tang/the-copy.jpg" );
$ma = 'bao-tang-lich-su-quan-doi-nhan-dan-lao';
file_put_contents( $tmp . '/images.csv', "ma;tep;vai_tro;thu_tu;loai_anh;chu_thich_vi;chu_thich_de\n{$ma};bao-tang/hero.jpg;hero;;;;\n{$ma};bao-tang/the.jpg;the;;;;\n{$ma};bao-tang/slide.jpg;slide;;;;\n{$ma};bao-tang/pc2.jpg;tv;2;cong_trinh;Công trình hoàn thành;Fertiges Gebäude\n{$ma};bao-tang/pc1.jpg;tv;1;thiet_ke;Phối cảnh mặt đứng;Fassade\n{$ma};bao-tang/the-copy.jpg;tv;3;thiet_ke;Ảnh trùng nội dung;\n" );
file_put_contents( $tmp . '/p1.csv', $text );
$opts = array( 'images' => "{$tmp}/imgs", 'images-csv' => "{$tmp}/images.csv" );
$r    = $runs( array( 'projects', $tmp . '/p1.csv' ), $opts );
same( 'ảnh: nhập không lỗi', '', $r['error'] );
$att_ids = static fn(): array => get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ) );
same( 'ảnh: 5 tệp khác nội dung → 5 ảnh (ảnh trùng nội dung dùng lại)', 5, count( $att_ids() ) );
$hero = (int) get_field( 'anh_dau_trang', $id );
check( 'ảnh: ô ảnh đầu trang trỏ tới tệp ảnh thật', $hero > 0 && is_file( get_attached_file( $hero ) ) && wp_attachment_is_image( $hero ) );
$gal = (array) get_field( 'thu_vien', $id );
same( 'ảnh: thư viện 3 ảnh theo thu_tu, chú thích đúng', array( 3, 'Phối cảnh mặt đứng', 'Công trình hoàn thành', 'Ảnh trùng nội dung' ), array( count( $gal ), $gal[0]['chu_thich'] ?? '', $gal[1]['chu_thich'] ?? '', $gal[2]['chu_thich'] ?? '' ) );
same( 'ảnh: ảnh trùng nội dung = cùng một tệp đính kèm với ảnh thẻ', (int) get_field( 'anh_the', $id ), (int) ( $gal[2]['anh'] ?? 0 ) );
same( 'ảnh: văn bản thay thế đặt theo chú thích', 'Phối cảnh mặt đứng', get_post_meta( (int) ( $gal[0]['anh'] ?? 0 ), '_wp_attachment_image_alt', true ) );
$sizes = wp_get_attachment_metadata( $hero )['sizes'] ?? array();
check( 'ảnh: WordPress sinh các kích thước vg-* của theme', isset( $sizes['vg-1200'] ) || isset( $sizes['vg-1600'] ) || isset( $sizes['vg-800'] ) );
$runs( array( 'projects', $tmp . '/p1.csv' ), $opts );
same( 'ảnh: chạy lại không nạp trùng', 5, count( $att_ids() ) );

file_put_contents( $tmp . '/images2.csv', "ma;tep;vai_tro\n{$ma};bao-tang/khong-co.jpg;hero\n" );
$r = $runs( array( 'projects', $tmp . '/p1.csv' ), array( 'images' => "{$tmp}/imgs", 'images-csv' => "{$tmp}/images2.csv" ) );
same( 'ảnh thiếu tệp: báo lỗi và dừng', true, str_contains( $r['error'], 'KHÔNG ghi gì' ) && str_contains( implode( ' ', $r['lines'] ), 'không thấy tệp ảnh' ) );

foreach ( get_posts( array( 'post_type' => 'du_an', 'post_status' => 'draft', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $pid ) {
	wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
}
same( 'sau khi xuất bản: 19 bài công khai', 19, $count( 'publish' ) );

try {
	$cli->report( array( 'translations' ), array( 'lang' => 'de' ) );
	$report_ok = true;
} catch ( Throwable $e ) {
	$report_ok = false;
}
check( 'report translations chạy được khi chưa có WPML', $report_ok );
finish();

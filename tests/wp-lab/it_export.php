<?php
/**
 * Hợp đồng giữa công cụ xuất ảnh (scripts/content/export-images.php) và importer (`wp vgarco import`) trên WordPress thật:
 * ảnh xuất ra + bộ khung images.csv nạp được; bộ khung chú thích trống bị importer chặn; điền chú thích rồi nạp đúng vai trò, thứ tự, văn bản thay thế;
 * không còn GPS/EXIF; chạy lại không nạp trùng.
 */
require __DIR__ . '/lib.php';
require_once REPO . '/tests/php/image-fixtures.php';

$tmp = LAB . '/tmp-export';
$rm  = static function ( string $dir ) use ( &$rm ): void {
	foreach ( glob( $dir . '/*' ) ?: array() as $f ) {
		is_dir( $f ) ? $rm( $f ) : unlink( $f );
	}
	@rmdir( $dir );
};
$rm( $tmp );
mkdir( "$tmp/in", 0777, true );

$ma1 = 'cong-trinh-dac-cong'; // không dùng dự án của các kịch bản khác (it_import, it_site kiểm tra ảnh của bảo tàng)
$ma2 = 'truong-thcs-tay-tuu';
file_put_contents( "$tmp/in/{$ma1}-hero.jpg", with_segments( base_jpeg( 2560, 1097 ), seg( 0xE1, exif_payload( 1 ) ) ) );
file_put_contents( "$tmp/in/{$ma1}-the.png", png_with_metadata( base_png( 1600, 1200 ) ) );
file_put_contents( "$tmp/in/{$ma1}-slide.jpg", with_segments( base_jpeg( 1920, 1080 ), seg( 0xE1, exif_payload( 1 ) ) ) );
file_put_contents( "$tmp/in/{$ma1}-tv-02.jpg", base_jpeg( 1200, 800 ) );
file_put_contents( "$tmp/in/{$ma1}-tv-01.jpg", with_segments( base_jpeg( 800, 400, true ), seg( 0xE1, exif_payload( 6 ) ) ) );
file_put_contents( "$tmp/in/{$ma2}-hero.jpg", base_jpeg( 2560, 1097, true ) ); // nội dung KHÁC hero của dự án 1: importer dùng lại ảnh trùng nội dung (SHA-1)

$ids_of = static function ( string $ma ): int {
	return (int) get_posts( array( 'post_type' => 'du_an', 'post_status' => 'any', 'meta_key' => 'ma', 'meta_value' => $ma, 'fields' => 'ids' ) )[0];
};
$fields   = array( 'anh_dau_trang', 'anh_the', 'anh_trinh_chieu', 'thu_vien' );
$restore  = array();
foreach ( array( $ma1, $ma2 ) as $m ) {
	foreach ( $fields as $f ) {
		$restore[] = array( $ids_of( $m ), $f, get_field( $f, $ids_of( $m ) ) );
	}
}
wp_cache_flush();
$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ) );

/* ---- Công cụ xuất ảnh (lệnh thật, PHP của môi trường thử) ---- */
$cmd  = array( PHP_BINARY, '-c', LAB . '/php.ini', REPO . '/scripts/content/export-images.php', "$tmp/in", "$tmp/out", "--csv=$tmp/out/images.csv" );
$proc = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
$out  = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
same( 'công cụ xuất ảnh: thoát 0', 0, proc_close( $proc ) );
check( 'công cụ xuất ảnh: báo xong 6 ảnh', str_contains( $out, 'Xong: 6 ảnh' ) );
same( 'công cụ xuất ảnh: 6 tệp .jpg + images.csv', 7, count( glob( "$tmp/out/*" ) ) );

/* ---- Importer: bộ khung chú thích trống bị chặn ---- */
$cli   = new VGARCO_CLI_Command();
$runs  = static function ( array $assoc ) use ( $cli ): array {
	WP_CLI::$lines = array();
	try {
		$cli->import( array( 'projects', REPO . '/content/projects.csv' ), $assoc );
		$error = '';
	} catch ( RuntimeException $e ) {
		$error = $e->getMessage();
	}
	return array( 'lines' => WP_CLI::$lines, 'error' => $error );
};
$att   = static function (): int {
	wp_cache_flush();
	return count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
};
$skel  = (string) file_get_contents( "$tmp/out/images.csv" );
check( 'bộ khung images.csv: có BOM, 6 dòng dữ liệu, chú thích trống', str_starts_with( $skel, "\xEF\xBB\xBF" ) && 7 === count( array_filter( explode( "\n", $skel ) ) ) );
$before = $att();
$r      = $runs( array( 'images' => "$tmp/out", 'images-csv' => "$tmp/out/images.csv" ) );
check( 'importer chặn bộ khung chưa điền chú thích ảnh thư viện: báo lỗi, KHÔNG ghi gì', str_contains( $r['error'], 'KHÔNG ghi gì' ) && str_contains( implode( ' ', $r['lines'] ), 'chu_thich_vi' ) && $att() === $before );

/* ---- Điền chú thích rồi nạp ---- */
$filled = str_replace( ";tv;1;;;", ";tv;1;cong_trinh;Mặt đứng chính nhìn từ sân;", $skel );
$filled = str_replace( ";tv;2;;;", ";tv;2;thiet_ke;Phối cảnh mặt bằng tầng một;", $filled );
file_put_contents( "$tmp/out/images-filled.csv", $filled );
$opts = array( 'images' => "$tmp/out", 'images-csv' => "$tmp/out/images-filled.csv" );
$r    = $runs( $opts );
same( 'nạp sau khi điền chú thích: không lỗi', '', $r['error'] );
same( 'nạp: thêm đúng 6 ảnh', $before + 6, $att() );

$id = (int) get_posts( array( 'post_type' => 'du_an', 'post_status' => 'any', 'meta_key' => 'ma', 'meta_value' => $ma1, 'fields' => 'ids' ) )[0];
$id2 = (int) get_posts( array( 'post_type' => 'du_an', 'post_status' => 'any', 'meta_key' => 'ma', 'meta_value' => $ma2, 'fields' => 'ids' ) )[0];
$dims = static fn( int $a ): array => array_slice( getimagesize( get_attached_file( $a ) ), 0, 2 );
same( 'ảnh đầu trang 2560×1097', array( 2560, 1097 ), $dims( (int) get_field( 'anh_dau_trang', $id ) ) );
same( 'ảnh thẻ (PNG 1600×1200 nguồn) thành JPEG 1200×900', array( 1200, 900 ), $dims( (int) get_field( 'anh_the', $id ) ) );
same( 'ảnh trình chiếu 1600×900 (thu nhỏ từ 1920×1080)', array( 1600, 900 ), $dims( (int) get_field( 'anh_trinh_chieu', $id ) ) );
check( 'dự án thứ hai có ảnh đầu trang', (int) get_field( 'anh_dau_trang', $id2 ) > 0 );
$gal = (array) get_field( 'thu_vien', $id );
same( 'thư viện 2 ảnh theo thu_tu 1 → 2, chú thích đúng', array( 2, 'Mặt đứng chính nhìn từ sân', 'Phối cảnh mặt bằng tầng một' ), array( count( $gal ), $gal[0]['chu_thich'] ?? '', $gal[1]['chu_thich'] ?? '' ) );
same( 'thư viện: ảnh hướng 6 đã xoay thẳng thành 400×800 (công cụ xuất ảnh làm, không phải máy chủ)', array( 400, 800 ), $dims( (int) ( $gal[0]['anh'] ?? 0 ) ) );
same( 'văn bản thay thế = chú thích', 'Mặt đứng chính nhìn từ sân', get_post_meta( (int) ( $gal[0]['anh'] ?? 0 ), '_wp_attachment_image_alt', true ) );
$leak = array();
foreach ( glob( WP_CONTENT_DIR . '/uploads/' . gmdate( 'Y/m' ) . "/{$ma1}-*" ) ?: array() as $f ) {
	$raw = (string) file_get_contents( $f );
	if ( str_contains( $raw, GPS_NEEDLE ) || str_contains( $raw, "Exif\0\0" ) || str_contains( $raw, 'PNG-XMP-NEEDLE' ) ) {
		$leak[] = basename( $f );
	}
}
same( 'mọi tệp đã lưu trong uploads (gốc và bản thu nhỏ) không còn GPS/EXIF/XMP', array(), $leak );
check( 'có tệp đã lưu để kiểm tra (phép thử không rỗng)', (bool) glob( WP_CONTENT_DIR . '/uploads/' . gmdate( 'Y/m' ) . "/{$ma1}-*" ) );

$runs( $opts );
same( 'chạy lại: không nạp trùng', $before + 6, $att() );

// Trả môi trường về như cũ: xóa ảnh do kịch bản này nạp, khôi phục các ô ảnh của hai dự án.
foreach ( $restore as [ $pid, $f, $value ] ) {
	update_field( $f, $value, $pid );
}
wp_cache_flush();
foreach ( array_diff( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ) ), $existing ) as $new ) {
	wp_delete_attachment( $new, true );
}
$rm( $tmp );
finish();

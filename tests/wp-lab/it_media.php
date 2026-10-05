<?php
/**
 * Ảnh tải lên trên WordPress thật: tọa độ GPS và mọi siêu dữ liệu phải bị gỡ khỏi TỆP GỐC (WordPress phục vụ công khai tệp gốc),
 * ảnh dọc chụp bằng điện thoại phải được xoay đúng chiều trước khi gỡ hướng xoay, định dạng ảnh lạ và tệp hỏng bị từ chối và không để lại tệp.
 * Ảnh thử do GD sinh ra rồi chèn EXIF giả (tests/php/image-fixtures.php); không dùng ảnh thật của khách hàng.
 */
require __DIR__ . '/lib.php';
require_once REPO . '/tests/php/image-fixtures.php';

$tmp = LAB . '/tmp-media';
@mkdir( $tmp, 0777, true );
$created = array();

/** Nạp một tệp vào thư viện qua đường sideload của WordPress (cũng là đường của `wp vgarco import`). */
$sideload = static function ( string $bytes, string $name ) use ( $tmp ): array {
	$path = $tmp . '/' . wp_generate_password( 8, false ) . '-' . $name;
	file_put_contents( $path, $bytes );
	$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $path ), 0 );
	@unlink( $path );
	return array( $id, is_wp_error( $id ) ? $id->get_error_message() : '' );
};
/** Mọi tệp vật lý của một ảnh: gốc, bản "-scaled", ảnh trước khi thu nhỏ và các kích thước. */
$files = static function ( int $id ): array {
	$meta = wp_get_attachment_metadata( $id );
	$main = get_attached_file( $id, true );
	$dir  = dirname( $main );
	$out  = array( $main );
	if ( ! empty( $meta['original_image'] ) ) {
		$out[] = $dir . '/' . $meta['original_image'];
	}
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $s ) {
		$out[] = $dir . '/' . $s['file'];
	}
	return array_values( array_unique( array_filter( $out, 'is_file' ) ) );
};
$leaks = static function ( array $paths ): array {
	$bad = array();
	foreach ( $paths as $p ) {
		$raw = (string) file_get_contents( $p );
		if ( str_contains( $raw, GPS_NEEDLE ) || str_contains( $raw, 'XMP-GPS-NEEDLE' ) || str_contains( $raw, "Exif\0\0" ) || str_contains( $raw, 'PNG-XMP-NEEDLE' ) ) {
			$bad[] = basename( $p );
		}
	}
	return $bad;
};
$px = static function ( string $file, int $x, int $y ): array {
	$im = imagecreatefromstring( (string) file_get_contents( $file ) );
	$c  = imagecolorsforindex( $im, imagecolorat( $im, $x, $y ) );
	return array( $c['red'], $c['green'], $c['blue'] );
};
$is_red = static fn( array $c ): bool => $c[0] > 200 && $c[1] < 60 && $c[2] < 60;

$metadata = seg( 0xE1, exif_payload( 1 ) ) . seg( 0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>XMP-GPS-NEEDLE</x:xmpmeta>" ) . seg( 0xED, "Photoshop 3.0\0IPTC" );

/* ---- Cấu hình ---- */
$mimes = get_allowed_mime_types();
check( 'cho phép JPEG và PNG', in_array( 'image/jpeg', $mimes, true ) && in_array( 'image/png', $mimes, true ) );
check( 'không cho WebP, AVIF, HEIC, GIF, TIFF, BMP, ICO (siêu dữ liệu chưa gỡ được)', ! array_filter( $mimes, fn( $m ) => str_starts_with( $m, 'image/' ) && ! in_array( $m, array( 'image/jpeg', 'image/png' ), true ) ) );
check( 'tệp không phải ảnh (PDF) vẫn được phép', in_array( 'application/pdf', $mimes, true ) );

/* ---- Chất lượng AVIF qua bộ xử lý ảnh THẬT của WordPress ---- */
if ( wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) {
	$noise = imagecreatetruecolor( 900, 600 ); // ảnh nhiều chi tiết để mức nén có ảnh hưởng
	mt_srand( 7 );
	for ( $y = 0; $y < 600; $y += 2 ) {
		for ( $x = 0; $x < 900; $x += 2 ) {
			$c = imagecolorallocate( $noise, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) );
			imagefilledrectangle( $noise, $x, $y, $x + 1, $y + 1, $c );
		}
	}
	$src = $tmp . '/nhieu-chi-tiet.jpg';
	imagejpeg( $noise, $src, 90 );
	$encode = static function () use ( $src, $tmp ): int {
		$editor = wp_get_image_editor( $src );
		$dest   = $tmp . '/q.avif';
		$editor->save( $dest, 'image/avif' );
		$size = (int) filesize( $dest );
		@unlink( $dest );
		return $size;
	};
	$with    = $encode();
	remove_filter( 'wp_editor_set_quality', 'vgarco_image_quality', 10 );
	$without = $encode();
	add_filter( 'wp_editor_set_quality', 'vgarco_image_quality', 10, 2 );
	check( "AVIF qua bộ xử lý ảnh của WordPress: có bộ lọc nhẹ hơn rõ rệt (< 75% của mặc định): {$with} so với {$without} byte", $with > 0 && $with < 0.75 * $without );
	same( 'bộ lọc không đổi chất lượng JPEG', 82, apply_filters( 'wp_editor_set_quality', 82, 'image/jpeg', array() ) );
} else {
	echo "  (bỏ qua phép kiểm AVIF: bộ xử lý ảnh trên máy này không hỗ trợ AVIF)
";
}

/* ---- JPEG có GPS, không xoay ---- */
[ $id, $err ] = $sideload( with_segments( base_jpeg( 800, 400, true ), seg( 0xE1, exif_payload( 1 ) ) . $metadata ), 'khong-xoay.jpg' );
check( 'JPEG có GPS: tải lên được' . ( $err ? " ({$err})" : '' ), is_int( $id ) );
$created[] = $id;
$all       = $files( $id );
check( 'JPEG có GPS: có tệp gốc và các kích thước', count( $all ) >= 3 );
same( 'JPEG có GPS: MỌI tệp (gốc và các bản thu nhỏ) không còn GPS, EXIF, XMP', array(), $leaks( $all ) );
$orig = get_attached_file( $id, true );
check( 'JPEG có GPS: ảnh gốc vẫn là ảnh hợp lệ cùng kích thước 800×400', array( 800, 400 ) === array_slice( getimagesize( $orig ), 0, 2 ) );
check( 'JPEG không xoay: góc trên trái vẫn đỏ (không bị xoay oan)', $is_red( $px( $orig, 2, 2 ) ) );

/* ---- Ảnh dọc chụp bằng điện thoại: hướng xoay 6, 8, 3 ---- */
// Ảnh gốc 800×400 có góc trên trái đỏ. Hiển thị đúng chiều thì: 6 (xoay 90° thuận) → 400×800, đỏ ở trên phải; 8 (ngược) → 400×800, đỏ ở dưới trái; 3 (180°) → 800×400, đỏ ở dưới phải.
foreach ( array( 6 => array( 400, 800, 'trên phải' ), 8 => array( 400, 800, 'dưới trái' ), 3 => array( 800, 400, 'dưới phải' ) ) as $o => [ $w, $h, $where ] ) {
	[ $id, $err ] = $sideload( with_segments( base_jpeg( 800, 400, true ), seg( 0xE1, exif_payload( $o ) ) ), "xoay-{$o}.jpg" );
	check( "hướng xoay {$o}: tải lên được" . ( $err ? " ({$err})" : '' ), is_int( $id ) );
	if ( ! is_int( $id ) ) {
		continue;
	}
	$created[] = $id;
	$orig      = get_attached_file( $id, true );
	[ $iw, $ih ] = getimagesize( $orig );
	same( "hướng xoay {$o}: ảnh gốc đã xoay thẳng, kích thước {$w}×{$h}", array( $w, $h ), array( $iw, $ih ) );
	$corner = array( 'trên phải' => array( $iw - 3, 2 ), 'dưới trái' => array( 2, $ih - 3 ), 'dưới phải' => array( $iw - 3, $ih - 3 ) )[ $where ];
	check( "hướng xoay {$o}: góc đỏ nằm ở {$where}", $is_red( $px( $orig, ...$corner ) ) );
	same( "hướng xoay {$o}: không còn hướng xoay trong tệp (không bị xoay lần hai)", 1, vgarco_jpeg_orientation( (string) file_get_contents( $orig ) ) );
	same( "hướng xoay {$o}: các bản thu nhỏ cũng đúng chiều {$w}×{$h}", true, ( static function () use ( $id, $w, $h ): bool {
		$meta = wp_get_attachment_metadata( $id );
		foreach ( $meta['sizes'] ?? array() as $s ) {
			if ( $s['width'] !== $s['height'] && ( $s['width'] > $s['height'] ) !== ( $w > $h ) ) { // bản 150×150 của WordPress là hình vuông cắt, bỏ qua
				return false;
			}
		}
		return true;
	} )() );
	same( "hướng xoay {$o}: không rò tọa độ", array(), $leaks( $files( $id ) ) );
}

/* ---- Ảnh lớn hơn 2560 px: WordPress giữ tệp gốc và tạo thêm bản "-scaled" ---- */
[ $id, $err ] = $sideload( with_segments( base_jpeg( 3000, 1500 ), seg( 0xE1, exif_payload( 1 ) ) ), 'lon.jpg' );
check( 'ảnh 3000 px: tải lên được' . ( $err ? " ({$err})" : '' ), is_int( $id ) );
if ( is_int( $id ) ) {
	$created[] = $id;
	$meta      = wp_get_attachment_metadata( $id );
	check( 'ảnh 3000 px: WordPress tạo bản -scaled và giữ tệp gốc', ! empty( $meta['original_image'] ) && str_contains( (string) $meta['file'], '-scaled' ) );
	$all = $files( $id );
	check( 'ảnh 3000 px: tệp gốc chưa thu nhỏ có mặt trên đĩa (công khai được)', in_array( $meta['original_image'], array_map( 'basename', $all ), true ) );
	same( 'ảnh 3000 px: tệp gốc chưa thu nhỏ và bản -scaled đều không còn GPS', array(), $leaks( $all ) );
}

/* ---- PNG ---- */
[ $id, $err ] = $sideload( png_with_metadata( base_png( 200, 100 ) ), 'render.png' );
check( 'PNG có tEXt/eXIf/iTXt: tải lên được' . ( $err ? " ({$err})" : '' ), is_int( $id ) );
if ( is_int( $id ) ) {
	$created[] = $id;
	same( 'PNG: tệp gốc và các bản thu nhỏ không còn siêu dữ liệu', array(), $leaks( $files( $id ) ) );
	check( 'PNG: ảnh vẫn hợp lệ', array( 200, 100 ) === array_slice( getimagesize( get_attached_file( $id, true ) ), 0, 2 ) );
}

/* ---- Từ chối ---- */
$before = glob( wp_upload_dir()['path'] . '/*' ) ?: array();
ob_start();
$gif_im = imagecreatetruecolor( 20, 20 );
imagegif( $gif_im );
$gif = (string) ob_get_clean();
[ $r, $err ] = $sideload( $gif, 'anh-dong.gif' );
check( 'GIF bị từ chối', is_wp_error( $r ) );
[ $r, $err ] = $sideload( base_jpeg( 50, 50 ), 'doi-duoi.webp' );
check( 'JPEG đổi đuôi .webp bị từ chối', is_wp_error( $r ) );
[ $r, $err ] = $sideload( "GIF89a" . str_repeat( 'x', 100 ), 'gia-gif.jpg' );
check( 'tệp không phải ảnh nhưng đặt đuôi .jpg bị từ chối', is_wp_error( $r ) );
// JPEG cắt cụt: đủ "dấu hiệu" JPEG để qua kiểm tra loại tệp của WordPress nhưng không có dữ liệu ảnh → phải bị từ chối và xóa tệp.
$cut = substr( with_segments( base_jpeg( 300, 300 ), seg( 0xE1, exif_payload( 1 ) ) ), 0, 120 );
[ $r, $err ] = $sideload( $cut, 'cut-cut.jpg' );
check( 'JPEG cắt cụt bị từ chối' . ( $err ? " ({$err})" : '' ), is_wp_error( $r ) );
check( 'lý do từ chối đọc được bằng tiếng Việt, có hướng dẫn', is_wp_error( $r ) && str_contains( $err, 'xuất lại' ) || str_contains( (string) $err, 'Không xử lý được' ) || str_contains( (string) $err, 'không' ) );
wp_cache_flush();
$after = glob( wp_upload_dir()['path'] . '/*' ) ?: array();
same( 'tệp bị từ chối KHÔNG để lại gì trong uploads', array(), array_values( array_diff( $after, $before ) ) );

[ $r, $err ] = $sideload( "%PDF-1.4\n" . str_repeat( 'nội dung ', 50 ) . "\n%%EOF\n", 'tai-lieu.pdf' );
check( 'PDF vẫn tải lên được (không bị bộ lọc ảnh chặn)' . ( $err ? " ({$err})" : '' ), is_int( $r ) );
if ( is_int( $r ) ) {
	$created[] = $r;
}

/* ---- Tải qua trang quản trị bằng HTTP thật (async-upload.php), đúng đường biên tập viên dùng ---- */
$cookie = lab_cookie_for( (int) get_user_by( 'login', 'labadmin' )->ID );
$page   = http( 'GET', '/wp-admin/media-new.php', array( 'headers' => array( 'Cookie: ' . $cookie ) ) );
preg_match( '/"_wpnonce":"([a-f0-9]+)"/', $page['body'], $m );
check( 'lấy được mã nonce tải ảnh của trang quản trị', ! empty( $m[1] ) );
if ( ! empty( $m[1] ) ) {
	$up   = $tmp . '/qua-http.jpg';
	file_put_contents( $up, with_segments( base_jpeg( 800, 400, true ), seg( 0xE1, exif_payload( 6 ) ) . $metadata ) );
	$resp = http( 'POST', '/wp-admin/async-upload.php', array( 'headers' => array( 'Cookie: ' . $cookie ), 'body' => array( 'action' => 'upload-attachment', '_wpnonce' => $m[1], 'name' => 'qua-http.jpg', 'async-upload' => new CURLFile( $up, 'image/jpeg', 'qua-http.jpg' ) ) ) );
	check( 'tải ảnh có GPS qua trang quản trị: thành công', ! empty( $resp['json']['success'] ) );
	if ( ! empty( $resp['json']['data']['id'] ) ) {
		$hid       = (int) $resp['json']['data']['id'];
		$created[] = $hid;
		wp_cache_flush();
		same( 'tải qua HTTP: tệp trên đĩa không còn GPS', array(), $leaks( $files( $hid ) ) );
		$url = $resp['json']['data']['url'];
		$get = http( 'GET', wp_parse_url( $url, PHP_URL_PATH ) );
		check( 'tải qua HTTP: tệp tải về công khai không có GPS và đã xoay thẳng (400×800)', 200 === $get['status'] && ! str_contains( $get['body'], GPS_NEEDLE ) && ! str_contains( $get['body'], "Exif\0\0" ) && 400 === getimagesizefromstring( $get['body'] )[0] );
	}
	$bad = $tmp . '/gif.gif';
	file_put_contents( $bad, $gif );
	$resp = http( 'POST', '/wp-admin/async-upload.php', array( 'headers' => array( 'Cookie: ' . $cookie ), 'body' => array( 'action' => 'upload-attachment', '_wpnonce' => $m[1], 'name' => 'gif.gif', 'async-upload' => new CURLFile( $bad, 'image/gif', 'gif.gif' ) ) ) );
	check( 'tải GIF qua trang quản trị: bị từ chối', empty( $resp['json']['success'] ) );
}

foreach ( $created as $cid ) {
	wp_delete_attachment( $cid, true );
}
foreach ( glob( $tmp . '/*' ) ?: array() as $f ) {
	@unlink( $f );
}
finish();

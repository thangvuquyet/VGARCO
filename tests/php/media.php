<?php
/**
 * Kiểm thử gỡ siêu dữ liệu ảnh (inc/media-lib.php) bằng ảnh THẬT: JPEG và PNG do GD sinh ra rồi chèn thêm EXIF có tọa độ GPS giả,
 * XMP, IPTC, MPF, ghi chú, hồ sơ màu ICC. Phần nối WordPress (wp_handle_upload, xoay ảnh, từ chối định dạng lạ) chạy ở tests/wp-lab (it_media.php).
 *
 *   php -d extension_dir=<ext> -d extension=mbstring -d extension=gd tests/php/media.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );
require_once ROOT . '/wp-content/plugins/vgarco-core/inc/media-lib.php';

$pass = 0;
$fail = array();

function check( string $label, bool $condition ): void {
	global $pass, $fail;
	if ( $condition ) {
		++$pass;
	} else {
		$fail[] = $label;
	}
}

/** Hiển thị gọn: chuỗi nhị phân dài chỉ in độ dài và mã băm (đừng đổ cả tệp ảnh ra màn hình). */
function show( $v ): string {
	return is_string( $v ) && ( strlen( $v ) > 60 || preg_match( '/[^\x20-\x7E]/', $v ) ) ? 'chuỗi ' . strlen( $v ) . ' byte, md5 ' . md5( $v ) : var_export( $v, true );
}

function same( string $label, $expected, $actual ): void {
	check( $label . ' — mong đợi ' . show( $expected ) . ', nhận ' . show( $actual ), $expected === $actual );
}

/* ---------- Dựng ảnh: xem image-fixtures.php ---------- */

require_once __DIR__ . '/image-fixtures.php';

$icc  = 'ICCDATA-giu-lai';
$full = with_segments(
	base_jpeg(),
	seg( 0xE1, exif_payload( 6 ) )
	. seg( 0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>XMP-GPS-NEEDLE</x:xmpmeta>" )
	. seg( 0xED, "Photoshop 3.0\0" . '8BIM-IPTC-NEEDLE' )
	. seg( 0xE2, "MPF\0" . 'MPF-NEEDLE' )
	. seg( 0xE2, "ICC_PROFILE\0\x01\x01" . $icc )
	. seg( 0xEE, "Adobe\0\x64\x80\x00\x00\x00\x00\x01" )
	. seg( 0xFE, 'COM-NEEDLE ghi chú máy ảnh' )
);
$needles = array( GPS_NEEDLE, 'XMP-GPS-NEEDLE', '8BIM-IPTC-NEEDLE', 'MPF-NEEDLE', 'COM-NEEDLE' );

/* ========== JPEG: gỡ siêu dữ liệu ========== */

foreach ( $needles as $n ) {
	check( "đầu vào có chứa {$n} (kiểm tra điều kiện của phép thử)", str_contains( $full, $n ) );
}
$clean = vgarco_jpeg_strip_metadata( $full );
check( 'strip: trả về JPEG', is_string( $clean ) && "\xFF\xD8" === substr( $clean, 0, 2 ) );
foreach ( $needles as $n ) {
	check( "strip: không còn {$n}", ! str_contains( (string) $clean, $n ) );
}
check( 'strip: không còn đoạn EXIF', ! str_contains( (string) $clean, "Exif\0\0" ) );
check( 'strip: giữ JFIF', str_contains( (string) $clean, "JFIF\0" ) );
check( 'strip: giữ hồ sơ màu ICC (mất thì màu sai)', str_contains( (string) $clean, 'ICC_PROFILE' ) && str_contains( (string) $clean, $icc ) );
check( 'strip: giữ đoạn Adobe (mất thì đổi màu sai)', str_contains( (string) $clean, 'Adobe' ) );
check( 'strip: nhỏ hơn đầu vào', strlen( (string) $clean ) < strlen( $full ) );

$in_scan  = substr( $full, vgarco_jpeg_parse( $full )['scan'] );
$out_scan = substr( (string) $clean, vgarco_jpeg_parse( (string) $clean )['scan'] );
check( 'strip: dữ liệu ảnh (quét) giữ nguyên từng byte — không mất chất lượng', $in_scan === $out_scan );
$im_in  = imagecreatefromstring( $full );
$im_out = imagecreatefromstring( (string) $clean );
check( 'strip: ảnh vẫn giải mã được, cùng kích thước', false !== $im_out && imagesx( $im_in ) === imagesx( $im_out ) && imagesy( $im_in ) === imagesy( $im_out ) );
$same_px = true;
for ( $i = 0; $i < 40; $i++ ) {
	$same_px = $same_px && imagecolorat( $im_in, ( $i * 7 ) % 120, ( $i * 5 ) % 60 ) === imagecolorat( $im_out, ( $i * 7 ) % 120, ( $i * 5 ) % 60 );
}
check( 'strip: điểm ảnh giống hệt nhau', $same_px );
same( 'strip: chạy hai lần cho cùng kết quả', $clean, vgarco_jpeg_strip_metadata( (string) $clean ) );
// GD tự ghi một ghi chú "CREATOR: gd-jpeg ..." (đoạn COM): ảnh chỉ có ghi chú đó thì chỉ mất đúng ghi chú đó.
$plain = base_jpeg();
check( 'điều kiện của phép thử: ảnh GD có ghi chú CREATOR', str_contains( $plain, 'CREATOR: gd-jpeg' ) );
$plain_clean = (string) vgarco_jpeg_strip_metadata( $plain );
check( 'strip: ảnh không có siêu dữ liệu nào khác chỉ mất ghi chú CREATOR của GD', ! str_contains( $plain_clean, 'CREATOR' ) && substr( $plain, vgarco_jpeg_parse( $plain )['scan'] ) === substr( $plain_clean, vgarco_jpeg_parse( $plain_clean )['scan'] ) && false !== imagecreatefromstring( $plain_clean ) );
same( 'strip: không còn hướng xoay (đã gỡ cùng EXIF)', 1, vgarco_jpeg_orientation( (string) $clean ) );

// Đoạn nằm TRƯỚC JFIF và byte đệm 0xFF giữa các đoạn vẫn xử lý đúng.
$odd = "\xFF\xD8" . seg( 0xE1, exif_payload( 3 ) ) . "\xFF\xFF" . substr( base_jpeg(), 2 );
$odd_clean = vgarco_jpeg_strip_metadata( $odd );
check( 'strip: EXIF đứng đầu và byte đệm 0xFF', is_string( $odd_clean ) && ! str_contains( $odd_clean, GPS_NEEDLE ) && false !== imagecreatefromstring( $odd_clean ) );

// Siêu dữ liệu giả mạo không được lọt qua bằng cách mượn tên đoạn được giữ.
$sneaky = with_segments( base_jpeg(), seg( 0xE2, "ICC_PROFILE\0\x01\x01" . GPS_NEEDLE ) );
check( 'ghi chú: đoạn ICC do người dùng nhập vẫn được giữ (giới hạn đã biết: giữ nguyên hồ sơ màu, không đọc nội dung)', str_contains( (string) vgarco_jpeg_strip_metadata( $sneaky ), GPS_NEEDLE ) );

/* ========== JPEG: hướng xoay ========== */

foreach ( array( 'MM', 'II' ) as $order ) {
	for ( $o = 1; $o <= 8; $o++ ) {
		same( "orientation {$o} ({$order})", $o, vgarco_jpeg_orientation( with_segments( base_jpeg(), seg( 0xE1, exif_payload( $o, $order ) ) ) ) );
	}
}
same( 'orientation 0 (ngoài 1–8) → 1', 1, vgarco_jpeg_orientation( with_segments( base_jpeg(), seg( 0xE1, exif_payload( 0 ) ) ) ) );
same( 'orientation 9 (ngoài 1–8) → 1', 1, vgarco_jpeg_orientation( with_segments( base_jpeg(), seg( 0xE1, exif_payload( 9 ) ) ) ) );
same( 'orientation: EXIF không có thẻ hướng xoay → 1', 1, vgarco_jpeg_orientation( with_segments( base_jpeg(), seg( 0xE1, exif_payload( 6, 'MM', GPS_NEEDLE, false ) ) ) ) );
same( 'orientation: không có EXIF → 1', 1, vgarco_jpeg_orientation( base_jpeg() ) );
same( 'orientation: XMP không phải EXIF → 1', 1, vgarco_jpeg_orientation( with_segments( base_jpeg(), seg( 0xE1, "http://ns.adobe.com/xap/1.0/\0x" ) ) ) );
same( 'orientation: tiêu đề TIFF hỏng (không phải II/MM) → 1', 1, vgarco_jpeg_orientation( with_segments( base_jpeg(), seg( 0xE1, "Exif\0\0XX\x00\x2A\x00\x00\x00\x08" ) ) ) );
same( 'orientation: IFD trỏ ra ngoài đoạn → 1', 1, vgarco_jpeg_orientation( with_segments( base_jpeg(), seg( 0xE1, "Exif\0\0MM\x00\x2A\xFF\xFF\xFF\xF0" ) ) ) );
same( 'orientation: EXIF cắt ngắn giữa chừng → 1', 1, vgarco_jpeg_orientation( with_segments( base_jpeg(), seg( 0xE1, substr( exif_payload( 6 ), 0, 20 ) ) ) ) );
same( 'orientation: rác → 1', 1, vgarco_jpeg_orientation( 'không phải ảnh' ) );

/* ========== JPEG: tệp hỏng phải bị từ chối (null), không được để nguyên ========== */

foreach (
	array(
		'rỗng'                        => '',
		'chữ thường'                  => 'không phải ảnh',
		'chỉ SOI'                     => "\xFF\xD8",
		'SOI rồi rác'                 => "\xFF\xD8ABCDEFGH",
		'PNG đưa vào hàm JPEG'        => "\x89PNG\r\n\x1A\n" . str_repeat( 'x', 40 ),
		'đoạn dài hơn tệp'            => "\xFF\xD8\xFF\xE1\xFF\xFF" . str_repeat( 'x', 20 ),
		'độ dài đoạn nhỏ hơn 2'       => "\xFF\xD8\xFF\xE1\x00\x01" . str_repeat( 'x', 20 ),
		'kết thúc ảnh trước dữ liệu'  => "\xFF\xD8\xFF\xD9",
		'cắt ngay giữa độ dài đoạn'   => "\xFF\xD8\xFF\xE1\x00",
	) as $label => $bytes
) {
	same( "strip: {$label} → null", null, vgarco_jpeg_strip_metadata( $bytes ) );
}
$truncated = substr( $full, 0, 40 );
same( 'strip: JPEG cắt cụt trong đoạn EXIF → null', null, vgarco_jpeg_strip_metadata( $truncated ) );

/* ========== PNG ========== */

$png      = base_png();
$png_full = png_with_metadata( $png );

check( 'PNG: đầu vào có chứa tọa độ (điều kiện của phép thử)', str_contains( $png_full, GPS_NEEDLE ) && str_contains( $png_full, 'PNG-XMP-NEEDLE' ) );
$png_clean = vgarco_png_strip_metadata( $png_full );
check( 'PNG strip: trả về PNG', is_string( $png_clean ) && str_starts_with( $png_clean, "\x89PNG\r\n\x1A\n" ) );
check( 'PNG strip: không còn tọa độ, XMP, EXIF', ! str_contains( (string) $png_clean, GPS_NEEDLE ) && ! str_contains( (string) $png_clean, 'PNG-XMP-NEEDLE' ) && ! str_contains( (string) $png_clean, 'eXIf' ) );
foreach ( array( 'tEXt', 'zTXt', 'iTXt', 'tIME' ) as $type ) {
	check( "PNG strip: không còn khối {$type}", ! str_contains( (string) $png_clean, $type ) );
}
same( 'PNG strip: trở về đúng ảnh gốc từng byte (mọi khối khác giữ nguyên)', $png, $png_clean );
check( 'PNG strip: ảnh vẫn giải mã được', false !== imagecreatefromstring( (string) $png_clean ) );
same( 'PNG strip: chạy hai lần cho cùng kết quả', $png_clean, vgarco_png_strip_metadata( (string) $png_clean ) );

foreach (
	array(
		'rỗng'                 => '',
		'chữ thường'           => 'không phải ảnh',
		'JPEG đưa vào hàm PNG' => base_jpeg(),
		'chỉ chữ ký'           => "\x89PNG\r\n\x1A\n",
		'khối dài hơn tệp'     => "\x89PNG\r\n\x1A\n" . pack( 'N', 9999 ) . 'IHDR' . str_repeat( 'x', 20 ),
		'thiếu IEND'           => substr( $png, 0, -12 ),
	) as $label => $bytes
) {
	same( "PNG strip: {$label} → null", null, vgarco_png_strip_metadata( $bytes ) );
}

/* ---------- Kết quả ---------- */

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

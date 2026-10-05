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

/* ========== Chất lượng mã hóa theo định dạng ========== */

same( 'chất lượng AVIF', 55, vgarco_image_quality( 82, 'image/avif' ) );
same( 'chất lượng WebP', 72, vgarco_image_quality( 86, 'image/webp' ) );
same( 'JPEG giữ mặc định của WordPress', 82, vgarco_image_quality( 82, 'image/jpeg' ) );
same( 'PNG giữ mặc định', 90, vgarco_image_quality( 90, 'image/png' ) );
same( 'định dạng khác/không rõ giữ mặc định', 77, vgarco_image_quality( 77, '' ) );
same( 'mặc định đưa vào dạng chuỗi vẫn ra số nguyên', 82, vgarco_image_quality( '82', 'image/jpeg' ) );
check( 'AVIF nén mạnh hơn WebP, WebP nén mạnh hơn mặc định (thứ tự có chủ đích)', VGARCO_AVIF_QUALITY < VGARCO_WEBP_QUALITY && VGARCO_WEBP_QUALITY < 82 );
check( 'các bậc chất lượng AVIF: bắt đầu từ mức chung, giảm dần, tối thiểu 45', VGARCO_AVIF_QUALITY_STEPS[0] === VGARCO_AVIF_QUALITY && VGARCO_AVIF_QUALITY_STEPS === array( 55, 50, 45 ) );

/* ========== Chất lượng AVIF theo từng ảnh (giữ trần mục 9.2) ========== */

$KB = 1024;
foreach (
	array(
		'ảnh đầu trang 21:9 (2560×1097)'                 => array( 2560, 1097, 350 ),
		'ảnh đầu trang lệch tỉ lệ trong sai số 3% (2560×1100)' => array( 2560, 1100, 350 ),
		'trình chiếu 16:9 (1600×900)'                    => array( 1600, 900, 250 ),
		'thẻ dự án 4:3 (1200×900)'                       => array( 1200, 900, 150 ),
		'thư viện ngang 3:2 (1800×1200)'                 => array( 1800, 1200, 300 ),
		'thư viện dọc 2:3 (1200×1800)'                   => array( 1200, 1800, 300 ),
		'thư viện 4:3 (1800×1350)'                       => array( 1800, 1350, 300 ),
		'lĩnh vực, tin tức 3:2 (1200×800)'               => array( 1200, 800, 150 ),
		'ảnh nhỏ (800×600)'                              => array( 800, 600, 150 ),
		'lệch 21:9 quá sai số (2560×1200) tính theo cỡ'  => array( 2560, 1200, 300 ),
		'kích thước 0 không chia cho 0'                  => array( 0, 0, 150 ),
	) as $label => [ $w, $h, $kb ]
) {
	same( "trần AVIF: {$label}", $kb * $KB, vgarco_image_ceiling_bytes( $w, $h ) );
}

$calls = array();
$curve = static function ( array $bytes_by_q ) use ( &$calls ): callable {
	return static function ( int $q ) use ( $bytes_by_q, &$calls ): ?int {
		$calls[] = $q;
		return $bytes_by_q[ $q ] ?? null;
	};
};

$calls = array();
same( 'vừa trần ở 55: giữ 55, chỉ đo một lần', array( 'quality' => 55, 'bytes' => 300, 'fits' => true ), vgarco_pick_avif_quality( $curve( array( 55 => 300, 50 => 250, 45 => 200 ) ), 350 ) );
same( '… số lần đo', array( 55 ), $calls );

$calls = array();
same( 'vượt ở 55, vừa ở 50: hạ một bậc', array( 'quality' => 50, 'bytes' => 340, 'fits' => true ), vgarco_pick_avif_quality( $curve( array( 55 => 400, 50 => 340, 45 => 250 ) ), 350 ) );
same( '… dừng ngay khi vừa (không đo 45)', array( 55, 50 ), $calls );

$calls = array();
same( 'chỉ vừa ở 45', array( 'quality' => 45, 'bytes' => 349, 'fits' => true ), vgarco_pick_avif_quality( $curve( array( 55 => 600, 50 => 450, 45 => 349 ) ), 350 ) );

same( 'đúng bằng trần là vừa', true, vgarco_pick_avif_quality( $curve( array( 55 => 350 ) ), 350 )['fits'] );
same( 'hơn trần 1 byte là vượt', false, vgarco_pick_avif_quality( $curve( array( 55 => 351, 50 => 351, 45 => 351 ) ), 350 )['fits'] );

$calls = array();
same( '45 vẫn vượt: giữ 45 (không hạ thêm) và báo vượt', array( 'quality' => 45, 'bytes' => 500, 'fits' => false ), vgarco_pick_avif_quality( $curve( array( 55 => 900, 50 => 700, 45 => 500 ) ), 350 ) );
same( '… đã thử đủ ba bậc theo thứ tự', array( 55, 50, 45 ), $calls );

$calls = array();
same( 'không đo được: giữ mức chung, không đoán, không đo tiếp', array( 'quality' => 55, 'bytes' => null, 'fits' => null ), vgarco_pick_avif_quality( $curve( array() ), 350 ) );
same( '… dừng ở lần đo hỏng đầu tiên', array( 55 ), $calls );

$calls = array();
same( 'đo hỏng giữa chừng: về mức chung, không giữ bậc nửa vời', array( 'quality' => 55, 'bytes' => null, 'fits' => null ), vgarco_pick_avif_quality( $curve( array( 55 => 600 ) ), 350 ) );

// Bộ lọc chất lượng theo ảnh: trạng thái nằm trong biến tĩnh, mỗi phép thử dọn lại.
$state = &vgarco_image_quality_state();
$reset = static function () use ( &$state ): void {
	$state = array( 'force' => null, 'ctx' => null );
};

$reset();
$state['ctx'] = array( 'width' => 2560, 'quality' => 45, 'resolve' => null );
same( 'ảnh đã quyết 45: bản AVIF lớn nhất dùng 45', 45, vgarco_image_quality( 82, 'image/avif', array( 'width' => 2560, 'height' => 1097 ) ) );
same( '… bản 1600 (62% cỡ gốc) cũng dùng 45', 45, vgarco_image_quality( 82, 'image/avif', array( 'width' => 1600, 'height' => 686 ) ) );
same( '… bản 1200 (47% cỡ gốc) giữ mức chung 55: đã nhẹ sẵn', 55, vgarco_image_quality( 82, 'image/avif', array( 'width' => 1200, 'height' => 514 ) ) );
same( '… không biết kích thước bản đang sinh thì dùng mức của ảnh', 45, vgarco_image_quality( 82, 'image/avif' ) );
same( '… WebP không bị đổi', 72, vgarco_image_quality( 86, 'image/webp', array( 'width' => 2560 ) ) );
same( '… JPEG không bị đổi', 82, vgarco_image_quality( 82, 'image/jpeg', array( 'width' => 2560 ) ) );

$reset();
$state['ctx'] = array( 'width' => 2560, 'quality' => 45, 'resolve' => null );
$state['force'] = 50;
same( 'đang mã hóa thử: chất lượng ép thắng mức của ảnh', 50, vgarco_image_quality( 82, 'image/avif', array( 'width' => 2560 ) ) );
same( '… cả khi chưa có ảnh nào đang xử lý', 50, ( static function () use ( &$state ) {
	$state['ctx'] = null;
	return vgarco_image_quality( 82, 'image/avif' );
} )() );

$reset();
same( 'không có ảnh nào đang xử lý: mức chung', 55, vgarco_image_quality( 82, 'image/avif', array( 'width' => 2560 ) ) );

// Quyết định chậm: chỉ đo khi thật sự có bản AVIF được sinh, và chỉ đo một lần cho mỗi ảnh.
$reset();
$resolved       = 0;
$state['ctx']   = array(
	'width'   => 1600,
	'quality' => null,
	'resolve' => static function () use ( &$resolved ): int {
		++$resolved;
		return 50;
	},
);
same( 'chưa cần AVIF (JPEG): không đo', 82, vgarco_image_quality( 82, 'image/jpeg', array( 'width' => 1600 ) ) );
same( '… vẫn chưa đo', 0, $resolved );
same( 'bản AVIF đầu tiên: đo rồi dùng kết quả', 50, vgarco_image_quality( 82, 'image/avif', array( 'width' => 1600 ) ) );
same( 'bản AVIF thứ hai (75% cỡ gốc): dùng lại, không đo nữa', 50, vgarco_image_quality( 82, 'image/avif', array( 'width' => 1200 ) ) );
same( 'bản AVIF nhỏ (50% cỡ gốc): mức chung', 55, vgarco_image_quality( 82, 'image/avif', array( 'width' => 800 ) ) );
same( '… tổng cộng chỉ đo một lần', 1, $resolved );
$reset();

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

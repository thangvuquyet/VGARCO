<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );
require_once ROOT . '/wp-content/plugins/vgarco-core/inc/media-lib.php';
require_once ROOT . '/scripts/content/export-images-lib.php';
require_once __DIR__ . '/image-fixtures.php';

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

function show( $v ): string {
	return is_string( $v ) && ( strlen( $v ) > 80 || preg_match( '/[^\x20-\x7E]/', $v ) ) ? 'chuỗi ' . strlen( $v ) . ' byte' : var_export( $v, true );
}

function same( string $label, $expected, $actual ): void {
	check( $label . ' — mong đợi ' . show( $expected ) . ', nhận ' . show( $actual ), $expected === $actual );
}

function px( $im, int $x, int $y ): array {
	$c = imagecolorsforindex( $im, imagecolorat( $im, $x, $y ) );
	return array( $c['red'], $c['green'], $c['blue'] );
}
function is_red( array $c ): bool {
	return $c[0] > 200 && $c[1] < 60 && $c[2] < 60;
}
function noise_image( int $w, int $h ) {
	$im = imagecreatetruecolor( $w, $h );
	mt_srand( 11 );
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			imagesetpixel( $im, $x, $y, ( mt_rand( 0, 255 ) << 16 ) | ( mt_rand( 0, 255 ) << 8 ) | mt_rand( 0, 255 ) );
		}
	}
	return $im;
}
function jpeg_bytes( $im, int $q = 90 ): string {
	ob_start();
	imagejpeg( $im, null, $q );
	return (string) ob_get_clean();
}

foreach (
	array(
		'bao-tang-qdnd-lao-hero.jpg' => array( 'bao-tang-qdnd-lao', 'hero', null, 'bao-tang-qdnd-lao-hero.jpg' ),
		'x-the.PNG'                  => array( 'x', 'the', null, 'x-the.jpg' ),
		'x-slide.jpeg'               => array( 'x', 'slide', null, 'x-slide.jpg' ),
		'x-tv-1.jpg'                 => array( 'x', 'tv', 1, 'x-tv-01.jpg' ),
		'x-tv-12.jpg'                => array( 'x', 'tv', 12, 'x-tv-12.jpg' ),
		'x-tv-001.jpg'               => array( 'x', 'tv', 1, 'x-tv-01.jpg' ),
		'truong-the-thao-the.jpg'    => array( 'truong-the-thao', 'the', null, 'truong-the-thao-the.jpg' ),
		'hero-tv-02.jpg'             => array( 'hero', 'tv', 2, 'hero-tv-02.jpg' ),
		'a-the-tv-03.jpg'            => array( 'a-the', 'tv', 3, 'a-the-tv-03.jpg' ),
		'du-an-2024-hero.jpg'        => array( 'du-an-2024', 'hero', null, 'du-an-2024-hero.jpg' ),
	) as $file => [ $ma, $role, $n, $out ]
) {
	same( "tên {$file}", array( 'ma' => $ma, 'role' => $role, 'n' => $n, 'out' => $out ), vgarco_export_parse_name( $file ) );
}
foreach ( array( 'Bao-Tang-hero.jpg', 'bao tang-hero.jpg', 'bảo-tàng-hero.jpg', 'x-hero.gif', 'x-hero.tif', 'x-hero', 'hero.jpg', 'x-tv-00.jpg', 'x-tv.jpg', 'x-banner.jpg', 'x-hero-2.jpg', '-hero.jpg', 'x--hero.jpg', 'x-Hero.jpg', 'x_hero.jpg', '' ) as $bad ) {
	same( "tên sai bị từ chối: '{$bad}'", null, vgarco_export_parse_name( $bad ) );
}

foreach (
	array(
		array( 4845, 2520, 'hero', array( 2109, 1097 ) ),
		array( 2560, 1097, 'hero', array( 2560, 1097 ) ),
		array( 1334, 572, 'hero', array( 1334, 572 ) ),
		array( 4000, 3000, 'the', array( 1200, 900 ) ),
		array( 1000, 760, 'the', array( 1000, 760 ) ),
		array( 3840, 2160, 'slide', array( 1600, 900 ) ),
		array( 4000, 3000, 'tv', array( 1800, 1350 ) ),
		array( 3000, 4000, 'tv', array( 1350, 1800 ) ),
		array( 1, 1, 'tv', array( 1, 1 ) ),
		array( 20000, 100, 'tv', array( 1800, 9 ) ),
	) as [ $w, $h, $role, $want ]
) {
	same( "kích thước xuất {$w}×{$h} ({$role})", $want, vgarco_export_target_size( $w, $h, $role ) );
}

check( 'tỉ lệ: hero 2560×1097 đạt', null === vgarco_export_ratio_note( 2560, 1097, 'hero' ) );
check( 'tỉ lệ: hero lệch dưới 3% đạt', null === vgarco_export_ratio_note( 2560, 1100, 'hero' ) );
check( 'tỉ lệ: hero 16:9 bị báo và nhắc cắt tay', str_contains( (string) vgarco_export_ratio_note( 1920, 1080, 'hero' ), '21:9' ) && str_contains( (string) vgarco_export_ratio_note( 1920, 1080, 'hero' ), 'cắt tay' ) );
check( 'tỉ lệ: thẻ 4:3 đạt, 3:2 bị báo, ảnh dọc bị báo', null === vgarco_export_ratio_note( 1200, 900, 'the' ) && null !== vgarco_export_ratio_note( 1200, 800, 'the' ) && null !== vgarco_export_ratio_note( 900, 1200, 'the' ) );
check( 'tỉ lệ: trình chiếu 16:9 đạt, 4:3 bị báo', null === vgarco_export_ratio_note( 1600, 900, 'slide' ) && null !== vgarco_export_ratio_note( 1200, 900, 'slide' ) );
check( 'tỉ lệ: thư viện không ràng buộc', null === vgarco_export_ratio_note( 900, 1800, 'tv' ) && null === vgarco_export_ratio_note( 3000, 100, 'tv' ) );
same( 'kích thước sau xoay: hướng 6 đổi chỗ', array( 20, 40 ), vgarco_export_oriented_size( 40, 20, 6 ) );
same( 'kích thước sau xoay: hướng 3 giữ nguyên', array( 40, 20 ), vgarco_export_oriented_size( 40, 20, 3 ) );

foreach ( array( 1 => 'TL', 2 => 'TR', 3 => 'BR', 4 => 'BL', 5 => 'TL', 6 => 'TR', 7 => 'BR', 8 => 'BL' ) as $o => $corner ) {
	$im = imagecreatetruecolor( 40, 20 );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 40, 90, 200 ) );
	imagefilledrectangle( $im, 0, 0, 9, 4, imagecolorallocate( $im, 255, 0, 0 ) );
	$r  = vgarco_export_orient( $im, $o );
	$w  = imagesx( $r );
	$h  = imagesy( $r );
	same( "hướng {$o}: kích thước", $o >= 5 ? array( 20, 40 ) : array( 40, 20 ), array( $w, $h ) );
	$pos = array( 'TL' => array( 1, 1 ), 'TR' => array( $w - 2, 1 ), 'BL' => array( 1, $h - 2 ), 'BR' => array( $w - 2, $h - 2 ) );
	foreach ( $pos as $name => [ $x, $y ] ) {
		check( "hướng {$o}: góc {$name} " . ( $name === $corner ? 'đỏ' : 'không đỏ' ), is_red( px( $r, $x, $y ) ) === ( $name === $corner ) );
	}
}

$big = imagecreatetruecolor( 4000, 3000 );
$res = vgarco_export_resize( $big, 'the' );
same( 'resize: thẻ 4000×3000 → 1200×900', array( 1200, 900 ), array( imagesx( $res ), imagesy( $res ) ) );
$small = imagecreatetruecolor( 800, 600 );
check( 'resize: ảnh nhỏ giữ nguyên, không phóng to', vgarco_export_resize( $small, 'the' ) === $small );

$smooth = imagecreatetruecolor( 600, 400 );
imagefilledrectangle( $smooth, 0, 0, 599, 399, imagecolorallocate( $smooth, 90, 120, 160 ) );
$enc = vgarco_export_encode( $smooth );
check( 'nén: ảnh nhẹ giữ q86 và đạt trần', 86 === $enc['quality'] && $enc['ok'] && strlen( $enc['bytes'] ) < 1572864 );
check( 'nén: là JPEG giải mã được, progressive (SOF2), không còn ghi chú CREATOR của GD', false !== imagecreatefromstring( $enc['bytes'] ) && str_contains( $enc['bytes'], "\xFF\xC2" ) && ! str_contains( $enc['bytes'], 'CREATOR' ) );
$noisy  = noise_image( 500, 400 );
$q86    = strlen( vgarco_export_encode( $noisy, PHP_INT_MAX )['bytes'] );
$tight  = vgarco_export_encode( $noisy, $q86 - 1000 );
check( 'nén: vượt trần thì hạ chất lượng từng bước 4 (86 → 82) và đạt trần', 82 === $tight['quality'] && $tight['ok'] && strlen( $tight['bytes'] ) <= $q86 - 1000 );
$impossible = vgarco_export_encode( $noisy, 1000 );
check( 'nén: không thể đạt trần thì dừng ở mức tối thiểu q70 và báo không đạt', 70 === $impossible['quality'] && ! $impossible['ok'] );
check( 'nén: trần mặc định là 1,5 MB (1.572.864 byte)', 1572864 === VGARCO_EXPORT_MAX_BYTES && 86 === VGARCO_EXPORT_QUALITY );

if ( function_exists( 'imageavif' ) ) {
	$a1 = vgarco_export_avif_kb( $smooth );
	$a2 = vgarco_export_avif_kb( $noisy );
	check( 'AVIF: ước lượng ra số KB dương; ảnh nhiễu nặng hơn ảnh phẳng', is_int( $a1 ) && is_int( $a2 ) && $a1 > 0 && $a2 > $a1 );
} else {
	same( 'AVIF: GD không có AVIF → null', null, vgarco_export_avif_kb( $smooth ) );
}

$icc_seg = static fn( string $data ): string => seg( 0xE2, "ICC_PROFILE\0\x01\x01" . $data );
$srgb    = with_segments( base_jpeg(), $icc_seg( "\0\0\0\0desc\0\0\0\0sRGB IEC61966-2.1\0\0" ) );
$p3      = with_segments( base_jpeg(), $icc_seg( "\0\0\0\0desc\0\0\0\0Display P3\0\0" ) );
check( 'màu: JPEG sRGB không bị báo', null === vgarco_export_color_note( $srgb ) );
check( 'màu: JPEG Display P3 bị báo', str_contains( (string) vgarco_export_color_note( $p3 ), 'không phải sRGB' ) );
check( 'màu: JPEG không có hồ sơ không bị báo', null === vgarco_export_color_note( base_jpeg() ) );
$png      = base_png();
$at       = strpos( $png, 'IDAT' ) - 4;
$png_p3   = substr( $png, 0, $at ) . png_chunk( 'iCCP', "Display P3\0\0zlib" ) . substr( $png, $at );
$png_srgb = substr( $png, 0, $at ) . png_chunk( 'iCCP', "sRGB IEC61966-2.1\0\0zlib" ) . substr( $png, $at );
check( 'màu: PNG Display P3 bị báo, PNG sRGB và PNG không hồ sơ thì không', null !== vgarco_export_color_note( $png_p3 ) && null === vgarco_export_color_note( $png_srgb ) && null === vgarco_export_color_note( $png ) );
if ( function_exists( 'gzcompress' ) ) {
	$iccp = static fn( string $profile ): string => substr( $png, 0, $at ) . png_chunk( 'iCCP', "Photoshop ICC profile\0\0" . gzcompress( $profile ) ) . substr( $png, $at );
	check( 'màu: PNG tên chung chung nhưng hồ sơ nén chứa sRGB → không báo', null === vgarco_export_color_note( $iccp( "\0\0\0\0desc\0\0\0\0sRGB IEC61966-2.1\0\0" ) ) );
	check( 'màu: PNG tên chung chung, hồ sơ nén là Adobe RGB → báo', null !== vgarco_export_color_note( $iccp( "\0\0\0\0desc\0\0\0\0Adobe RGB (1998)\0\0" ) ) );
	check( 'màu: PNG hồ sơ nén hỏng → báo (không đoán là sRGB)', null !== vgarco_export_color_note( substr( $png, 0, $at ) . png_chunk( 'iCCP', "Photoshop ICC profile\0\0khong-phai-zlib" ) . substr( $png, $at ) ) );
}
check( 'màu: dữ liệu rác không gây lỗi', null === vgarco_export_color_note( 'rác' ) && null === vgarco_export_color_note( '' ) );

$plan = vgarco_export_plan( array( 'b-hero.jpg', 'a-hero.jpg', 'a-hero.png', 'Sai Ten.jpg', 'a-tv-01.jpg', 'a-tv-1.jpg' ) );
same( 'plan: giữ các tệp hợp lệ, sắp theo tên', array( 'a-hero.jpg', 'a-tv-01.jpg', 'b-hero.jpg' ), array_column( $plan['items'], 'src' ) );
same( 'plan: 3 lỗi (trùng đầu ra hero, trùng đầu ra tv-01, tên sai)', 3, count( $plan['errors'] ) );
check( 'plan: lỗi nêu đúng tệp và lý do', (bool) preg_grep( '/a-hero\.png.*trùng tệp xuất a-hero\.jpg/u', $plan['errors'] ) && (bool) preg_grep( '/Sai Ten\.jpg.*tên không đúng/u', $plan['errors'] ) && (bool) preg_grep( '/a-tv-1\.jpg.*trùng/u', $plan['errors'] ) );
$many = array();
for ( $i = 1; $i <= 16; $i++ ) {
	$many[] = sprintf( 'p-tv-%02d.jpg', $i );
}
$plan = vgarco_export_plan( $many );
same( 'plan: ảnh thư viện thứ 16 bị từ chối, 15 ảnh đầu được giữ', array( 15, 1 ), array( count( $plan['items'] ), count( $plan['errors'] ) ) );
check( 'plan: lỗi quá 15 ảnh nêu dự án', str_contains( $plan['errors'][0] ?? '', 'quá 15 ảnh thư viện' ) && str_contains( $plan['errors'][0] ?? '', 'p-tv-16.jpg' ) );

$plan = vgarco_export_plan( array( 'b-tv-02.jpg', 'a-the.jpg', 'a-hero.jpg', 'a-slide.jpg', 'a-tv-10.jpg', 'a-tv-02.jpg' ) );
$rows = vgarco_export_csv_rows( $plan['items'] );
same( 'csv: tiêu đề', array( 'ma', 'tep', 'vai_tro', 'thu_tu', 'loai_anh', 'chu_thich_vi', 'chu_thich_de' ), $rows[0] );
same( 'csv: thứ tự dự án → hero, thẻ, trình chiếu, thư viện theo số', array( 'a-hero.jpg', 'a-the.jpg', 'a-slide.jpg', 'a-tv-02.jpg', 'a-tv-10.jpg', 'b-tv-02.jpg' ), array_column( array_slice( $rows, 1 ), 1 ) );
same( 'csv: thu_tu chỉ có ở ảnh thư viện; chú thích để trống', array( '', '', '', '2', '10', '2' ), array_column( array_slice( $rows, 1 ), 3 ) );
check( 'csv: chú thích để trống (phải điền thật)', ! array_filter( array_map( fn( $r ) => $r[5] . $r[6], array_slice( $rows, 1 ) ) ) );

$script = ROOT . '/scripts/content/export-images.php';
$run    = static function ( array $argv ) use ( $script ): array {
	$cmd  = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=gd', '-d', 'extension=mbstring', $script, ...$argv );
	$proc = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	$out  = stream_get_contents( $pipes[1] );
	$err  = stream_get_contents( $pipes[2] );
	return array( 'code' => proc_close( $proc ), 'out' => (string) $out, 'err' => (string) $err );
};
$tmp = sys_get_temp_dir() . '/vgarco-export-' . getmypid();
$rm  = static function ( string $dir ) use ( &$rm ): void {
	foreach ( glob( $dir . '/*' ) ?: array() as $f ) {
		is_dir( $f ) ? $rm( $f ) : unlink( $f );
	}
	@rmdir( $dir );
};
$rm( $tmp );
mkdir( "$tmp/in", 0777, true );

$marker = base_jpeg( 800, 400, true );
file_put_contents( "$tmp/in/bao-tang-hero.jpg", with_segments( base_jpeg( 2560, 1097 ), seg( 0xE1, exif_payload( 1 ) ) ) );
file_put_contents( "$tmp/in/bao-tang-the.png", png_with_metadata( base_png( 1600, 1200 ) ) );
file_put_contents( "$tmp/in/bao-tang-tv-01.jpg", with_segments( $marker, seg( 0xE1, exif_payload( 6 ) ) ) );
file_put_contents( "$tmp/in/bao-tang-tv-02.jpg", jpeg_bytes( noise_image( 700, 500 ), 95 ) );
file_put_contents( "$tmp/in/ghi-chu.txt", 'không phải ảnh' );
file_put_contents( "$tmp/in/ban-ve.psd", 'psd' );

$r = $run( array( "$tmp/in", "$tmp/out", "--csv=$tmp/out-images.csv" ) );
same( 'lệnh: thoát 0', 0, $r['code'] );
check( 'lệnh: báo xong 4 ảnh và bỏ qua tệp không phải ảnh', str_contains( $r['out'], 'Xong: 4 ảnh' ) && str_contains( $r['out'], 'bỏ qua: ghi-chu.txt' ) && str_contains( $r['out'], 'bỏ qua: ban-ve.psd' ) );
same( 'lệnh: đúng 4 tệp xuất, đều .jpg', array( 'bao-tang-hero.jpg', 'bao-tang-the.jpg', 'bao-tang-tv-01.jpg', 'bao-tang-tv-02.jpg' ), array_map( 'basename', glob( "$tmp/out/*" ) ?: array() ) );
$clean = true;
foreach ( glob( "$tmp/out/*" ) ?: array() as $f ) {
	$raw   = (string) file_get_contents( $f );
	$clean = $clean && str_starts_with( $raw, "\xFF\xD8" ) && ! str_contains( $raw, GPS_NEEDLE ) && ! str_contains( $raw, "Exif\0\0" ) && ! str_contains( $raw, 'PNG-XMP-NEEDLE' ) && ! str_contains( $raw, 'CREATOR' ) && strlen( $raw ) <= 1572864;
}
check( 'lệnh: mọi tệp xuất là JPEG, không còn GPS/EXIF/XMP, ≤ 1,5 MB', $clean );
same( 'lệnh: hero 2560×1097', array( 2560, 1097 ), array_slice( getimagesize( "$tmp/out/bao-tang-hero.jpg" ), 0, 2 ) );
same( 'lệnh: thẻ PNG 1600×1200 → JPEG 1200×900', array( 1200, 900, IMAGETYPE_JPEG ), array_slice( getimagesize( "$tmp/out/bao-tang-the.jpg" ), 0, 3 ) );
same( 'lệnh: thư viện hướng 6 đã xoay thẳng (400×800)', array( 400, 800 ), array_slice( getimagesize( "$tmp/out/bao-tang-tv-01.jpg" ), 0, 2 ) );
$up = imagecreatefromjpeg( "$tmp/out/bao-tang-tv-01.jpg" );
check( 'lệnh: góc đỏ của ảnh hướng 6 nằm ở trên phải', is_red( px( $up, 397, 2 ) ) && ! is_red( px( $up, 2, 2 ) ) );
$csv = (string) file_get_contents( "$tmp/out-images.csv" );
check( 'lệnh: images.csv có BOM, tiêu đề và 4 dòng đúng thứ tự', str_starts_with( $csv, "\xEF\xBB\xBF" ) && 5 === count( array_filter( explode( "\n", $csv ) ) ) && str_contains( $csv, 'bao-tang;bao-tang-hero.jpg;hero' ) && str_contains( $csv, 'bao-tang;bao-tang-tv-02.jpg;tv;2' ) );

$r = $run( array( "$tmp/in", "$tmp/out2", "--csv=$tmp/out-images.csv" ) );
check( 'lệnh: images.csv đã có → từ chối, thoát 1, không tạo thư mục xuất', 1 === $r['code'] && str_contains( $r['err'], 'không ghi đè' ) && ! is_dir( "$tmp/out2" ) );

mkdir( "$tmp/bad", 0777, true );
file_put_contents( "$tmp/bad/Bao-Tang-hero.jpg", $marker );
file_put_contents( "$tmp/bad/x-tv-00.jpg", $marker );
file_put_contents( "$tmp/bad/ok-hero.jpg", base_jpeg( 2560, 1097 ) );
file_put_contents( "$tmp/bad/hong-hero.jpg", 'đây là chữ, không phải ảnh' );
$r = $run( array( "$tmp/bad", "$tmp/out3" ) );
check( 'lệnh: tên sai/tệp hỏng → thoát 1, nêu cả 3 lỗi, không tạo gì', 1 === $r['code'] && str_contains( $r['err'], 'Có 3 lỗi' ) && str_contains( $r['err'], 'Bao-Tang-hero.jpg' ) && str_contains( $r['err'], 'x-tv-00.jpg' ) && str_contains( $r['err'], 'hong-hero.jpg: không phải ảnh' ) && ! is_dir( "$tmp/out3" ) );

$r = $run( array( "$tmp/in", "$tmp/out4", '--dry-run' ) );
check( 'lệnh: --dry-run thoát 0, in kế hoạch, không ghi tệp', 0 === $r['code'] && str_contains( $r['out'], 'XEM TRƯỚC' ) && str_contains( $r['out'], 'bao-tang-the.jpg' ) && ! is_dir( "$tmp/out4" ) );

mkdir( "$tmp/ratio", 0777, true );
file_put_contents( "$tmp/ratio/y-hero.jpg", base_jpeg( 1920, 1080 ) );
$r = $run( array( "$tmp/ratio", "$tmp/out5" ) );
check( 'lệnh: hero 16:9 → cảnh báo cắt tay, vẫn xuất, thoát 0', 0 === $r['code'] && str_contains( $r['out'], 'cắt tay' ) && is_file( "$tmp/out5/y-hero.jpg" ) );
$r = $run( array( "$tmp/ratio", "$tmp/out6", '--strict' ) );
check( 'lệnh: --strict → coi là lỗi, thoát 1, không ghi gì', 1 === $r['code'] && str_contains( $r['err'], 'cắt tay' ) && ! is_dir( "$tmp/out6" ) );

$r = $run( array() );
check( 'lệnh: thiếu đối số → thoát 2 kèm hướng dẫn', 2 === $r['code'] && str_contains( $r['err'], 'Cách dùng' ) );
$r = $run( array( "$tmp/khong-co", "$tmp/o", '--bay=1' ) );
check( 'lệnh: tùy chọn lạ → thoát 2', 2 === $r['code'] );
$r = $run( array( "$tmp/khong-co-thu-muc", "$tmp/o" ) );
check( 'lệnh: thư mục nguồn không có → thoát 2', 2 === $r['code'] && str_contains( $r['err'], 'Không thấy thư mục' ) );
mkdir( "$tmp/empty", 0777, true );
$r = $run( array( "$tmp/empty", "$tmp/o2" ) );
check( 'lệnh: thư mục không có ảnh → thoát 1', 1 === $r['code'] && str_contains( $r['err'], 'Không có ảnh' ) );

$rm( $tmp );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

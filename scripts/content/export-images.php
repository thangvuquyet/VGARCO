<?php
/**
 * Xuất ảnh master cho website (phương án mục 9.3 bước 3–4). Không cần WordPress; cần PHP 8.3 với gd (JPEG, PNG; AVIF nếu có).
 *
 *   php scripts/content/export-images.php <thư-mục-đã-cắt> <thư-mục-xuất> [--csv=images.csv] [--strict] [--dry-run]
 *
 * Thư mục đã cắt chứa ảnh đã CẮT TAY đúng tỉ lệ (hero 21:9, trình chiếu 16:9, thẻ 4:3), đặt tên theo mã dự án:
 *   <ma>-hero.jpg  <ma>-the.jpg  <ma>-slide.jpg  <ma>-tv-01.jpg  <ma>-tv-02.jpg ...   (đuôi jpg, jpeg hoặc png)
 * Mỗi ảnh được xoay theo EXIF, thu nhỏ vừa khung (không phóng to), nén JPEG chất lượng 86 (tự hạ nếu quá 1,5 MB), gỡ mọi siêu dữ liệu.
 * Công cụ KHÔNG tự cắt: chỉ báo ảnh lệch tỉ lệ. Tên sai hoặc lỗi thì không ghi gì cả; mọi lỗi báo một lượt.
 *
 *   --csv=<tệp>   ghi bộ khung images.csv (chú thích để trống: ảnh thư viện phải có chú thích thật). Không ghi đè tệp đã có.
 *   --strict      ảnh lệch tỉ lệ quy cách bị coi là lỗi (mặc định chỉ cảnh báo)
 *   --dry-run     chỉ kiểm tra và báo kế hoạch, không ghi tệp
 *
 * Mã thoát: 0 = xong; 1 = có lỗi (chưa ghi gì, hoặc có ảnh không đạt trần 1,5 MB); 2 = dùng sai lệnh hoặc thiếu gd.
 */

ini_set( 'memory_limit', '2048M' ); // ảnh toàn cảnh hàng chục nghìn điểm ảnh
define( 'ABSPATH', __DIR__ . '/' );
require_once dirname( __DIR__, 2 ) . '/wp-content/plugins/vgarco-core/inc/media-lib.php';
require_once __DIR__ . '/export-images-lib.php';

$args  = array_slice( $argv, 1 );
$opts  = array();
$paths = array();
foreach ( $args as $a ) {
	if ( str_starts_with( $a, '--' ) ) {
		[ $k, $v ] = array_pad( explode( '=', substr( $a, 2 ), 2 ), 2, true );
		$opts[ $k ] = $v;
	} else {
		$paths[] = $a;
	}
}
if ( 2 !== count( $paths ) || array_diff( array_keys( $opts ), array( 'csv', 'strict', 'dry-run' ) ) ) {
	fwrite( STDERR, "Cách dùng: php scripts/content/export-images.php <thư-mục-đã-cắt> <thư-mục-xuất> [--csv=images.csv] [--strict] [--dry-run]\n" );
	exit( 2 );
}
if ( ! extension_loaded( 'gd' ) || ! function_exists( 'imagejpeg' ) || ! function_exists( 'imagecreatefromstring' ) ) {
	fwrite( STDERR, "Thiếu phần mở rộng gd của PHP (cần JPEG và PNG).\n" );
	exit( 2 );
}
[ $in, $out ] = $paths;
$strict  = isset( $opts['strict'] );
$dry     = isset( $opts['dry-run'] );
$csv     = isset( $opts['csv'] ) && true !== $opts['csv'] ? (string) $opts['csv'] : null;
if ( ! is_dir( $in ) ) {
	fwrite( STDERR, "Không thấy thư mục: {$in}\n" );
	exit( 2 );
}

/* ---------- 1. Kiểm tra trước, chưa ghi gì ---------- */

$files   = array();
$ignored = array();
foreach ( scandir( $in ) as $f ) {
	if ( '.' === $f[0] || is_dir( "{$in}/{$f}" ) ) {
		continue;
	}
	if ( preg_match( '/\.(jpe?g|png)$/i', $f ) ) {
		$files[] = $f;
	} else {
		$ignored[] = $f;
	}
}
$plan   = vgarco_export_plan( $files );
$errors = $plan['errors'];
$notes  = array(); // tệp => ghi chú cảnh báo
foreach ( $plan['items'] as $i => $item ) {
	$path  = "{$in}/{$item['src']}";
	$size  = @getimagesize( $path );
	$bytes = (string) file_get_contents( $path );
	if ( ! $size || ! in_array( $size[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG ), true ) ) {
		$errors[] = "{$item['src']}: không phải ảnh JPEG/PNG hợp lệ";
		continue;
	}
	$orientation = IMAGETYPE_JPEG === $size[2] ? vgarco_jpeg_orientation( $bytes ) : 1;
	[ $w, $h ]   = vgarco_export_oriented_size( $size[0], $size[1], $orientation );
	$plan['items'][ $i ] += array( 'w' => $w, 'h' => $h, 'orientation' => $orientation, 'bytes' => strlen( $bytes ) );
	$note = vgarco_export_ratio_note( $w, $h, $item['role'] );
	if ( $note ) {
		if ( $strict ) {
			$errors[] = "{$item['src']}: {$note}";
		} else {
			$notes[ $item['src'] ][] = $note;
		}
	}
	$color = vgarco_export_color_note( $bytes );
	if ( $color ) {
		$notes[ $item['src'] ][] = $color;
	}
}
if ( $csv && is_file( $csv ) ) {
	$errors[] = "{$csv}: đã có — không ghi đè (giữ chú thích đã điền); xóa hoặc đổi tên tệp rồi chạy lại";
}
foreach ( $ignored as $f ) {
	echo "  bỏ qua: {$f} (không phải JPEG/PNG)\n";
}
if ( $errors ) {
	fwrite( STDERR, "Có " . count( $errors ) . " lỗi, CHƯA ghi gì:\n" );
	foreach ( $errors as $e ) {
		fwrite( STDERR, "  ✗ {$e}\n" );
	}
	exit( 1 );
}
if ( ! $plan['items'] ) {
	fwrite( STDERR, "Không có ảnh nào trong {$in}\n" );
	exit( 1 );
}

/* ---------- 2. Xuất ---------- */

if ( ! $dry && ! is_dir( $out ) && ! mkdir( $out, 0777, true ) ) {
	fwrite( STDERR, "Không tạo được thư mục xuất: {$out}\n" );
	exit( 1 );
}
$total   = 0;
$over    = 0;
$warns   = 0;
$bad     = 0;
$ceiling = 0;
$written = array();
echo ( $dry ? 'XEM TRƯỚC (không ghi tệp): ' : 'Xuất ' ) . count( $plan['items'] ) . " ảnh từ {$in} sang {$out}\n";
foreach ( $plan['items'] as $item ) {
	$src   = "{$in}/{$item['src']}";
	$spec  = vgarco_export_roles()[ $item['role'] ];
	[ $tw, $th ] = vgarco_export_target_size( $item['w'], $item['h'], $item['role'] );
	$line  = sprintf( '  %-46s %-5s %5d×%-5d → %4d×%-4d', $item['out'], $item['role'], $item['w'], $item['h'], $tw, $th );
	if ( $dry ) {
		echo $line . "\n";
	} else {
		$im = imagecreatefromstring( (string) file_get_contents( $src ) );
		if ( ! $im ) {
			fwrite( STDERR, "  ✗ {$item['src']}: không đọc được ảnh (hỏng hoặc quá lớn so với bộ nhớ)\n" );
			++$bad;
			continue;
		}
		$im  = vgarco_export_orient( $im, $item['orientation'] );
		$im  = vgarco_export_resize( $im, $item['role'] );
		$enc = vgarco_export_encode( $im );
		file_put_contents( "{$out}/{$item['out']}", $enc['bytes'] );
		$kb   = (int) round( strlen( $enc['bytes'] ) / 1024 );
		$avif = vgarco_export_avif_kb( $im );
		$total += strlen( $enc['bytes'] );
		$written[] = $item;
		echo sprintf( '%s %5d KB q%d%s', $line, $kb, $enc['quality'], null !== $avif ? " AVIF≈{$avif} KB" : '' ) . "\n";
		if ( ! $enc['ok'] ) {
			fwrite( STDERR, "    ✗ vẫn quá 1,5 MB ở chất lượng tối thiểu q" . VGARCO_EXPORT_MIN_QUALITY . ": giảm kích thước ảnh nguồn\n" );
			++$over;
		}
		if ( null !== $avif && $avif > $spec['ceiling_kb'] ) {
			echo "    ⚠ AVIF ước lượng {$avif} KB vượt trần {$spec['ceiling_kb']} KB của mục 9.2 (quyết định trần còn mở): cân nhắc ảnh ít chi tiết hơn hoặc hạ chất lượng riêng ảnh này\n";
			++$ceiling;
		}
		if ( $enc['quality'] < VGARCO_EXPORT_QUALITY ) {
			echo '    ⚠ đã hạ chất lượng JPEG xuống q' . $enc['quality'] . " để master ≤ 1,5 MB\n";
		}
	}
	foreach ( $notes[ $item['src'] ] ?? array() as $n ) {
		echo "    ⚠ {$n}\n";
		++$warns;
	}
}

/* ---------- 3. images.csv ---------- */

if ( $csv && ! $dry ) {
	$fh = fopen( $csv, 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" ); // BOM để Excel nhận UTF-8
	foreach ( vgarco_export_csv_rows( $plan['items'] ) as $row ) {
		fputcsv( $fh, $row, ';' );
	}
	fclose( $fh );
	echo "Đã ghi bộ khung {$csv}: điền chu_thich_vi cho ảnh thư viện (tv), chu_thich_de nếu có, loai_anh nếu là ảnh công trình.\n";
}

if ( $dry ) {
	echo $warns ? "Có {$warns} cảnh báo.\n" : "Không có cảnh báo.\n";
	exit( 0 );
}
printf( "\nXong: %d ảnh, %.1f MB; %d cảnh báo tỉ lệ/màu, %d ảnh AVIF ước lượng vượt trần.\n", count( $written ), $total / 1048576, $warns, $ceiling );
exit( $bad || $over ? 1 : 0 );

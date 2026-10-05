<?php
/**
 * Gỡ siêu dữ liệu khỏi ảnh tải lên — phần THUẦN, kiểm thử bằng ảnh thật (tests/php/media.php).
 *
 * Vì sao: ảnh chụp bằng điện thoại mang tọa độ GPS, thời điểm chụp, tên thiết bị. WordPress giữ NGUYÊN tệp gốc đã tải lên và phục vụ
 * công khai ngay trong thư mục uploads (tên suy ra được từ bản "-scaled"), trong khi các bản thu nhỏ do GD sinh ra đã sạch. Với công trình
 * quân sự và ngoại giao, tọa độ lộ ra từ tệp gốc là sự cố an ninh (đo trên ảnh thật của dự án: 22 trong 113 ảnh JPEG có GPS).
 *
 * JPEG: gỡ không mất chất lượng (không giải mã lại) mọi đoạn EXIF, XMP, IPTC, MPF, ghi chú; giữ JFIF, hồ sơ màu ICC và Adobe (đổi màu sai nếu mất).
 * PNG: gỡ các khối eXIf, tEXt, zTXt, iTXt, tIME.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chất lượng mã hóa AVIF và WebP (thang 0–100 của WordPress). Đo trên 16 bộ ảnh thật của dự án (plan Phụ lục E): mặc định của WordPress là 82
 * cho AVIF (86 cho WebP); ở mức 82 chỉ 8/16 ảnh đầu trang đạt trần 350 KB của mục 9.2. Mức 55 trông ngang JPEG 82 ở ảnh chi tiết nhất (hạt đá granite,
 * tán cây) mà nhẹ hơn khoảng 35% (13/16 ảnh đạt trần; vài ảnh render chi tiết vượt nhẹ). JPEG giữ mặc định của WordPress (82).
 * Số đo bằng GD/libavif và chỉ cho AVIF; WebP (72) chưa đo riêng. Phải đo lại bằng bộ mã hóa thật của máy chủ ở ngày N16 (mục 9.3 bước 5).
 */
const VGARCO_AVIF_QUALITY = 55;
const VGARCO_WEBP_QUALITY = 72;

/**
 * Bộ lọc wp_editor_set_quality: chất lượng theo định dạng đầu ra.
 *
 * @param int    $default Chất lượng mặc định của WordPress cho định dạng đó.
 * @param string $mime    Định dạng đầu ra (image/jpeg, image/avif, image/webp...).
 */
function vgarco_image_quality( $default, $mime = '' ): int {
	return match ( $mime ) {
		'image/avif' => VGARCO_AVIF_QUALITY,
		'image/webp' => VGARCO_WEBP_QUALITY,
		default      => (int) $default,
	};
}

/**
 * Tách các đoạn của JPEG cho tới đoạn quét ảnh (SOS).
 *
 * @return array{segments: array<int, array{0:int,1:int}>, scan: int}|null segments = [mã đánh dấu, vị trí]; scan = vị trí của SOS. null nếu không phải JPEG hợp lệ.
 */
function vgarco_jpeg_parse( string $jpeg ): ?array {
	$len = strlen( $jpeg );
	if ( $len < 4 || "\xFF\xD8" !== substr( $jpeg, 0, 2 ) ) {
		return null;
	}
	$segments = array();
	$pos      = 2;
	while ( $pos + 2 <= $len ) {
		if ( "\xFF" !== $jpeg[ $pos ] ) {
			return null;
		}
		$marker = ord( $jpeg[ $pos + 1 ] );
		if ( 0xFF === $marker ) { // byte đệm
			++$pos;
			continue;
		}
		if ( 0xDA === $marker ) {
			return array( 'segments' => $segments, 'scan' => $pos );
		}
		if ( 0xD9 === $marker ) { // kết thúc ảnh trước khi có dữ liệu quét
			return null;
		}
		if ( 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) { // đoạn không có độ dài
			$segments[] = array( $marker, $pos );
			$pos       += 2;
			continue;
		}
		if ( $pos + 4 > $len ) {
			return null;
		}
		$size = ( ord( $jpeg[ $pos + 2 ] ) << 8 ) | ord( $jpeg[ $pos + 3 ] );
		if ( $size < 2 || $pos + 2 + $size > $len ) {
			return null;
		}
		$segments[] = array( $marker, $pos );
		$pos       += 2 + $size;
	}
	return null;
}

/** Độ dài tổng (cả hai byte đánh dấu) của đoạn tại $pos. */
function vgarco_jpeg_segment_length( string $jpeg, int $marker, int $pos ): int {
	if ( 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
		return 2;
	}
	return 2 + ( ( ord( $jpeg[ $pos + 2 ] ) << 8 ) | ord( $jpeg[ $pos + 3 ] ) );
}

/**
 * Hướng xoay EXIF (1–8) của JPEG; 1 (không xoay) nếu không có hoặc dữ liệu hỏng. Tự đọc cấu trúc TIFF, không cần phần mở rộng exif của PHP.
 */
function vgarco_jpeg_orientation( string $jpeg ): int {
	$parsed = vgarco_jpeg_parse( $jpeg );
	if ( ! $parsed ) {
		return 1;
	}
	foreach ( $parsed['segments'] as [ $marker, $pos ] ) {
		if ( 0xE1 !== $marker || 'Exif' . "\0\0" !== substr( $jpeg, $pos + 4, 6 ) ) {
			continue;
		}
		$tiff = substr( $jpeg, $pos + 10, vgarco_jpeg_segment_length( $jpeg, $marker, $pos ) - 10 );
		if ( strlen( $tiff ) < 8 ) {
			return 1;
		}
		$order = substr( $tiff, 0, 2 );
		if ( 'II' !== $order && 'MM' !== $order ) {
			return 1;
		}
		$u16 = static fn( string $s ): int => (int) unpack( 'II' === $order ? 'v' : 'n', $s )[1];
		$u32 = static fn( string $s ): int => (int) unpack( 'II' === $order ? 'V' : 'N', $s )[1];
		if ( 42 !== $u16( substr( $tiff, 2, 2 ) ) ) {
			return 1;
		}
		$ifd = $u32( substr( $tiff, 4, 4 ) );
		if ( $ifd < 8 || $ifd + 2 > strlen( $tiff ) ) {
			return 1;
		}
		$count = $u16( substr( $tiff, $ifd, 2 ) );
		for ( $i = 0; $i < $count && $ifd + 2 + ( $i + 1 ) * 12 <= strlen( $tiff ); $i++ ) {
			$entry = substr( $tiff, $ifd + 2 + $i * 12, 12 );
			if ( 0x0112 === $u16( substr( $entry, 0, 2 ) ) && 3 === $u16( substr( $entry, 2, 2 ) ) ) {
				$value = $u16( substr( $entry, 8, 2 ) );
				return ( $value >= 1 && $value <= 8 ) ? $value : 1;
			}
		}
		return 1;
	}
	return 1;
}

/**
 * Bỏ mọi siêu dữ liệu khỏi JPEG mà không giải mã lại ảnh: dữ liệu quét giữ nguyên từng byte.
 *
 * @return string|null JPEG đã gỡ; null nếu không phải JPEG hợp lệ (người gọi phải từ chối tệp, không được để nguyên).
 */
function vgarco_jpeg_strip_metadata( string $jpeg ): ?string {
	$parsed = vgarco_jpeg_parse( $jpeg );
	if ( ! $parsed ) {
		return null;
	}
	$out = "\xFF\xD8";
	foreach ( $parsed['segments'] as [ $marker, $pos ] ) {
		$length = vgarco_jpeg_segment_length( $jpeg, $marker, $pos );
		$keep   = true;
		if ( $marker >= 0xE0 && $marker <= 0xEF ) { // APPn: chỉ giữ JFIF, ICC, Adobe
			$payload = substr( $jpeg, $pos + 4, 12 );
			$keep    = ( 0xE0 === $marker && str_starts_with( $payload, "JFIF\0" ) )
				|| ( 0xE2 === $marker && str_starts_with( $payload, "ICC_PROFILE\0" ) )
				|| ( 0xEE === $marker && str_starts_with( $payload, 'Adobe' ) );
		} elseif ( 0xFE === $marker ) { // ghi chú
			$keep = false;
		}
		if ( $keep ) {
			$out .= substr( $jpeg, $pos, $length );
		}
	}
	return $out . substr( $jpeg, $parsed['scan'] );
}

/**
 * Bỏ các khối siêu dữ liệu khỏi PNG (eXIf, tEXt, zTXt, iTXt, tIME); mọi khối khác giữ nguyên từng byte.
 *
 * @return string|null null nếu không phải PNG hợp lệ.
 */
function vgarco_png_strip_metadata( string $png ): ?string {
	$signature = "\x89PNG\r\n\x1A\n";
	$len       = strlen( $png );
	if ( $len < 33 || ! str_starts_with( $png, $signature ) ) {
		return null;
	}
	$out  = $signature;
	$pos  = 8;
	$seen = false;
	while ( $pos + 12 <= $len ) {
		$size = (int) unpack( 'N', substr( $png, $pos, 4 ) )[1];
		$type = substr( $png, $pos + 4, 4 );
		$end  = $pos + 12 + $size;
		if ( $end > $len ) {
			return null;
		}
		if ( ! in_array( $type, array( 'eXIf', 'tEXt', 'zTXt', 'iTXt', 'tIME' ), true ) ) {
			$out .= substr( $png, $pos, 12 + $size );
		}
		$seen = $seen || 'IEND' === $type;
		$pos  = $end;
		if ( 'IEND' === $type ) {
			break;
		}
	}
	return $seen ? $out : null;
}

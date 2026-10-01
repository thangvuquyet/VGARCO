<?php
/**
 * Dựng ảnh thử có siêu dữ liệu: JPEG và PNG do GD sinh ra, chèn thêm EXIF có tọa độ GPS giả, XMP, IPTC, MPF, ghi chú, hồ sơ màu ICC.
 * Dùng chung cho tests/php/media.php (hàm thuần) và tests/wp-lab/it_media.php (WordPress thật). Cần phần mở rộng gd.
 */

const GPS_NEEDLE = 'KINH-DO-GPS-10.762622N-106.660172E';

/** Một đoạn JPEG: 0xFF, mã đánh dấu, độ dài (gồm 2 byte độ dài), nội dung. */
function seg( int $marker, string $payload ): string {
	return "\xFF" . chr( $marker ) . pack( 'n', strlen( $payload ) + 2 ) . $payload;
}

/** APP1 EXIF: Make (chứa chuỗi cần tìm) và Orientation, theo thứ tự byte $order (MM hoặc II). */
function exif_payload( int $orientation, string $order = 'MM', string $needle = GPS_NEEDLE, bool $with_orientation = true ): string {
	$p16     = static fn( int $v ): string => pack( 'MM' === $order ? 'n' : 'v', $v );
	$p32     = static fn( int $v ): string => pack( 'MM' === $order ? 'N' : 'V', $v );
	$make    = $needle . "\0";
	$entries = $with_orientation ? 2 : 1;
	$data    = 8 + 2 + $entries * 12 + 4;
	$tiff    = $order . $p16( 42 ) . $p32( 8 ) . $p16( $entries )
		. $p16( 0x010F ) . $p16( 2 ) . $p32( strlen( $make ) ) . $p32( $data );
	if ( $with_orientation ) {
		$tiff .= $p16( 0x0112 ) . $p16( 3 ) . $p32( 1 ) . $p16( $orientation ) . "\0\0";
	}
	return "Exif\0\0" . $tiff . $p32( 0 ) . $make;
}

/**
 * JPEG do GD sinh ra. Với $marker = true, góc trên trái ($w/4 × $h/4 điểm đầu tiên) tô đỏ thuần, còn lại là màu xanh dương nhạt:
 * dùng để kiểm tra ảnh đã được xoay đúng chiều.
 */
function base_jpeg( int $w = 120, int $h = 60, bool $marker = false ): string {
	$im = imagecreatetruecolor( $w, $h );
	if ( $marker ) {
		imagefill( $im, 0, 0, imagecolorallocate( $im, 40, 90, 200 ) );
		imagefilledrectangle( $im, 0, 0, intdiv( $w, 4 ), intdiv( $h, 4 ), imagecolorallocate( $im, 255, 0, 0 ) );
	} else {
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				imagesetpixel( $im, $x, $y, imagecolorallocate( $im, ( $x * 2 ) % 256, ( $y * 4 ) % 256, ( $x * $y ) % 256 ) );
			}
		}
	}
	ob_start();
	imagejpeg( $im, null, 90 );
	return (string) ob_get_clean();
}

/** Chèn các đoạn $segments ngay sau APP0 (JFIF) của ảnh GD. */
function with_segments( string $jpeg, string $segments ): string {
	if ( "\xFF\xD8\xFF\xE0" !== substr( $jpeg, 0, 4 ) || 'JFIF' !== substr( $jpeg, 6, 4 ) ) {
		throw new RuntimeException( 'ảnh GD không bắt đầu bằng JFIF như mong đợi' );
	}
	$app0_len = 2 + unpack( 'n', substr( $jpeg, 4, 2 ) )[1];
	return substr( $jpeg, 0, 2 + $app0_len ) . $segments . substr( $jpeg, 2 + $app0_len );
}

/** Một khối PNG (độ dài, loại, dữ liệu, CRC). */
function png_chunk( string $type, string $data ): string {
	return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
}

/** PNG do GD sinh ra (có góc trên trái đỏ thuần nếu $marker). */
function base_png( int $w = 40, int $h = 30 ): string {
	$im = imagecreatetruecolor( $w, $h );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 60, 30 ) );
	ob_start();
	imagepng( $im );
	return (string) ob_get_clean();
}

/** Chèn các khối siêu dữ liệu vào PNG ngay trước IDAT: tEXt (tọa độ), eXIf, iTXt (XMP), tIME, zTXt. */
function png_with_metadata( string $png ): string {
	$at   = strpos( $png, 'IDAT' ) - 4;
	$meta = png_chunk( 'tEXt', "Comment\0" . GPS_NEEDLE )
		. png_chunk( 'eXIf', exif_payload( 6, 'MM', GPS_NEEDLE . '-PNG' ) )
		. png_chunk( 'iTXt', "XML:com.adobe.xmp\0\0\0\0\0PNG-XMP-NEEDLE" )
		. png_chunk( 'tIME', "\x07\xEA\x0A\x01\x08\x00\x00" )
		. png_chunk( 'zTXt', "k\0\0zlib-bytes" );
	return substr( $png, 0, $at ) . $meta . substr( $png, $at );
}

<?php
/**
 * Mã bảo mật của biểu mẫu ứng tuyển (mục 8.3 bước 0 và 6): 5 ký tự, ảnh PNG do máy chủ vẽ bằng GD.
 * Mã KHÔNG bao giờ nằm trong HTML hay JSON dạng chữ — chỉ có ảnh; máy chủ chỉ lưu băm của mã.
 * Bỏ các ký tự dễ nhầm (0/O, 1/I/L) để người thật đọc được ngay lần đầu.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

const VGARCO_CAPTCHA_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const VGARCO_CAPTCHA_LENGTH   = 5;

/**
 * Sinh mã ngẫu nhiên bằng nguồn ngẫu nhiên mật mã học.
 */
function vgarco_captcha_code(): string {
	$code = '';
	for ( $i = 0; $i < VGARCO_CAPTCHA_LENGTH; $i++ ) {
		$code .= VGARCO_CAPTCHA_ALPHABET[ random_int( 0, strlen( VGARCO_CAPTCHA_ALPHABET ) - 1 ) ];
	}
	return $code;
}

/**
 * Chuẩn hóa câu trả lời của người dùng để so sánh: bỏ khoảng trắng, không phân biệt hoa thường.
 * Chữ thường của ký tự nhìn giống nhau (o/0...) không được sửa hộ — alphabet đã loại các ký tự đó.
 */
function vgarco_captcha_normalize( string $answer ): string {
	return strtoupper( preg_replace( '/\s+/', '', $answer ) ?? '' );
}

/**
 * Băm mã để lưu (không lưu chính mã). Muối gắn với website nên băm không dùng lại được nơi khác.
 */
function vgarco_captcha_hash( string $code, string $salt ): string {
	return hash_hmac( 'sha256', vgarco_captcha_normalize( $code ), $salt );
}

/**
 * Vẽ mã thành ảnh PNG (data URI): mỗi ký tự lệch vị trí và nghiêng khác nhau, có đường nhiễu và chấm nhiễu.
 * Không có GD thì trả SVG dự phòng (kém chống máy đọc hơn nhưng vẫn dùng được cho người).
 */
function vgarco_captcha_image( string $code ): string {
	if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagestring' ) ) {
		return vgarco_captcha_svg( $code );
	}

	$width  = 180;
	$height = 60;
	$img    = imagecreatetruecolor( $width, $height );
	$bg     = imagecolorallocate( $img, 241, 242, 238 );
	imagefilledrectangle( $img, 0, 0, $width, $height, $bg );

	// Nhiễu nền: đường và chấm màu gần màu nền, không đủ đậm để che mã.
	for ( $i = 0; $i < 6; $i++ ) {
		$shade = imagecolorallocate( $img, random_int( 150, 205 ), random_int( 150, 205 ), random_int( 150, 205 ) );
		imageline( $img, random_int( 0, $width ), random_int( 0, $height ), random_int( 0, $width ), random_int( 0, $height ), $shade );
	}
	for ( $i = 0; $i < 90; $i++ ) {
		imagesetpixel( $img, random_int( 0, $width - 1 ), random_int( 0, $height - 1 ), imagecolorallocate( $img, random_int( 120, 200 ), random_int( 120, 200 ), random_int( 120, 200 ) ) );
	}

	// Không dựa vào FreeType (có thể thiếu): vẽ ký tự bằng phông dựng sẵn của GD vào ảnh nhỏ rồi phóng to, mỗi ký tự một cỡ.
	$step = (int) floor( ( $width - 20 ) / VGARCO_CAPTCHA_LENGTH );
	foreach ( str_split( $code ) as $i => $char ) {
		$tile  = imagecreatetruecolor( 12, 18 );
		$clear = imagecolorallocate( $tile, 241, 242, 238 );
		imagefilledrectangle( $tile, 0, 0, 12, 18, $clear );
		imagestring( $tile, 5, 2, 1, $char, imagecolorallocate( $tile, random_int( 20, 90 ), random_int( 20, 90 ), random_int( 20, 90 ) ) );

		$size = random_int( 34, 42 );
		$top  = random_int( 2, max( 2, $height - $size - 2 ) );
		imagecopyresized( $img, $tile, 10 + $i * $step + random_int( -3, 3 ), $top, 0, 0, (int) ( $size * 0.75 ), $size, 12, 18 );
		imagedestroy( $tile );
	}

	// Một nét nhiễu đậm cắt ngang các ký tự.
	$stroke = imagecolorallocate( $img, random_int( 40, 100 ), random_int( 40, 100 ), random_int( 40, 100 ) );
	imageline( $img, 0, random_int( 15, 45 ), $width, random_int( 15, 45 ), $stroke );

	ob_start();
	imagepng( $img );
	$png = (string) ob_get_clean();

	return 'data:image/png;base64,' . base64_encode( $png );
}

/**
 * Ảnh SVG dự phòng khi máy chủ thiếu GD.
 */
function vgarco_captcha_svg( string $code ): string {
	$chars = '';
	foreach ( str_split( $code ) as $i => $char ) {
		$chars .= sprintf(
			'<text x="%d" y="%d" font-size="%d" font-family="monospace" font-weight="700" fill="#2a2f2c" transform="rotate(%d %d 36)">%s</text>',
			14 + $i * 33,
			random_int( 38, 46 ),
			random_int( 30, 38 ),
			random_int( -14, 14 ),
			14 + $i * 33,
			htmlspecialchars( $char, ENT_QUOTES )
		);
	}
	$noise = '';
	for ( $i = 0; $i < 6; $i++ ) {
		$noise .= sprintf( '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#9aa09b" stroke-width="1"/>', random_int( 0, 180 ), random_int( 0, 60 ), random_int( 0, 180 ), random_int( 0, 60 ) );
	}
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="60" viewBox="0 0 180 60"><rect width="180" height="60" fill="#f1f2ee"/>' . $noise . $chars . '</svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

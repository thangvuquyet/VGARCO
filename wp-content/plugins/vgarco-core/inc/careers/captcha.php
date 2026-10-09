<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_CAPTCHA_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const VGARCO_CAPTCHA_LENGTH   = 5;

function vgarco_captcha_code(): string {
	$code = '';
	for ( $i = 0; $i < VGARCO_CAPTCHA_LENGTH; $i++ ) {
		$code .= VGARCO_CAPTCHA_ALPHABET[ random_int( 0, strlen( VGARCO_CAPTCHA_ALPHABET ) - 1 ) ];
	}
	return $code;
}

function vgarco_captcha_normalize( string $answer ): string {
	return strtoupper( preg_replace( '/\s+/', '', $answer ) ?? '' );
}

function vgarco_captcha_hash( string $code, string $salt ): string {
	return hash_hmac( 'sha256', vgarco_captcha_normalize( $code ), $salt );
}

function vgarco_captcha_image( string $code ): string {
	if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagestring' ) ) {
		return vgarco_captcha_svg( $code );
	}

	$width  = 180;
	$height = 60;
	$img    = imagecreatetruecolor( $width, $height );
	$bg     = imagecolorallocate( $img, 241, 242, 238 );
	imagefilledrectangle( $img, 0, 0, $width, $height, $bg );

	for ( $i = 0; $i < 6; $i++ ) {
		$shade = imagecolorallocate( $img, random_int( 150, 205 ), random_int( 150, 205 ), random_int( 150, 205 ) );
		imageline( $img, random_int( 0, $width ), random_int( 0, $height ), random_int( 0, $width ), random_int( 0, $height ), $shade );
	}
	for ( $i = 0; $i < 90; $i++ ) {
		imagesetpixel( $img, random_int( 0, $width - 1 ), random_int( 0, $height - 1 ), imagecolorallocate( $img, random_int( 120, 200 ), random_int( 120, 200 ), random_int( 120, 200 ) ) );
	}

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

	$stroke = imagecolorallocate( $img, random_int( 40, 100 ), random_int( 40, 100 ), random_int( 40, 100 ) );
	imageline( $img, 0, random_int( 15, 45 ), $width, random_int( 15, 45 ), $stroke );

	ob_start();
	imagepng( $img );
	$png = (string) ob_get_clean();

	return 'data:image/png;base64,' . base64_encode( $png );
}

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

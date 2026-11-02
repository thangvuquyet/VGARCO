<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_AVIF_QUALITY = 55;
const VGARCO_WEBP_QUALITY = 72;

const VGARCO_AVIF_QUALITY_STEPS = array( 55, 50, 45 );

function vgarco_image_ceiling_bytes( int $width, int $height ): int {
	$long  = max( $width, $height );
	$short = min( $width, $height );
	if ( $short <= 0 ) {
		return 150 * 1024;
	}
	$ratio = $long / $short;
	if ( abs( $ratio / ( 21 / 9 ) - 1 ) <= 0.03 ) {
		return 350 * 1024;
	}
	if ( abs( $ratio / ( 16 / 9 ) - 1 ) <= 0.03 ) {
		return 250 * 1024;
	}
	return ( $long >= 1500 ? 300 : 150 ) * 1024;
}

function vgarco_pick_avif_quality( callable $measure, int $ceiling, array $steps = VGARCO_AVIF_QUALITY_STEPS ): array {
	$result = array( 'quality' => $steps[0] ?? VGARCO_AVIF_QUALITY, 'bytes' => null, 'fits' => null );
	foreach ( $steps as $quality ) {
		$bytes = $measure( $quality );
		if ( null === $bytes ) {
			return array( 'quality' => $steps[0], 'bytes' => null, 'fits' => null );
		}
		$result = array( 'quality' => $quality, 'bytes' => $bytes, 'fits' => $bytes <= $ceiling );
		if ( $result['fits'] ) {
			break;
		}
	}
	return $result;
}

function &vgarco_image_quality_state(): array {
	static $state = array( 'force' => null, 'ctx' => null );
	return $state;
}

function vgarco_image_quality( $default, $mime = '', $size = array() ): int {
	if ( 'image/avif' === $mime ) {
		$state = &vgarco_image_quality_state();
		if ( null !== $state['force'] ) {
			return (int) $state['force'];
		}
		$ctx = $state['ctx'];
		if ( is_array( $ctx ) ) {
			if ( null === $ctx['quality'] && is_callable( $ctx['resolve'] ?? null ) ) {
				$state['ctx']['quality'] = VGARCO_AVIF_QUALITY;
					$state['ctx']['quality'] = (int) ( $ctx['resolve'] )();
				$ctx                     = $state['ctx'];
			}
			$width = (int) ( $size['width'] ?? 0 );
			if ( null !== $ctx['quality'] && ( $width <= 0 || $ctx['width'] <= 0 || $width >= 0.6 * $ctx['width'] ) ) {
				return (int) $ctx['quality'];
			}
		}
	}
	return match ( $mime ) {
		'image/avif' => VGARCO_AVIF_QUALITY,
		'image/webp' => VGARCO_WEBP_QUALITY,
		default      => (int) $default,
	};
}

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
		if ( 0xFF === $marker ) {
			++$pos;
			continue;
		}
		if ( 0xDA === $marker ) {
			return array( 'segments' => $segments, 'scan' => $pos );
		}
		if ( 0xD9 === $marker ) {
			return null;
		}
		if ( 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
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

function vgarco_jpeg_segment_length( string $jpeg, int $marker, int $pos ): int {
	if ( 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
		return 2;
	}
	return 2 + ( ( ord( $jpeg[ $pos + 2 ] ) << 8 ) | ord( $jpeg[ $pos + 3 ] ) );
}

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

function vgarco_jpeg_strip_metadata( string $jpeg ): ?string {
	$parsed = vgarco_jpeg_parse( $jpeg );
	if ( ! $parsed ) {
		return null;
	}
	$out = "\xFF\xD8";
	foreach ( $parsed['segments'] as [ $marker, $pos ] ) {
		$length = vgarco_jpeg_segment_length( $jpeg, $marker, $pos );
		$keep   = true;
		if ( $marker >= 0xE0 && $marker <= 0xEF ) {
			$payload = substr( $jpeg, $pos + 4, 12 );
			$keep    = ( 0xE0 === $marker && str_starts_with( $payload, "JFIF\0" ) )
				|| ( 0xE2 === $marker && str_starts_with( $payload, "ICC_PROFILE\0" ) )
				|| ( 0xEE === $marker && str_starts_with( $payload, 'Adobe' ) );
		} elseif ( 0xFE === $marker ) {
			$keep = false;
		}
		if ( $keep ) {
			$out .= substr( $jpeg, $pos, $length );
		}
	}
	return $out . substr( $jpeg, $parsed['scan'] );
}

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

<?php

const VGARCO_EXPORT_QUALITY       = 86;
const VGARCO_EXPORT_MIN_QUALITY   = 70;
const VGARCO_EXPORT_QUALITY_STEP  = 4;
const VGARCO_EXPORT_MAX_BYTES     = 1572864;
const VGARCO_EXPORT_RATIO_TOL     = 0.03;
const VGARCO_EXPORT_MAX_GALLERY   = 15;

function vgarco_export_roles(): array {
	return array(
		'hero'  => array( 'max' => array( 2560, 1097 ), 'ratio' => 21 / 9, 'ceiling_kb' => 350, 'label' => '21:9' ),
		'slide' => array( 'max' => array( 1600, 900 ), 'ratio' => 16 / 9, 'ceiling_kb' => 250, 'label' => '16:9' ),
		'the'   => array( 'max' => array( 1200, 900 ), 'ratio' => 4 / 3, 'ceiling_kb' => 150, 'label' => '4:3' ),
		'tv'    => array( 'max' => array( 1800, 1800 ), 'ratio' => null, 'ceiling_kb' => 300, 'label' => '' ),
	);
}

function vgarco_export_parse_name( string $file ): ?array {
	$info = pathinfo( $file );
	if ( ! isset( $info['extension'] ) || ! in_array( strtolower( $info['extension'] ), array( 'jpg', 'jpeg', 'png' ), true ) ) {
		return null;
	}
	if ( ! preg_match( '/^([a-z0-9]+(?:-[a-z0-9]+)*?)-(hero|slide|the|tv-(\d{1,3}))$/', $info['filename'], $m ) ) {
		return null;
	}
	$role = isset( $m[3] ) ? 'tv' : $m[2];
	$n    = isset( $m[3] ) ? (int) $m[3] : null;
	if ( 'tv' === $role && $n < 1 ) {
		return null;
	}
	return array(
		'ma'   => $m[1],
		'role' => $role,
		'n'    => $n,
		'out'  => 'tv' === $role ? sprintf( '%s-tv-%02d.jpg', $m[1], $n ) : "{$m[1]}-{$role}.jpg",
	);
}

function vgarco_export_target_size( int $w, int $h, string $role ): array {
	[ $max_w, $max_h ] = vgarco_export_roles()[ $role ]['max'];
	$scale             = min( 1.0, $max_w / $w, $max_h / $h );
	return array( max( 1, (int) round( $w * $scale ) ), max( 1, (int) round( $h * $scale ) ) );
}

function vgarco_export_ratio_note( int $w, int $h, string $role ): ?string {
	$spec = vgarco_export_roles()[ $role ];
	if ( null === $spec['ratio'] ) {
		return null;
	}
	$ratio = $w / $h;
	if ( abs( $ratio / $spec['ratio'] - 1 ) <= VGARCO_EXPORT_RATIO_TOL ) {
		return null;
	}
	return sprintf( 'tỉ lệ %s:1, quy cách %s (%s:1) — cắt tay đúng tỉ lệ trước khi xuất', number_format( $ratio, 2, ',', '' ), $spec['label'], number_format( $spec['ratio'], 2, ',', '' ) );
}

function vgarco_export_oriented_size( int $w, int $h, int $orientation ): array {
	return $orientation >= 5 ? array( $h, $w ) : array( $w, $h );
}

function vgarco_export_orient( $im, int $orientation ) {
	switch ( $orientation ) {
		case 2:
			imageflip( $im, IMG_FLIP_HORIZONTAL );
			return $im;
		case 3:
			return imagerotate( $im, 180, 0 );
		case 4:
			imageflip( $im, IMG_FLIP_VERTICAL );
			return $im;
		case 5:
			imageflip( $im, IMG_FLIP_HORIZONTAL );
			return imagerotate( $im, 90, 0 );
		case 6:
			return imagerotate( $im, -90, 0 );
		case 7:
			imageflip( $im, IMG_FLIP_HORIZONTAL );
			return imagerotate( $im, -90, 0 );
		case 8:
			return imagerotate( $im, 90, 0 );
		default:
			return $im;
	}
}

function vgarco_export_resize( $im, string $role ) {
	$w = imagesx( $im );
	$h = imagesy( $im );
	[ $tw, $th ] = vgarco_export_target_size( $w, $h, $role );
	if ( $tw === $w && $th === $h ) {
		return $im;
	}
	$dst = imagecreatetruecolor( $tw, $th );
	imagecopyresampled( $dst, $im, 0, 0, 0, 0, $tw, $th, $w, $h );
	return $dst;
}

function vgarco_export_encode( $im, int $max_bytes = VGARCO_EXPORT_MAX_BYTES ): array {
	imageinterlace( $im, true );
	$bytes   = '';
	$quality = VGARCO_EXPORT_QUALITY;
	for ( ; ; ) {
		ob_start();
		imagejpeg( $im, null, $quality );
		$bytes = (string) ob_get_clean();
		$bytes = vgarco_jpeg_strip_metadata( $bytes ) ?? $bytes;
		if ( strlen( $bytes ) <= $max_bytes || $quality <= VGARCO_EXPORT_MIN_QUALITY ) {
			break;
		}
		$quality = max( VGARCO_EXPORT_MIN_QUALITY, $quality - VGARCO_EXPORT_QUALITY_STEP );
	}
	return array( 'bytes' => $bytes, 'quality' => $quality, 'ok' => strlen( $bytes ) <= $max_bytes );
}

function vgarco_export_avif_kb( $im ): ?int {
	if ( ! function_exists( 'imageavif' ) ) {
		return null;
	}
	ob_start();
	$ok    = @imageavif( $im, null, VGARCO_AVIF_QUALITY, 7 );
	$bytes = (string) ob_get_clean();
	return $ok && '' !== $bytes ? (int) ceil( strlen( $bytes ) / 1024 ) : null;
}

function vgarco_export_color_note( string $bytes ): ?string {
	$profile = '';
	if ( str_starts_with( $bytes, "\xFF\xD8" ) ) {
		$parsed = vgarco_jpeg_parse( $bytes );
		foreach ( $parsed ? $parsed['segments'] : array() as [ $marker, $pos ] ) {
			if ( 0xE2 === $marker && "ICC_PROFILE\0" === substr( $bytes, $pos + 4, 12 ) ) {
				$profile .= substr( $bytes, $pos + 18, ( ord( $bytes[ $pos + 2 ] ) << 8 | ord( $bytes[ $pos + 3 ] ) ) - 16 );
			}
		}
	} elseif ( str_starts_with( $bytes, "\x89PNG" ) && ( $at = strpos( $bytes, 'iCCP' ) ) !== false && $at >= 4 ) {
		$size    = (int) unpack( 'N', substr( $bytes, $at - 4, 4 ) )[1];
		$data    = substr( $bytes, $at + 4, min( $size, 4194304 ) );
		$name    = (string) strtok( $data, "\0" );
		$packed  = substr( $data, strlen( $name ) + 2 );
		$inflate = function_exists( 'zlib_decode' ) ? @zlib_decode( $packed, 4194304 ) : false;
		$profile = $name . ( false === $inflate ? '' : $inflate );
	}
	if ( '' === $profile || stripos( $profile, 'sRGB' ) !== false ) {
		return null;
	}
	return 'có hồ sơ màu không phải sRGB — màu sẽ lệch; xuất sang sRGB từ Lightroom/Photoshop trước (công cụ này không chuyển được hồ sơ màu)';
}

function vgarco_export_plan( array $files ): array {
	$items  = array();
	$errors = array();
	$seen   = array();
	$count  = array();
	sort( $files );
	foreach ( $files as $file ) {
		$p = vgarco_export_parse_name( $file );
		if ( ! $p ) {
			$errors[] = "{$file}: tên không đúng dạng <ma>-hero|slide|the|tv-NN.jpg (ma: chữ thường không dấu, số, gạch ngang; ví dụ bao-tang-qdnd-lao-hero.jpg)";
			continue;
		}
		if ( isset( $seen[ $p['out'] ] ) ) {
			$errors[] = "{$file}: trùng tệp xuất {$p['out']} với {$seen[ $p['out'] ]}";
			continue;
		}
		$seen[ $p['out'] ] = $file;
		if ( 'tv' === $p['role'] && ( $count[ $p['ma'] ] = ( $count[ $p['ma'] ] ?? 0 ) + 1 ) > VGARCO_EXPORT_MAX_GALLERY ) {
			$errors[] = "{$file}: dự án {$p['ma']} có quá " . VGARCO_EXPORT_MAX_GALLERY . ' ảnh thư viện';
			continue;
		}
		$items[] = $p + array( 'src' => $file );
	}
	return array( 'items' => $items, 'errors' => $errors );
}

function vgarco_export_csv_rows( array $items ): array {
	$order = array( 'hero' => 0, 'the' => 1, 'slide' => 2, 'tv' => 3 );
	usort( $items, fn( $a, $b ) => array( $a['ma'], $order[ $a['role'] ], $a['n'] ) <=> array( $b['ma'], $order[ $b['role'] ], $b['n'] ) );
	$rows = array( array( 'ma', 'tep', 'vai_tro', 'thu_tu', 'loai_anh', 'chu_thich_vi', 'chu_thich_de' ) );
	foreach ( $items as $i ) {
		$rows[] = array( $i['ma'], $i['out'], $i['role'], 'tv' === $i['role'] ? (string) $i['n'] : '', '', '', '' );
	}
	return $rows;
}

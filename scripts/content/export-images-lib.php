<?php
/**
 * Xuất ảnh master (phương án mục 9.2 và 9.3 bước 3–4) — phần THUẦN, kiểm thử bằng ảnh thật (tests/php/export-images.php).
 *
 * Ảnh đã được CẮT TAY đúng tỉ lệ (9.3 bước 2). Công cụ này không bao giờ tự cắt (cắt tự động xén mất mái hoặc nền công trình):
 * chỉ xoay theo EXIF, thu nhỏ vừa khung (không phóng to), nén JPEG, gỡ siêu dữ liệu, và BÁO những gì lệch quy cách.
 *
 * Cần phần mở rộng gd. Dùng lại media-lib.php của vgarco-core (đọc hướng xoay, gỡ siêu dữ liệu): nạp bởi export-images.php sau khi định nghĩa ABSPATH.
 */

/** Chất lượng JPEG của master (9.3: 86); hạ dần tới mức tối thiểu nếu tệp vượt trần dung lượng. */
const VGARCO_EXPORT_QUALITY       = 86;
const VGARCO_EXPORT_MIN_QUALITY   = 70;
const VGARCO_EXPORT_QUALITY_STEP  = 4;
/** Trần dung lượng master (9.3 bước 4): 1,5 MB. */
const VGARCO_EXPORT_MAX_BYTES     = 1572864;
/** Sai lệch tỉ lệ cho phép so với quy cách (3%). */
const VGARCO_EXPORT_RATIO_TOL     = 0.03;
/** Số ảnh thư viện tối đa của một dự án (khớp importer). */
const VGARCO_EXPORT_MAX_GALLERY   = 15;

/**
 * Quy cách theo vai trò (mục 9.2): khung tối đa [rộng, cao], tỉ lệ rộng/cao cần có (null = tùy ảnh) và trần dung lượng sau tối ưu (KB, AVIF).
 * Trần AVIF là quyết định còn mở với chủ đầu tư (Phụ lục E, 05/10/2026): chỉ cảnh báo, không bao giờ làm hỏng lần xuất.
 *
 * @return array<string, array{max: int[], ratio: float|null, ceiling_kb: int, label: string}>
 */
function vgarco_export_roles(): array {
	return array(
		'hero'  => array( 'max' => array( 2560, 1097 ), 'ratio' => 21 / 9, 'ceiling_kb' => 350, 'label' => '21:9' ),
		'slide' => array( 'max' => array( 1600, 900 ), 'ratio' => 16 / 9, 'ceiling_kb' => 250, 'label' => '16:9' ),
		'the'   => array( 'max' => array( 1200, 900 ), 'ratio' => 4 / 3, 'ceiling_kb' => 150, 'label' => '4:3' ),
		'tv'    => array( 'max' => array( 1800, 1800 ), 'ratio' => null, 'ceiling_kb' => 300, 'label' => '' ),
	);
}

/**
 * Tách tên tệp `<ma>-hero|slide|the|tv-NN.<đuôi>`. `ma` là chữ thường không dấu, số và gạch ngang (như cột `ma` của projects.csv).
 *
 * @return array{ma: string, role: string, n: int|null, out: string}|null `out` là tên tệp xuất (luôn .jpg; thư viện đánh số hai chữ số).
 */
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

/** Kích thước xuất: vừa khung của vai trò, giữ tỉ lệ, KHÔNG phóng to. @return int[] [rộng, cao] */
function vgarco_export_target_size( int $w, int $h, string $role ): array {
	[ $max_w, $max_h ] = vgarco_export_roles()[ $role ]['max'];
	$scale             = min( 1.0, $max_w / $w, $max_h / $h );
	return array( max( 1, (int) round( $w * $scale ) ), max( 1, (int) round( $h * $scale ) ) );
}

/** Ghi chú khi tỉ lệ ảnh lệch quy cách của vai trò (null nếu đạt hoặc vai trò không có tỉ lệ cố định). */
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

/** Kích thước SAU khi xoay theo hướng EXIF (hướng 5–8 đổi chỗ rộng và cao). @return int[] */
function vgarco_export_oriented_size( int $w, int $h, int $orientation ): array {
	return $orientation >= 5 ? array( $h, $w ) : array( $w, $h );
}

/**
 * Xoay/lật ảnh GD cho đúng chiều theo hướng EXIF (bảng của đặc tả EXIF: 2 lật ngang, 3 xoay 180°, 4 lật dọc,
 * 5 lật ngang rồi xoay 270° thuận, 6 xoay 90° thuận, 7 lật ngang rồi xoay 90° thuận, 8 xoay 270° thuận).
 * imagerotate của GD quay NGƯỢC chiều kim đồng hồ.
 *
 * @param GdImage $im
 * @return GdImage
 */
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

/**
 * Thu nhỏ vừa khung của vai trò (lấy mẫu lại chất lượng cao); ảnh nhỏ hơn khung giữ nguyên.
 *
 * @param GdImage $im
 * @return GdImage
 */
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

/**
 * Nén JPEG (progressive), hạ chất lượng từng bước cho tới khi ≤ $max_bytes hoặc chạm mức tối thiểu; gỡ ghi chú CREATOR do GD tự thêm.
 *
 * @param GdImage $im
 * @return array{bytes: string, quality: int, ok: bool} ok = đạt trần dung lượng.
 */
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

/** Ước lượng dung lượng AVIF (KB) ở mức chất lượng của website (VGARCO_AVIF_QUALITY). null nếu GD không có AVIF. @param GdImage $im */
function vgarco_export_avif_kb( $im ): ?int {
	if ( ! function_exists( 'imageavif' ) ) {
		return null;
	}
	ob_start();
	$ok    = @imageavif( $im, null, VGARCO_AVIF_QUALITY, 7 );
	$bytes = (string) ob_get_clean();
	return $ok && '' !== $bytes ? (int) ceil( strlen( $bytes ) / 1024 ) : null; // làm tròn lên: ảnh phẳng vài trăm byte vẫn là 1 KB, không phải 0
}

/**
 * Ghi chú về hồ sơ màu: GD không chuyển được sang sRGB, nên ảnh mang hồ sơ khác (Display P3, Adobe RGB, ProPhoto...) sẽ lệch màu khi bỏ hồ sơ.
 *
 * @param string $bytes Nội dung tệp nguồn (JPEG hoặc PNG).
 */
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
		// Khối iCCP: tên hồ sơ, 0, phương pháp nén, rồi hồ sơ nén zlib. Tên hay chung chung ("Photoshop ICC profile") nên phải đọc cả hồ sơ.
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

/**
 * Kiểm tra TRƯỚC khi ghi gì: tên tệp, trùng tên xuất, quá 15 ảnh thư viện. Mọi lỗi được gom lại một lượt (giống importer).
 *
 * @param string[] $files Tên tệp ảnh (không gồm thư mục).
 * @return array{items: array<int, array<string, mixed>>, errors: string[]}
 */
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

/**
 * Dòng images.csv cho các ảnh đã xuất (chú thích để TRỐNG: ảnh thư viện bắt buộc có chú thích thật, importer sẽ báo cho tới khi điền).
 *
 * @param array<int, array{ma: string, role: string, n: int|null, out: string}> $items
 * @return string[][] Gồm dòng tiêu đề.
 */
function vgarco_export_csv_rows( array $items ): array {
	$order = array( 'hero' => 0, 'the' => 1, 'slide' => 2, 'tv' => 3 );
	usort( $items, fn( $a, $b ) => array( $a['ma'], $order[ $a['role'] ], $a['n'] ) <=> array( $b['ma'], $order[ $b['role'] ], $b['n'] ) );
	$rows = array( array( 'ma', 'tep', 'vai_tro', 'thu_tu', 'loai_anh', 'chu_thich_vi', 'chu_thich_de' ) );
	foreach ( $items as $i ) {
		$rows[] = array( $i['ma'], $i['out'], $i['role'], 'tv' === $i['role'] ? (string) $i['n'] : '', '', '', '' );
	}
	return $rows;
}

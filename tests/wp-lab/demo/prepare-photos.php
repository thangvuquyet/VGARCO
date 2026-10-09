<?php

ini_set( 'memory_limit', '3072M' );
error_reporting( E_ALL & ~E_DEPRECATED );

$args  = array_slice( $argv, 1 );
$paths = array_values( array_filter( $args, fn( $a ) => ! str_starts_with( $a, '--' ) ) );
$only  = null;
foreach ( $args as $a ) {
	if ( str_starts_with( $a, '--only=' ) ) {
		$only = substr( $a, 7 );
	}
}
if ( 2 !== count( $paths ) ) {
	fwrite( STDERR, "Cách dùng: php prepare-photos.php <thư-mục-ảnh-gốc> <thư-mục-ra> [--only=<ma>]\n" );
	exit( 2 );
}
[ $root, $out ] = $paths;
$root = rtrim( str_replace( '\\', '/', $root ), '/' );
$out  = rtrim( str_replace( '\\', '/', $out ), '/' );
$repo = dirname( __DIR__, 3 );

$MAP = array(
	'bao-tang-lich-su-quan-doi-nhan-dan-lao'     => array( 'BAO TANG/ANH BAO TANG LICH SU QDND LAO', null ),
	'truong-thcs-chat-luong-cao-bac-tu-liem'     => array( 'TRUONG CHAT LUONG CAO', null ),
	'du-an-ban-con'                              => array( 'BAN CON', null ),
	'khu-nghi-duong-hidumi-hoang-mai'            => array( 'KHU NGHI DUONG HIDUMI', null ),
	'cong-trinh-cuc-bien-phong'                  => array( 'CUC BIEN PHONG', null ),
	'cuc-thi-hanh-an-dan-su-binh-chanh'          => array( 'CUC THI HANH AN DAN SU BINH CHANH', null ),
	'cong-trinh-dac-cong'                        => array( 'DAC CONG', null ),
	'don-bien-phong-214'                         => array( 'DON BP 214', null ),
	'truong-mam-non-xuan-dinh'                   => array( 'MAM NON XD', null ),
	'nha-dai-su'                                 => array( 'NHA DAI SU', '/^PANO/i' ),
	'truong-tieu-hoc-co-nhue-2b'                 => array( 'TH CO NHUE 2B', null ),
	'truong-thcs-tay-tuu'                        => array( 'THCS TAY TUU', null ),
	'tong-lanh-su-quan-viet-nam-tai-savannakhet' => array( 'TLS SAVANAKHET', '/^PANO/i' ),
	'cong-trinh-to-ngoc-van'                     => array( 'TO NGOC VAN', null ),
	'truong-ly-luan-chinh-tri-quan-doi-nhan-dan-lao' => array( 'TRUONG CHINH TRI', null ),
	'trung-tam-boi-duong-chinh-tri'              => array( 'TT BOI DUONG CTRI', null ),
);
const GALLERY_MAX      = 6;
const GALLERY_LONG     = 1800;
const UPSCALE_BELOW    = 1500;
const MAX_UPSCALE      = 2.6;
$ROLES = array(
	'hero'  => array( 2560, 1097 ),
	'slide' => array( 1600, 900 ),
	'the'   => array( 1200, 900 ),
);

$names = array();
$csv   = preg_split( '/\r?\n/', trim( (string) file_get_contents( $repo . '/content/projects.csv' ) ) );
$head  = str_getcsv( ltrim( array_shift( $csv ), "\xEF\xBB\xBF" ), ';' );
foreach ( $csv as $line ) {
	$row = array_combine( $head, str_getcsv( $line, ';' ) );
	$names[ $row['ma'] ] = $row['ten_vi'];
}

function p( string $m ): void {
	echo $m . "\n";
}

function load_image( string $path ): ?array {
	$data = @file_get_contents( $path );
	if ( false === $data ) {
		return null;
	}
	$im = @imagecreatefromstring( $data );
	if ( ! $im ) {
		return null;
	}
	$camera = false;
	if ( function_exists( 'exif_read_data' ) && preg_match( '/\.jpe?g$/i', $path ) ) {
		$exif   = @exif_read_data( $path );
		$camera = is_array( $exif ) && ( isset( $exif['Make'] ) || isset( $exif['Model'] ) );
		$o      = (int) ( $exif['Orientation'] ?? 1 );
		$angle  = array( 3 => 180, 6 => -90, 8 => 90 )[ $o ] ?? 0;
		if ( $angle ) {
			$im = imagerotate( $im, $angle, 0 );
		}
	}
	return array( $im, imagesx( $im ), imagesy( $im ), $camera );
}

function saliency_crop( GdImage $im, float $ratio ): array {
	$w = imagesx( $im );
	$h = imagesy( $im );
	if ( $w / $h > $ratio ) {
		$cw = (int) round( $h * $ratio );
		$ch = $h;
	} else {
		$cw = $w;
		$ch = (int) round( $w / $ratio );
	}
	$slack_x = $w - $cw;
	$slack_y = $h - $ch;
	if ( $slack_x < 4 && $slack_y < 4 ) {
		return array( 0, 0, $w, $h );
	}
	$sw   = 160;
	$sh   = max( 8, (int) round( $h * $sw / $w ) );
	$tiny = imagescale( $im, $sw, $sh, IMG_BILINEAR_FIXED );
	imagefilter( $tiny, IMG_FILTER_GRAYSCALE );
	$g = array();
	for ( $y = 0; $y < $sh; $y++ ) {
		for ( $x = 0; $x < $sw; $x++ ) {
			$g[ $y ][ $x ] = imagecolorat( $tiny, $x, $y ) & 0xFF;
		}
	}
	$e = array_fill( 0, $sh, array_fill( 0, $sw, 0 ) );
	for ( $y = 1; $y < $sh - 1; $y++ ) {
		for ( $x = 1; $x < $sw - 1; $x++ ) {
			$e[ $y ][ $x ] = abs( $g[ $y ][ $x + 1 ] - $g[ $y ][ $x - 1 ] ) + abs( $g[ $y + 1 ][ $x ] - $g[ $y - 1 ][ $x ] );
		}
	}
	$scale = $sw / $w;
	$best  = array( 0, 0 );
	$score = -1.0;
	$steps = 12;
	for ( $s = 0; $s <= $steps; $s++ ) {
		$px = $slack_x > 3 ? (int) round( $slack_x * $s / $steps ) : (int) round( $slack_x / 2 );
		$py = $slack_y > 3 ? (int) round( $slack_y * $s / $steps ) : (int) round( $slack_y / 2 );
		if ( $slack_x > 3 && $slack_y > 3 ) {
			continue;
		}
		$x0 = (int) floor( $px * $scale );
		$y0 = (int) floor( $py * $scale );
		$x1 = min( $sw - 1, (int) ceil( ( $px + $cw ) * $scale ) );
		$y1 = min( $sh - 1, (int) ceil( ( $py + $ch ) * $scale ) );
		$sum = 0;
		for ( $y = $y0; $y < $y1; $y++ ) {
			for ( $x = $x0; $x < $x1; $x++ ) {
				$sum += $e[ $y ][ $x ];
			}
		}
		$pos    = $s / $steps;
		$weight = 1 - 0.18 * abs( $pos - 0.45 ) * 2;
		$val    = $sum * $weight;
		if ( $val > $score ) {
			$score = $val;
			$best  = array( $px, $py );
		}
	}
	if ( $slack_x > 3 && $slack_y > 3 ) {
		$best = array( (int) round( $slack_x / 2 ), (int) round( $slack_y / 2 ) );
	}
	return array( $best[0], $best[1], $cw, $ch );
}

function enhance_upscale( string $png, string $jpg, int $w, int $h ): bool {
	$vf  = sprintf( 'hqdn3d=1.5:1.2:2:2,scale=%d:%d:flags=lanczos,unsharp=5:5:0.7:5:5:0.0,eq=contrast=1.04:saturation=1.05', $w, $h );
	$cmd = sprintf( 'ffmpeg -v error -y -i %s -vf %s -q:v 2 %s 2>&1', escapeshellarg( $png ), escapeshellarg( $vf ), escapeshellarg( $jpg ) );
	exec( $cmd, $lines, $code );
	return 0 === $code && is_file( $jpg ) && filesize( $jpg ) > 1000;
}

function save_jpeg( GdImage $im, string $path, int $q = 92 ): void {
	imagejpeg( $im, $path, $q );
}

@mkdir( "$out/staging", 0777, true );
@mkdir( "$out/masters", 0777, true );
foreach ( array_merge( glob( "$out/staging/*" ) ?: array(), $only ? array() : ( glob( "$out/masters/*" ) ?: array() ) ) as $f ) {
	if ( is_file( $f ) && ( ! $only || str_contains( basename( $f ), $only ) ) ) {
		unlink( $f );
	}
}

$report = array();
foreach ( $MAP as $ma => [ $dir, $exclude ] ) {
	if ( $only && $only !== $ma ) {
		continue;
	}
	$base  = "$root/$dir";
	$files = array();
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
		$path = str_replace( '\\', '/', $f->getPathname() );
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $path ) || ( $exclude && preg_match( $exclude, basename( $path ) ) ) ) {
			continue;
		}
		$s = @getimagesize( $path );
		if ( ! $s ) {
			continue;
		}
		$files[] = array( 'path' => $path, 'w' => $s[0], 'h' => $s[1], 'name' => substr( $path, strlen( $base ) + 1 ) );
	}
	if ( ! $files ) {
		p( "[$ma] không có ảnh" );
		continue;
	}
	foreach ( $files as &$f ) {
		$f['camera'] = false;
		if ( function_exists( 'exif_read_data' ) && preg_match( '/\.jpe?g$/i', $f['path'] ) ) {
			$ex          = @exif_read_data( $f['path'] );
			$f['camera'] = is_array( $ex ) && ( isset( $ex['Make'] ) || isset( $ex['Model'] ) );
			if ( in_array( (int) ( $ex['Orientation'] ?? 1 ), array( 6, 8 ), true ) ) {
				[ $f['w'], $f['h'] ] = array( $f['h'], $f['w'] );
			}
		}
		$f['ratio'] = $f['w'] / $f['h'];
		$f['area']  = min( $f['w'] * $f['h'], 12_000_000 );
		$f['wide']  = $f['ratio'] >= 1.45;
	}
	unset( $f );
	$rank = $files;
	usort( $rank, fn( $a, $b ) => array( (int) ! $a['wide'], (int) $a['camera'], -$a['area'] ) <=> array( (int) ! $b['wide'], (int) $b['camera'], -$b['area'] ) );
	$lead = $rank[0];
	$card = $rank[1] ?? $rank[0];
	$pool   = array_values( array_filter( $files, fn( $f ) => $f['path'] !== $lead['path'] ) );
	usort( $pool, fn( $a, $b ) => -$a['area'] <=> -$b['area'] );
	$render = array_values( array_filter( $pool, fn( $f ) => ! $f['camera'] ) );
	$photo  = array_values( array_filter( $pool, fn( $f ) => $f['camera'] ) );
	$gal    = array();
	while ( count( $gal ) < GALLERY_MAX && ( $render || $photo ) ) {
		if ( $photo ) {
			$gal[] = array_shift( $photo );
		}
		if ( count( $gal ) < GALLERY_MAX && $render ) {
			$gal[] = array_shift( $render );
		}
	}
	$entry = array( 'ma' => $ma, 'thu_muc' => $dir, 'tong_anh_goc' => count( $files ), 'vai_tro' => array(), 'thu_vien' => array() );

	$do_role = function ( string $role, array $src, string $dest, ?float $ratio, ?array $frame ) use ( &$entry ): void {
		$img = load_image( $src['path'] );
		if ( ! $img ) {
			p( "  ! không đọc được {$src['path']}" );
			return;
		}
		[ $im, $w, $h ] = $img;
		if ( null !== $ratio ) {
			[ $x, $y, $cw, $ch ] = saliency_crop( $im, $ratio );
			$crop = imagecrop( $im, array( 'x' => $x, 'y' => $y, 'width' => $cw, 'height' => $ch ) );
			$need = $frame[0] / $cw;
			$up   = $need > 1.05;
			$info = array( 'tep_goc' => $src['name'], 'goc' => "{$w}x{$h}", 'cat' => "{$cw}x{$ch}+{$x}+{$y}" );
			if ( $up ) {
				$scale = min( $need, MAX_UPSCALE );
				$tw    = (int) round( $cw * $scale );
				$th    = (int) round( $ch * $scale );
				$tmp   = $dest . '.tmp.png';
				imagepng( $crop, $tmp, 1 );
				$ok = enhance_upscale( $tmp, $dest, $tw, $th );
				@unlink( $tmp );
				if ( ! $ok ) {
					$big = imagescale( $crop, $tw, $th, IMG_SINC );
					save_jpeg( $big, $dest );
					$info['phong_to'] = sprintf( 'x%.2f (GD, ffmpeg lỗi)', $scale );
				} else {
					$info['phong_to'] = sprintf( 'x%.2f (ffmpeg lanczos + làm nét)', $scale );
				}
			} else {
				save_jpeg( $crop, $dest );
			}
		} else {
			$long  = max( $w, $h );
			$info  = array( 'tep_goc' => $src['name'], 'goc' => "{$w}x{$h}", 'loai' => $src['camera'] ? 'cong_trinh' : 'thiet_ke' );
			if ( $long < UPSCALE_BELOW ) {
				$scale = min( GALLERY_LONG / $long, MAX_UPSCALE, 2.0 );
				$tw    = (int) round( $w * $scale );
				$th    = (int) round( $h * $scale );
				$tmp   = $dest . '.tmp.png';
				imagepng( $im, $tmp, 1 );
				$ok = enhance_upscale( $tmp, $dest, $tw, $th );
				@unlink( $tmp );
				if ( ! $ok ) {
					save_jpeg( imagescale( $im, $tw, $th, IMG_SINC ), $dest );
				}
				$info['phong_to'] = sprintf( 'x%.2f (ffmpeg lanczos + làm nét)', $scale );
			} else {
				save_jpeg( $im, $dest );
			}
		}
		imagedestroy( $im );
		if ( 'tv' === $role ) {
			$entry['thu_vien'][] = $info;
		} else {
			$entry['vai_tro'][ $role ] = $info;
		}
	};

	$do_role( 'hero', $lead, "$out/staging/$ma-hero.jpg", 21 / 9, $ROLES['hero'] );
	$do_role( 'slide', $lead, "$out/staging/$ma-slide.jpg", 16 / 9, $ROLES['slide'] );
	$do_role( 'the', $card, "$out/staging/$ma-the.jpg", 4 / 3, $ROLES['the'] );
	foreach ( $gal as $i => $g ) {
		$do_role( 'tv', $g, sprintf( '%s/staging/%s-tv-%02d.jpg', $out, $ma, $i + 1 ), null, null );
	}
	$ups = count( array_filter( array_merge( $entry['vai_tro'], $entry['thu_vien'] ), fn( $r ) => isset( $r['phong_to'] ) ) );
	p( sprintf( '[%s] %d ảnh gốc → hero/slide/thẻ + %d ảnh thư viện; phóng to: %d', $ma, count( $files ), count( $gal ), $ups ) );
	$report[ $ma ] = $entry;
}

$php   = PHP_BINARY;
$ext   = ini_get( 'extension_dir' );
$skel  = "$out/images-skeleton.csv";
@unlink( $skel );
$cmd = sprintf( '%s -d extension_dir=%s -d extension=gd -d extension=mbstring -d extension=exif %s %s %s --csv=%s 2>&1', escapeshellarg( $php ), escapeshellarg( $ext ), escapeshellarg( $repo . '/scripts/content/export-images.php' ), escapeshellarg( "$out/staging" ), escapeshellarg( "$out/masters" ), escapeshellarg( $skel ) );
exec( $cmd, $lines, $code );
p( "\n== export-images.php (mã thoát $code)" );
p( implode( "\n", array_slice( $lines, -12 ) ) );
if ( 0 !== $code ) {
	exit( 1 );
}

$rows = array_map( fn( $l ) => str_getcsv( $l, ';' ), preg_split( '/\r?\n/', trim( ltrim( (string) file_get_contents( $skel ), "\xEF\xBB\xBF" ) ) ) );
$hdr  = array_shift( $rows );
$fh   = fopen( "$out/images.csv", 'w' );
fwrite( $fh, "\xEF\xBB\xBF" );
fputcsv( $fh, $hdr, ';' );
foreach ( $rows as $r ) {
	$row = array_combine( $hdr, $r );
	if ( 'tv' === $row['vai_tro'] && isset( $report[ $row['ma'] ]['thu_vien'][ (int) $row['thu_tu'] - 1 ] ) ) {
		$info                = $report[ $row['ma'] ]['thu_vien'][ (int) $row['thu_tu'] - 1 ];
		$row['loai_anh']     = $info['loai'];
		$row['chu_thich_vi'] = 'cong_trinh' === $info['loai'] ? 'Ảnh công trình' : 'Phối cảnh thiết kế';
	}
	fputcsv( $fh, array_values( $row ), ';' );
}
fclose( $fh );
@unlink( $skel );
file_put_contents( "$out/report.json", json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

$cell_h = 150;
$rowsN  = count( $report );
$sheet  = imagecreatetruecolor( 1500, max( 1, $rowsN ) * ( $cell_h + 16 ) );
$bg     = imagecolorallocate( $sheet, 240, 240, 236 );
$ink    = imagecolorallocate( $sheet, 20, 20, 20 );
imagefill( $sheet, 0, 0, $bg );
$yy = 0;
foreach ( $report as $ma => $entry ) {
	imagestring( $sheet, 2, 4, $yy, $ma, $ink );
	$xx = 0;
	$set = array( "$out/masters/$ma-hero.jpg", "$out/masters/$ma-slide.jpg", "$out/masters/$ma-the.jpg" );
	for ( $i = 1; $i <= 3; $i++ ) {
		$set[] = sprintf( '%s/masters/%s-tv-%02d.jpg', $out, $ma, $i );
	}
	foreach ( $set as $img ) {
		if ( ! is_file( $img ) ) {
			continue;
		}
		$im = imagecreatefromjpeg( $img );
		$tw = (int) round( imagesx( $im ) * $cell_h / imagesy( $im ) );
		imagecopyresampled( $sheet, $im, $xx, $yy + 14, 0, 0, $tw, $cell_h, imagesx( $im ), imagesy( $im ) );
		$xx += $tw + 4;
		imagedestroy( $im );
	}
	$yy += $cell_h + 16;
}
imagejpeg( $sheet, "$out/contact-sheet.jpg", 80 );
p( "\nXong: " . count( $report ) . " dự án. Tờ xem nhanh: $out/contact-sheet.jpg" );

<?php
error_reporting( E_ALL & ~E_WARNING & ~E_DEPRECATED );

$static = 'http://127.0.0.1:4174/';
$lab    = 'http://localhost:8080';

const ALLOWED = array(
	'*'         => array(),
	'chi tiết dự án' => array(
		'div.project-scope' => 'Phạm vi công việc: dữ liệu mẫu của trang tĩnh có, bảng nhập dự án (content/projects.csv) chưa có cột này',
	),
	'chi tiết tin'   => array(
		'a.nav-prev'          => 'Bài trước/sau theo ngày đăng: ngày của dữ liệu mẫu tĩnh (09/2026) khác ngày của dữ liệu thử (tính từ hôm nay), nên bài đầu tiên khác chiều',
		'a.nav-next'          => 'như trên',
		'figure.wp-block-image' => 'Hình trong nội dung bài do trình soạn thảo WordPress sinh ra (lớp wp-block-image); dữ liệu mẫu tĩnh viết <figure> trần',
	),
);

$pairs = array(
	'trang chủ'           => array( 'home.html', '/' ),
	'danh mục dự án'      => array( 'archive-du_an.html', '/du-an/' ),
	'chi tiết dự án'      => array( 'single-du_an-106.html', '/du-an/bao-tang-lich-su-quan-doi-nhan-dan-lao/' ),
	'loại công trình'     => array( 'taxonomy-loai_cong_trinh-giao-duc.html', '/du-an/loai/giao-duc-dao-tao/' ),
	'giới thiệu'          => array( 'templates-about-501.html', '/gioi-thieu/' ),
	'ban lãnh đạo'        => array( 'templates-leadership-502.html', '/gioi-thieu/ban-lanh-dao/' ),
	'phòng ban'           => array( 'templates-departments-503.html', '/gioi-thieu/phong-ban/' ),
	'lĩnh vực'            => array( 'templates-services-504.html', '/linh-vuc-hoat-dong/' ),
	'liên hệ'             => array( 'templates-contact-505.html', '/lien-he/' ),
	'tuyển dụng'          => array( 'templates-careers-506.html', '/tuyen-dung/' ),
	'chi tiết vị trí'     => array( 'single-vi_tri-401.html', '/tuyen-dung/vt-kts/' ),
	'ứng tuyển'           => array( 'templates-apply-507.html', 'apply' ),
	'tin tức'             => array( 'archive-tin_tuc.html', '/tin-tuc/' ),
	'chi tiết tin'        => array( 'single-tin_tuc-301.html', '/tin-tuc/tu-y-tuong-den-mot-cong-trinh-co-the-van-hanh/' ),
);

function fetch( string $url ): ?DOMXPath {
	$ctx  = stream_context_create( array( 'http' => array( 'timeout' => 20, 'ignore_errors' => true ) ) );
	$html = @file_get_contents( $url, false, $ctx );
	if ( false === $html || '' === $html || ! preg_match( '#^HTTP/\S+ 2#', (string) ( $http_response_header[0] ?? '' ) ) ) {
		return null;
	}
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
	libxml_clear_errors();
	return new DOMXPath( $dom );
}

function summarise( DOMXPath $x ): array {
	$main = $x->query( '//main' )->item( 0 );
	$keys = array();
	$head = array();
	foreach ( $main ? $x->query( './/*', $main ) : array() as $el ) {
		$cls = trim( (string) $el->getAttribute( 'class' ) );
		if ( 'img' === $el->nodeName ) {
			continue;
		}
		if ( '' !== $cls ) {
			$keys[ $el->nodeName . '.' . preg_split( '/\s+/', $cls )[0] ] = true;
		}
		if ( preg_match( '/^h[1-3]$/', $el->nodeName ) ) {
			$head[ $el->nodeName ] = true;
		}
	}
	ksort( $keys );
	return array( 'keys' => array_keys( $keys ), 'headings' => array_keys( $head ) );
}

$verbose    = in_array( '--verbose', $argv, true );
$unexpected = 0;
$missing    = 0;
foreach ( $pairs as $name => [ $file, $path ] ) {
	if ( 'apply' === $path ) {
		$job = fetch( $lab . '/tuyen-dung/vt-kts/' );
		$a   = $job ? $job->query( '//a[contains(@href, "/ung-tuyen/")]' )->item( 0 ) : null;
		$path = $a ? (string) wp_parse_url_path( $a->getAttribute( 'href' ) ) : '/ung-tuyen/';
	}
	$a = fetch( $static . $file );
	$b = fetch( $lab . $path );
	if ( ! $a || ! $b ) {
		++$missing;
		echo "THIẾU   {$name}: " . ( $a ? '' : "không tải được trang tĩnh {$file}; " ) . ( $b ? '' : "không tải được trang thử {$path}" ) . "\n";
		continue;
	}
	$sa    = summarise( $a );
	$sb    = summarise( $b );
	$only_static = array_diff( $sa['keys'], $sb['keys'] );
	$only_lab    = array_diff( $sb['keys'], $sa['keys'] );
	$allowed     = ALLOWED['*'] + ( ALLOWED[ $name ] ?? array() );
	$bad         = array();
	foreach ( array( 'tĩnh' => $only_static, 'thử' => $only_lab ) as $side => $list ) {
		foreach ( $list as $key ) {
			if ( isset( $allowed[ $key ] ) ) {
				$verbose && print "   (chấp nhận) {$name}: {$key} chỉ ở bản {$side} — {$allowed[$key]}\n";
			} else {
				$bad[] = "{$key} chỉ ở bản {$side}";
			}
		}
	}
	$head_diff = array_merge( array_diff( $sa['headings'], $sb['headings'] ), array_diff( $sb['headings'], $sa['headings'] ) );
	if ( $head_diff ) {
		$bad[] = 'khác cấp tiêu đề: ' . implode( ',', $head_diff );
	}
	if ( $bad ) {
		$unexpected += count( $bad );
		echo "KHÁC    {$name} ({$file} ↔ {$path}): " . implode( '; ', $bad ) . "\n";
	} else {
		echo 'KHỚP    ' . $name . ' (' . count( $sa['keys'] ) . ' lớp)' . "\n";
	}
}
echo ( $unexpected || $missing ) ? "\n{$unexpected} khác biệt chưa được chấp nhận, {$missing} trang không tải được\n" : "\nTất cả khớp\n";
exit( ( $unexpected || $missing ) ? 1 : 0 );

function wp_parse_url_path( string $url ): string {
	$p = parse_url( $url );
	return ( $p['path'] ?? '/' ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
}

<?php
require __DIR__ . '/lib.php';

$base    = 'http://localhost:8080';
$queue   = array( '/' );
$seen    = array();
$assets  = array();
$bad     = array();
$pages   = 0;
$no_h1   = array();
$dup_ids = array();
$no_alt  = array();
$mixed   = array();

while ( $queue && $pages < 300 ) {
	$path = array_shift( $queue );
	if ( isset( $seen[ $path ] ) ) {
		continue;
	}
	$seen[ $path ] = true;
	$r = http( 'GET', $path );
	++$pages;
	if ( 200 !== $r['status'] ) {
		$bad[] = "{$r['status']} {$path}";
		continue;
	}
	if ( ! preg_match( '#content-type:\s*text/html#i', $r['headers'] ) ) {
		continue;
	}
	$d = new DOMDocument();
	libxml_use_internal_errors( true );
	$d->loadHTML( '<?xml encoding="UTF-8">' . $r['body'] );
	libxml_clear_errors();
	$x = new DOMXPath( $d );

	if ( 1 !== $x->query( '//h1' )->length ) {
		$no_h1[] = $path . ' (' . $x->query( '//h1' )->length . ')';
	}
	$ids = array();
	foreach ( $x->query( '//*[@id]' ) as $el ) {
		$id = $el->getAttribute( 'id' );
		if ( isset( $ids[ $id ] ) ) {
			$dup_ids[] = "{$path}: #{$id}";
		}
		$ids[ $id ] = true;
	}
	foreach ( $x->query( '//img[not(@alt)]' ) as $img ) {
		$no_alt[] = $path . ' ' . $img->getAttribute( 'src' );
	}
	foreach ( $x->query( '//a[@href]' ) as $a ) {
		$h = $a->getAttribute( 'href' );
		if ( preg_match( '#^(mailto:|tel:|javascript:|\#)#', $h ) ) {
			continue;
		}
		$p = wp_parse_url( $h );
		if ( ! empty( $p['host'] ) && 'localhost' !== $p['host'] ) {
			continue;
		}
		$target = ( $p['path'] ?? '/' ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
		if ( preg_match( '#^/(wp-admin|wp-login|wp-json|xmlrpc|feed)#', $target ) || preg_match( '#/feed/?$#', $target ) ) {
			continue;
		}
		if ( ! isset( $seen[ $target ] ) ) {
			$queue[] = $target;
		}
	}
	foreach ( $x->query( '//link[@href] | //script[@src] | //img[@src] | //source[@srcset] | //img[@srcset]' ) as $el ) {
		foreach ( array( 'href', 'src' ) as $attr ) {
			$u = $el->getAttribute( $attr );
			if ( '' !== $u && ! str_starts_with( $u, 'data:' ) ) {
				if ( preg_match( '#^http://(?!localhost)|^https?://(?!localhost)#', $u ) && ! str_contains( $u, 'localhost' ) ) {
					if ( 'link' === $el->nodeName && in_array( $el->getAttribute( 'rel' ), array( 'canonical', 'alternate', 'next', 'prev' ), true ) ) {
						continue;
					}
					$mixed[] = "{$path}: {$u}";
					continue;
				}
				$assets[ wp_parse_url( $u, PHP_URL_PATH ) . ( wp_parse_url( $u, PHP_URL_QUERY ) ? '?' . wp_parse_url( $u, PHP_URL_QUERY ) : '' ) ] = $path;
			}
		}
		$set = $el->getAttribute( 'srcset' );
		foreach ( array_filter( array_map( 'trim', explode( ',', $set ) ) ) as $cand ) {
			$u = preg_split( '/\s+/', $cand )[0];
			$assets[ wp_parse_url( $u, PHP_URL_PATH ) ] = $path;
		}
	}
}

$missing = array();
foreach ( $assets as $a => $from ) {
	if ( ! $a || ! str_starts_with( $a, '/' ) ) {
		continue;
	}
	$r = http( 'GET', $a );
	if ( 200 !== $r['status'] ) {
		$missing[] = "{$r['status']} {$a} (từ {$from})";
	}
}

echo "Đã duyệt {$pages} trang, " . count( $assets ) . " tài nguyên\n";
foreach ( $bad as $b ) { echo "  trang lỗi: {$b}\n"; }
foreach ( $missing as $m ) { echo "  thiếu tài nguyên: {$m}\n"; }
foreach ( array_slice( $no_h1, 0, 10 ) as $m ) { echo "  số H1 khác 1: {$m}\n"; }
foreach ( array_slice( array_unique( $dup_ids ), 0, 10 ) as $m ) { echo "  id trùng: {$m}\n"; }
foreach ( array_slice( array_unique( $no_alt ), 0, 10 ) as $m ) { echo "  ảnh thiếu alt: {$m}\n"; }
foreach ( array_slice( array_unique( $mixed ), 0, 10 ) as $m ) { echo "  tài nguyên ngoài miền: {$m}\n"; }
echo "Danh sách trang: " . implode( ' ', array_keys( $seen ) ) . "\n";

check( 'mọi trang nội bộ trả 200', ! $bad );
check( 'mọi tài nguyên tải được', ! $missing );
check( 'mỗi trang đúng một H1', ! $no_h1 );
check( 'không trùng id', ! $dup_ids );
check( 'mọi ảnh có thuộc tính alt', ! $no_alt );
check( 'không tải tài nguyên từ miền ngoài', ! $mixed );
finish();

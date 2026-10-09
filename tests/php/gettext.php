<?php

[ , $domain, $mo_file, $json_file ] = $argv + array( null, null, null, null );
if ( ! function_exists( 'dgettext' ) ) {
	echo "BỎ QUA: PHP không có phần mở rộng gettext\n";
	exit( 0 );
}

$entries = json_decode( (string) file_get_contents( (string) $json_file ), true );
$dir     = sys_get_temp_dir() . '/vgarco-gettext-' . getmypid();
@mkdir( $dir . '/de_DE/LC_MESSAGES', 0777, true );
copy( (string) $mo_file, $dir . '/de_DE/LC_MESSAGES/' . $domain . '.mo' );
register_shutdown_function(
	static function () use ( $dir, $domain ): void {
		@unlink( $dir . '/de_DE/LC_MESSAGES/' . $domain . '.mo' );
		@rmdir( $dir . '/de_DE/LC_MESSAGES' );
		@rmdir( $dir . '/de_DE' );
		@rmdir( $dir );
	}
);

putenv( 'LANGUAGE=de_DE' );
putenv( 'LC_ALL=de_DE.UTF-8' );
setlocale( LC_ALL, 'de_DE.UTF-8', 'de_DE', 'German_Germany.1252', 'de' );
bindtextdomain( $domain, $dir );
bind_textdomain_codeset( $domain, 'UTF-8' );

$probe = null;
foreach ( $entries as $e ) {
	if ( null === $e['plural'] && '' !== $e['str'][0] && $e['id'] !== $e['str'][0] ) {
		$probe = $e;
		break;
	}
}
if ( $probe && dgettext( $domain, $probe['id'] ) !== $probe['str'][0] ) {
	echo "BỎ QUA: libintl không dịch được (thiếu locale đức trên hệ điều hành này)\n";
	exit( 0 );
}

$bad = array();
foreach ( $entries as $e ) {
	if ( null === $e['plural'] ) {
		$got = dgettext( $domain, $e['id'] );
		if ( $got !== $e['str'][0] ) {
			$bad[] = array( $e['id'], $e['str'][0], $got );
		}
		continue;
	}
	foreach ( array( 1, 2, 5 ) as $n ) {
		$expect = $e['str'][ 1 === $n ? 0 : 1 ] ?? '';
		$got    = dngettext( $domain, $e['id'], $e['plural'], $n );
		if ( $got !== $expect ) {
			$bad[] = array( $e['id'] . " (n={$n})", $expect, $got );
		}
	}
}

if ( $bad ) {
	foreach ( array_slice( $bad, 0, 10 ) as [ $id, $expect, $got ] ) {
		echo '  ✗ ' . json_encode( $id, JSON_UNESCAPED_UNICODE ) . ' → mong đợi ' . json_encode( $expect, JSON_UNESCAPED_UNICODE ) . ', libintl trả ' . json_encode( $got, JSON_UNESCAPED_UNICODE ) . "\n";
	}
	echo 'THẤT BẠI ' . count( $bad ) . ' / ' . count( $entries ) . "\n";
	exit( 1 );
}
echo 'ĐẠT ' . count( $entries ) . '/' . count( $entries ) . " mục (libintl đọc .mo khớp .po)\n";

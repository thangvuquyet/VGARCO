<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );

$pass = 0;
$fail = array();

function check( string $label, bool $condition ): void {
	global $pass, $fail;
	if ( $condition ) {
		++$pass;
	} else {
		$fail[] = $label;
	}
}

$theme_setup = (string) file_get_contents( ROOT . '/wp-content/themes/vgarco/inc/setup.php' );
$stubs       = array();
if ( preg_match( '/if \( ! function_exists\( \'vgarco_option\' \) \) \{(.*?)\n\}\n/s', $theme_setup, $block ) ) {
	preg_match_all( '/^\tfunction (vgarco_\w+)/m', $block[1], $names );
	$stubs = $names[1];
}
check( 'theme: tìm thấy các hàm dự phòng (vgarco_option, vgarco_directions_url)', array( 'vgarco_option', 'vgarco_directions_url' ) === $stubs );

$helpers = (string) file_get_contents( ROOT . '/wp-content/plugins/vgarco-core/inc/helpers.php' );
foreach ( $stubs as $name ) {
	check( "plugin: {$name} chỉ được khai báo khi chưa có", false !== strpos( $helpers, "if ( ! function_exists( '{$name}' ) ) {" ) );
	eval( "function {$name}() { return 'theme-du-phong'; }" );
}

require_once ROOT . '/wp-content/plugins/vgarco-core/inc/helpers.php';

check( 'plugin nạp sau hàm dự phòng của theme không gây khai báo lại', true );
check( 'plugin vẫn nạp các hàm còn lại', function_exists( 'vgarco_dot_get' ) && function_exists( 'vgarco_client_ip' ) );
check( 'hàm dự phòng của theme còn nguyên trong lần nạp đó', 'theme-du-phong' === vgarco_option( 'x' ) );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

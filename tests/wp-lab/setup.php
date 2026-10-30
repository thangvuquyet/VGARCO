<?php

define( 'LAB', __DIR__ . '/.lab' );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/';
require LAB . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';

function out( $m ) {
	echo $m . "\n";
}

foreach ( array( 'secure-custom-fields/secure-custom-fields.php', 'polylang/polylang.php', 'vgarco-core/vgarco-core.php', 'lab-mail-capture/lab-mail-capture.php' ) as $plugin ) {
	if ( ! is_plugin_active( $plugin ) ) {
		$r = activate_plugin( $plugin );
		out( ( is_wp_error( $r ) ? 'LỖI ' . $r->get_error_message() : 'đã kích hoạt' ) . ": {$plugin}" );
	}
}
switch_theme( 'vgarco' );
out( 'theme: ' . get_stylesheet() );

update_option( 'permalink_structure', '/%postname%/' );
update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );
update_option( 'blogdescription', 'Tư vấn thiết kế, giám sát và quản lý dự án xây dựng' );

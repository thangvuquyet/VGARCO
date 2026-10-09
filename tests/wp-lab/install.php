<?php

define( 'LAB', __DIR__ . '/.lab' );
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';
define( 'WP_INSTALLING', true );
require LAB . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if ( is_blog_installed() ) {
	echo "WordPress đã được cài.\n";
} else {
	$password = bin2hex( random_bytes( 9 ) ) . 'Aa1!';
	file_put_contents( LAB . '/credentials.txt', "user: labadmin\npassword: {$password}\n" );
	$result = wp_install( 'VGARCO Lab', 'labadmin', 'lab@example.test', true, '', $password, 'vi' );
	echo "Đã cài WordPress, user id {$result['user_id']} (mật khẩu trong .lab/credentials.txt)\n";
}

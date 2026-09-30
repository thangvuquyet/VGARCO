<?php
/**
 * Giao diện VGARCO — chỉ nạp các tệp trong inc/.
 * Kiểu nội dung, phân loại và trường dữ liệu nằm ở plugin vgarco-core, không nằm ở đây.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

const VGARCO_THEME_VERSION = '0.1.0';

require_once get_theme_file_path( 'inc/setup.php' );
require_once get_theme_file_path( 'inc/cleanup.php' );
require_once get_theme_file_path( 'inc/enqueue.php' );
require_once get_theme_file_path( 'inc/query.php' );
require_once get_theme_file_path( 'inc/models.php' );
require_once get_theme_file_path( 'inc/menus.php' );
require_once get_theme_file_path( 'inc/template-tags.php' );

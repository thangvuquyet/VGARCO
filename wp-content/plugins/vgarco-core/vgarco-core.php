<?php
/**
 * Plugin Name: VGARCO Core
 * Description: Dữ liệu và nghiệp vụ của website VGARCO: kiểu nội dung, phân loại, trường dữ liệu, cấu hình chung. Độc lập với giao diện — đổi theme không mất dữ liệu (yêu cầu 5.7.1).
 * Version:     0.1.0
 * Requires at least: 6.6
 * Requires PHP: 8.3
 * Text Domain: vgarco-core
 * Domain Path: /languages
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

const VGARCO_CORE_VERSION = '0.1.0';

define( 'VGARCO_CORE_FILE', __FILE__ );
define( 'VGARCO_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once VGARCO_CORE_DIR . 'inc/helpers.php';
require_once VGARCO_CORE_DIR . 'inc/alerts.php';
require_once VGARCO_CORE_DIR . 'inc/security-lib.php';
require_once VGARCO_CORE_DIR . 'inc/cache-lib.php';
require_once VGARCO_CORE_DIR . 'inc/careers/jobs.php';
require_once VGARCO_CORE_DIR . 'inc/search-lib.php';
require_once VGARCO_CORE_DIR . 'inc/careers/captcha.php';
require_once VGARCO_CORE_DIR . 'inc/careers/apply-validate.php';
require_once VGARCO_CORE_DIR . 'inc/careers/apply-process.php';
require_once VGARCO_CORE_DIR . 'inc/careers/applications-lib.php';
require_once VGARCO_CORE_DIR . 'inc/post-types.php';
require_once VGARCO_CORE_DIR . 'inc/careers/apply-wp.php';
require_once VGARCO_CORE_DIR . 'inc/careers/applications.php';
require_once VGARCO_CORE_DIR . 'inc/search.php';
require_once VGARCO_CORE_DIR . 'inc/security.php';
require_once VGARCO_CORE_DIR . 'inc/cache.php';
require_once VGARCO_CORE_DIR . 'inc/cli/import.php';
require_once VGARCO_CORE_DIR . 'inc/taxonomies.php';
require_once VGARCO_CORE_DIR . 'inc/seed.php';
require_once VGARCO_CORE_DIR . 'inc/fields.php';
require_once VGARCO_CORE_DIR . 'inc/options.php';
require_once VGARCO_CORE_DIR . 'inc/media-lib.php';
require_once VGARCO_CORE_DIR . 'inc/media.php';
require_once VGARCO_CORE_DIR . 'inc/editor.php';

/**
 * Kích hoạt: đăng ký kiểu nội dung, tạo sẵn các phân loại cố định, làm mới đường dẫn.
 * Chỉ làm mới đường dẫn ở đây — làm mỗi lần tải trang sẽ làm chậm toàn site.
 */
function vgarco_core_activate(): void {
	vgarco_register_taxonomies(); // trước kiểu nội dung, cùng thứ tự với lúc chạy thường (xem taxonomies.php)
	vgarco_register_post_types();
	vgarco_seed_terms();
	flush_rewrite_rules();
}
register_activation_hook( VGARCO_CORE_FILE, 'vgarco_core_activate' );

function vgarco_core_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( VGARCO_CORE_FILE, 'vgarco_core_deactivate' );

add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'vgarco-core', false, dirname( plugin_basename( VGARCO_CORE_FILE ) ) . '/languages' );
	}
);

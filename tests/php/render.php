<?php
/**
 * Chạy các mẫu trang THẬT của theme (header.php, index.php, footer.php) với hàm giả tối thiểu của WordPress,
 * ghi HTML ra tests/out/frame.html. Mọi cảnh báo/notice PHP bị đổi thành lỗi để không lọt vào production.
 *
 *   php -d extension_dir=<ext> -d extension=mbstring tests/php/render.php
 *
 * HTML này dùng để kiểm tra markup và chạy thử JS trên trình duyệt (tests/serve.mjs). Không thay cho chạy thử
 * trên WordPress thật.
 */

error_reporting( E_ALL );
set_error_handler(
	static function ( int $no, string $message, string $file, int $line ): bool {
		throw new ErrorException( $message, 0, $no, $file, $line );
	}
);

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );
define( 'THEME', ROOT . '/wp-content/themes/vgarco' );
define( 'VGARCO_CORE_DIR', ROOT . '/wp-content/plugins/vgarco-core/' );

/* ---------- Hàm giả của WordPress ---------- */

$GLOBALS['lang'] = $argv[1] ?? 'vi';

// Hệ thống hook tối giản: đủ để chạy inc/enqueue.php thật (wp_enqueue_scripts → wp_head).
$GLOBALS['actions'] = array();
$GLOBALS['styles']  = array();
$GLOBALS['modules'] = array();
function add_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['actions'][ $hook ][ $priority ][] = $callback;
}
function do_action( $hook ) {
	$by_priority = $GLOBALS['actions'][ $hook ] ?? array();
	ksort( $by_priority );
	foreach ( $by_priority as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$callback();
		}
	}
}
function add_filter() {}
function remove_action() {}
function wp_enqueue_style( $handle, $src ) {
	$GLOBALS['styles'][ $handle ] = $src;
}
function wp_enqueue_script_module( $id, $src ) {
	$GLOBALS['modules'][ $id ] = $src;
}
function get_theme_file_uri( $p = '' ) {
	return '/' . ltrim( $p, '/' ); // tests/serve.mjs phục vụ theme/dist dưới /dist/
}
function current_user_can() {
	return false;
}
function determine_locale() {
	return 'vi' === $GLOBALS['lang'] ? 'vi_VN' : 'de_DE';
}
function __( $t ) {
	return $t;
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false );
}
function esc_attr( $s ) {
	return esc_html( $s );
}
function esc_url( $s ) {
	return esc_html( $s );
}
function esc_html__( $t ) {
	return esc_html( $t );
}
function esc_attr__( $t ) {
	return esc_attr( $t );
}
function esc_html_e( $t ) {
	echo esc_html( $t );
}
function esc_attr_e( $t ) {
	echo esc_attr( $t );
}
function get_field( $n, $p = false ) {
	return null;
}
function home_url( $path = '' ) {
	return '/' . ltrim( $path, '/' );
}
function get_theme_file_path( $p = '' ) {
	return THEME . '/' . ltrim( $p, '/' );
}
function language_attributes() {
	echo 'lang="vi"';
}
function bloginfo() {
	echo 'UTF-8';
}
function wp_head() {
	echo '<title>Khung thử — VGARCO</title>' . "\n";
	do_action( 'wp_enqueue_scripts' );
	do_action( 'wp_head' );
	foreach ( $GLOBALS['styles'] as $src ) {
		printf( "<link rel=\"stylesheet\" href=\"%s\">\n", esc_url( $src ) );
	}
	foreach ( $GLOBALS['modules'] as $src ) {
		printf( "<script type=\"module\" src=\"%s\"></script>\n", esc_url( $src ) );
	}
}
function wp_footer() {}
function wp_body_open() {}
function body_class() {
	echo 'class="home"';
}
function wp_date( $f ) {
	return date( $f );
}
function sanitize_key( $k ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $k ) );
}
function get_template_part( $slug ) {
	require THEME . '/' . $slug . '.php';
}
function get_header() {
	require THEME . '/header.php';
}
function get_footer() {
	require THEME . '/footer.php';
}
function get_nav_menu_locations() {
	return array();
}
function get_post_type_archive_link( $t ) {
	return array( 'du_an' => '/du-an/', 'tin_tuc' => '/tin-tuc/' )[ $t ] ?? false;
}
function is_wp_error( $x ) {
	return false;
}
function is_front_page() {
	return true;
}
function is_404() {
	return false;
}
function is_singular() {
	return false;
}
function is_post_type_archive() {
	return false;
}
function is_tax() {
	return false;
}
function is_page() {
	return false;
}
function get_page_template_slug() {
	return '';
}
function get_terms( $args ) {
	require_once VGARCO_CORE_DIR . 'inc/seed.php';
	$out = array();
	$id  = 1;
	foreach ( vgarco_seed_term_map()[ $args['taxonomy'] ] ?? array() as $slug => $name ) {
		$out[] = (object) array( 'term_id' => $id++, 'slug' => $slug, 'name' => html_entity_decode( $name ), 'taxonomy' => $args['taxonomy'] );
	}
	return $out;
}
function get_term_link( $term ) {
	return ( 'loai_cong_trinh' === $term->taxonomy ? '/du-an/loai/' : '/tin-tuc/chuyen-muc/' ) . $term->slug . '/';
}
function apply_filters( $hook, $value = null, ...$args ) {
	if ( 'wpml_active_languages' === $hook ) {
		$vi = 'vi' === $GLOBALS['lang'];
		return array(
			'vi' => array( 'url' => '/', 'active' => $vi ? 1 : 0, 'native_name' => 'Tiếng Việt' ),
			'de' => array( 'url' => '/de/', 'active' => $vi ? 0 : 1, 'native_name' => 'Deutsch' ),
		);
	}
	return $value;
}

// Vòng lặp giả cho index.php.
$GLOBALS['posts'] = array( (object) array( 'title' => 'Bài thử', 'content' => '<p>Nội dung thử.</p>' ) );
$GLOBALS['i']     = -1;
function have_posts() {
	return $GLOBALS['i'] + 1 < count( $GLOBALS['posts'] );
}
function the_post() {
	++$GLOBALS['i'];
}
function post_class() {
	echo 'class="entry"';
}
function the_title() {
	echo esc_html( $GLOBALS['posts'][ $GLOBALS['i'] ]->title );
}
function the_permalink() {
	echo '/bai-thu/';
}
function the_content() {
	echo $GLOBALS['posts'][ $GLOBALS['i'] ]->content;
}
function the_excerpt() {
	the_content();
}

/* ---------- Nạp mã thật và kết xuất ---------- */

require_once VGARCO_CORE_DIR . 'inc/helpers.php';
require_once THEME . '/inc/menus.php';
require_once THEME . '/inc/template-tags.php';
require_once THEME . '/inc/enqueue.php';

ob_start();
require THEME . '/index.php';
$html = ob_get_clean();

$out = ROOT . '/tests/out';
if ( ! is_dir( $out ) ) {
	mkdir( $out, 0777, true );
}
file_put_contents( $out . '/frame.html', $html );

echo 'Đã kết xuất ' . strlen( $html ) . " byte → tests/out/frame.html, không có cảnh báo PHP.\n";

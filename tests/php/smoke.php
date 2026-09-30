<?php
/**
 * Kiểm thử khói cho vgarco-core, mu-plugin và các hàm thuần của theme.
 * Chạy không cần WordPress: nạp mã thật với một số hàm giả tối thiểu.
 *
 *   php -d extension_dir=<ext> -d extension=mbstring tests/php/smoke.php
 *
 * Kiểm thử này KHÔNG thay cho chạy thử trên WordPress + WPML + ACF PRO thật.
 * Nó chỉ bắt lỗi logic ở phần không phụ thuộc WordPress.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );

/* ---------- Hàm giả của WordPress ---------- */

$GLOBALS['hooks']   = array();
$GLOBALS['env']     = 'production';
$GLOBALS['fields']  = array();
$GLOBALS['queried'] = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function register_activation_hook() {}
function register_deactivation_hook() {}
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}
function __( $text ) {
	return $text;
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
function wp_get_environment_type() {
	return $GLOBALS['env'];
}
function get_field( $name, $post_id = false ) {
	return $GLOBALS['fields'][ $name ] ?? null;
}
function error_log_stub() {}

/* ---------- Tiện ích kiểm thử ---------- */

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

function same( string $label, $expected, $actual ): void {
	check( $label . ' — mong đợi ' . var_export( $expected, true ) . ', nhận ' . var_export( $actual, true ), $expected === $actual );
}

/* ========== 1. vgarco-core ========== */

define( 'VGARCO_CORE_DIR', ROOT . '/wp-content/plugins/vgarco-core/' );
foreach ( array( 'helpers', 'post-types', 'taxonomies', 'seed', 'fields', 'media' ) as $file ) {
	require_once VGARCO_CORE_DIR . "inc/{$file}.php";
}

// --- vgarco_dot_get ---
$tree = array( 'a' => array( 'b' => array( 'c' => 5 ) ), 'z' => null );
same( 'dot_get: lồng 3 cấp', 5, vgarco_dot_get( $tree, 'a.b.c' ) );
same( 'dot_get: khóa không có', null, vgarco_dot_get( $tree, 'a.x.c' ) );
same( 'dot_get: qua giá trị không phải mảng', null, vgarco_dot_get( $tree, 'a.b.c.d' ) );
same( 'dot_get: giá trị null có thật', null, vgarco_dot_get( $tree, 'z' ) );

// --- vgarco_option: đã lưu thắng mặc định; trống thì lấy mặc định; không có gì thì $fallback ---
$GLOBALS['fields'] = array();
same( 'option: chưa lưu → mặc định (điện thoại)', '0988 961 666', vgarco_option( 'lien_he.dien_thoai' ) );
same( 'option: chưa lưu → mặc định (tên công ty)', 'Công ty TNHH Tư vấn Xây dựng VGARCO', vgarco_option( 'phap_nhan.ten' ) );
same( 'option: không có ở đâu → fallback', 'x', vgarco_option( 'khong.ton_tai', 'x' ) );

$GLOBALS['fields'] = array( 'lien_he' => array( 'dien_thoai' => '0900 000 000', 'email_chung' => '' ) );
same( 'option: đã lưu thắng mặc định', '0900 000 000', vgarco_option( 'lien_he.dien_thoai' ) );
same( 'option: đã lưu nhưng rỗng → dùng mặc định', 'vgarco.cc@gmail.com', vgarco_option( 'lien_he.email_chung' ) );

$GLOBALS['fields'] = array( 'chi_duong_url' => 'https://maps.example/x' );
same( 'directions: dùng liên kết đã nhập', 'https://maps.example/x', vgarco_directions_url() );
$GLOBALS['fields'] = array();
check( 'directions: tự tạo từ địa chỉ, có mã hóa URL', str_starts_with( vgarco_directions_url(), 'https://www.google.com/maps/search/?api=1&query=' ) && ! str_contains( vgarco_directions_url(), ' ' ) );
check( 'directions: địa chỉ nhiều dòng được nối lại (không còn %0A / %0D)', ! str_contains( vgarco_directions_url(), '%0A' ) && ! str_contains( vgarco_directions_url(), '%0D' ) );
$GLOBALS['fields'] = array( 'phap_nhan' => array( 'dia_chi_van_phong' => "A,\r\n  B\n\nC" ) );
same( 'directions: nhiều dòng trống và dấu cách thừa → một dấu cách', 'https://www.google.com/maps/search/?api=1&query=A%2C%20B%20C', vgarco_directions_url() );
$GLOBALS['fields'] = array();

$home = vgarco_option( 'chi_so_trang_chu' );
same( 'chỉ số trang chủ: mặc định có 3 mục', 3, count( $home ) );
same( 'chỉ số trang chủ: mục đầu là 13+', '13+', $home[0]['so'] . $home[0]['hau_to'] );

// --- Nhãn tiếng Việt ---
$labels = vgarco_cpt_labels( 'Dự án' );
same( 'nhãn: chữ thường có dấu', 'Thêm dự án mới', $labels['add_new_item'] );
same( 'nhãn: nhiều từ', 'Thêm vị trí tuyển dụng mới', vgarco_cpt_labels( 'Vị trí tuyển dụng' )['add_new_item'] );

// --- Dữ liệu mồi ---
$seed = vgarco_seed_term_map();
same( '7 loại công trình', 7, count( $seed['loai_cong_trinh'] ) );
same( '3 chuyên mục tin', 3, count( $seed['chuyen_muc'] ) );
check( 'slug loại công trình chỉ gồm a-z, số, gạch ngang', 0 === count( array_filter( array_keys( $seed['loai_cong_trinh'] ), fn( $s ) => ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s ) ) ) );
check( '"Y tế" có trong danh sách (chưa có dự án nhưng phải tạo sẵn)', isset( $seed['loai_cong_trinh']['y-te'] ) );

// --- Trường ACF: cấu trúc hợp lệ ---
$groups = vgarco_field_groups();
same( 'số nhóm trường (4 kiểu nội dung + 3 nhóm cấu hình + 7 nhóm trang)', 14, count( $groups ) );

$keys  = array();
$names = array();
$walk  = function ( array $fields, string $where ) use ( &$walk, &$keys ) {
	foreach ( $fields as $field ) {
		$key = $field['key'] ?? '';
		check( "trường {$where}/{$field['name']}: khóa bắt đầu bằng field_", str_starts_with( $key, 'field_' ) );
		check( "khóa trùng: {$key}", ! isset( $keys[ $key ] ) );
		$keys[ $key ] = true;
		check( "trường {$where}/{$field['name']}: tên dạng snake_case", (bool) preg_match( '/^[a-z][a-z0-9_]*$/', $field['name'] ) );
		check( "trường {$where}/{$field['name']}: có nhãn", '' !== trim( $field['label'] ?? '' ) );
		check( "trường {$where}/{$field['name']}: wpml_cf_preferences hợp lệ", in_array( $field['wpml_cf_preferences'] ?? -1, array( 0, 1, 2, 3 ), true ) );
		if ( 'select' === $field['type'] ) {
			check( "select {$where}/{$field['name']}: có lựa chọn", ! empty( $field['choices'] ) );
			if ( isset( $field['default_value'] ) ) {
				check( "select {$where}/{$field['name']}: mặc định nằm trong lựa chọn", isset( $field['choices'][ $field['default_value'] ] ) );
			}
		}
		if ( in_array( $field['type'], array( 'repeater', 'group' ), true ) ) {
			check( "{$field['type']} {$where}/{$field['name']}: có trường con", ! empty( $field['sub_fields'] ) );
			$walk( $field['sub_fields'] ?? array(), "{$where}/{$field['name']}" );
		}
	}
};

$group_keys = array();
foreach ( $groups as $group ) {
	check( "nhóm {$group['key']}: khóa bắt đầu bằng group_", str_starts_with( $group['key'], 'group_' ) );
	check( "khóa nhóm trùng: {$group['key']}", ! isset( $group_keys[ $group['key'] ] ) );
	$group_keys[ $group['key'] ] = true;
	check( "nhóm {$group['key']}: có vị trí hiển thị", ! empty( $group['location'] ) );
	$walk( $group['fields'], $group['key'] );
}

// Các trường bắt buộc theo phương án 6.2 phải có mặt.
$du_an = array_column( $groups[0]['fields'], null, 'name' );
foreach ( array( 'ma', 'dia_diem', 'quoc_gia', 'thoi_gian', 'vai_tro', 'pham_vi', 'chu_dau_tu', 'doi_tac', 'cong_bo_chu_dau_tu', 'anh_dau_trang', 'anh_the', 'anh_trinh_chieu', 'loai_anh_dai_dien', 'thu_vien', 'noi_bat', 'tieu_bieu', 'mo_ta_seo' ) as $name ) {
	check( "du_an có trường {$name}", isset( $du_an[ $name ] ) );
}
same( 'du_an: ảnh đầu trang bắt buộc', 1, $du_an['anh_dau_trang']['required'] );
same( 'du_an: chú thích ảnh là chữ hiển thị → dịch', 2, array_column( $du_an['thu_vien']['sub_fields'], null, 'name' )['chu_thich']['wpml_cf_preferences'] );
same( 'du_an: ảnh trong thư viện → sao chép', 1, array_column( $du_an['thu_vien']['sub_fields'], null, 'name' )['anh']['wpml_cf_preferences'] );
same( 'du_an: thư viện tối đa 15 ảnh', 15, $du_an['thu_vien']['max'] );
same( 'du_an: mặc định KHÔNG công bố chủ đầu tư', 0, $du_an['cong_bo_chu_dau_tu']['default_value'] );

// --- Kiểm tra định dạng mã ---
same( 'slug hợp lệ', true, vgarco_validate_slug_value( true, 'bao-tang-qdnd-lao' ) );
check( 'slug có dấu bị từ chối', is_string( vgarco_validate_slug_value( true, 'bảo-tàng' ) ) );
check( 'slug có chữ hoa bị từ chối', is_string( vgarco_validate_slug_value( true, 'Bao-Tang' ) ) );
check( 'slug có gạch ngang cuối bị từ chối', is_string( vgarco_validate_slug_value( true, 'bao-tang-' ) ) );
same( 'slug rỗng để ACF tự xử lý "bắt buộc"', true, vgarco_validate_slug_value( true, '' ) );
same( 'giữ nguyên lỗi trước đó', 'lỗi cũ', vgarco_validate_slug_value( 'lỗi cũ', 'abc' ) );

// --- Chiều rộng ảnh ---
same( 'kích thước ảnh sinh ra', array( 480, 800, 1200, 1600, 2560 ), vgarco_image_widths() );

/* ========== 2. mu-plugin: chặn thư ở staging ========== */

$GLOBALS['hooks'] = array();
require ROOT . '/wp-content/mu-plugins/vgarco-loader.php';

$mail_filter = null;
foreach ( $GLOBALS['hooks']['pre_wp_mail'] ?? array() as $cb ) {
	$mail_filter = $cb;
}
check( 'mu-plugin đăng ký bộ lọc pre_wp_mail', is_callable( $mail_filter ) );

function run_mail( array $atts, string $env = 'staging' ) {
	global $mail_filter;
	$GLOBALS['env'] = $env;
	return $mail_filter( null, $atts );
}

$logged = ini_set( 'error_log', sys_get_temp_dir() . '/vgarco-smoke.log' );
define( 'VG_MAIL_ALLOW', array( 'thang@example.com', '@vgarco.vn' ) );

same( 'production: không đụng tới thư', null, run_mail( array( 'to' => 'khach@gmail.com' ), 'production' ) );
same( 'local: không đụng tới thư (Mailpit bắt)', null, run_mail( array( 'to' => 'khach@gmail.com' ), 'local' ) );
same( 'staging: người ngoài danh sách bị chặn (báo đã gửi)', true, run_mail( array( 'to' => 'khach@gmail.com' ) ) );
same( 'staging: địa chỉ trong danh sách được gửi', null, run_mail( array( 'to' => 'thang@example.com' ) ) );
same( 'staging: cả tên miền được phép', null, run_mail( array( 'to' => 'hr@vgarco.vn' ) ) );
same( 'staging: dạng "Tên <email>"', null, run_mail( array( 'to' => 'Thắng <thang@example.com>' ) ) );
same( 'staging: mảng người nhận, một người lạ → chặn cả thư', true, run_mail( array( 'to' => array( 'thang@example.com', 'la@evil.com' ) ) ) );
same( 'staging: Bcc ra ngoài trong header dạng chuỗi → chặn', true, run_mail( array( 'to' => 'thang@example.com', 'headers' => "From: a@vgarco.vn\r\nBcc: khach@gmail.com" ) ) );
same( 'staging: Cc ra ngoài trong header dạng mảng → chặn', true, run_mail( array( 'to' => 'thang@example.com', 'headers' => array( 'Cc: khach@gmail.com' ) ) ) );
same( 'staging: Reply-To ra ngoài KHÔNG bị coi là người nhận', null, run_mail( array( 'to' => 'thang@example.com', 'headers' => array( 'Reply-To: khach@gmail.com' ) ) ) );
same( 'staging: so khớp không phân biệt hoa thường', null, run_mail( array( 'to' => 'THANG@Example.com' ) ) );
same( 'staging: tên miền giả mạo kiểu vgarco.vn.evil.com bị chặn', true, run_mail( array( 'to' => 'x@vgarco.vn.evil.com' ) ) );

same( 'vgarco_mail_addresses: trích nhiều địa chỉ', array( 'a@b.vn', 'c@d.com' ), vgarco_mail_addresses( 'Tên A <a@b.vn>, c@d.com' ) );

/* ========== 3. Theme: cây menu và HTML menu ========== */

// Hàm giả tối thiểu cho menus.php / template-tags.php.
function get_nav_menu_locations() {
	return array();
}
function __return_true() {
	return true;
}
function sanitize_key( $k ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $k ) );
}
function printf_( ...$a ) {
	printf( ...$a );
}

require ROOT . '/wp-content/themes/vgarco/inc/models.php';
require ROOT . '/wp-content/themes/vgarco/inc/menus.php';
require ROOT . '/wp-content/themes/vgarco/inc/template-tags.php';

$menu = array(
	vgarco_menu_node( 'Giới thiệu', '/gioi-thieu/', true, 'KHÁM PHÁ VGARCO', array(
		vgarco_menu_node( 'Về công ty', '/gioi-thieu/', true ),
		vgarco_menu_node( 'Ban lãnh đạo', '/ban-lanh-dao/' ),
	) ),
	vgarco_menu_node( 'Liên hệ', '/lien-he/' ),
);

ob_start();
vgarco_render_primary_nav( $menu );
$html = ob_get_clean();

check( 'nav: mục có con là nút, không phải liên kết', str_contains( $html, '<button type="button" class="nav-trigger" aria-current="true" aria-expanded="false" aria-controls="nav-panel-1">' ) );
check( 'nav: bảng ẩn mặc định và khớp aria-controls', str_contains( $html, '<div class="nav-panel" id="nav-panel-1" hidden>' ) );
check( 'nav: nhãn bảng lấy từ mô tả của mục cha', str_contains( $html, '<p class="nav-panel-label">KHÁM PHÁ VGARCO</p>' ) );
check( 'nav: liên kết con đang xem có aria-current="page"', str_contains( $html, '<a href="/gioi-thieu/" aria-current="page">Về công ty</a>' ) );
check( 'nav: liên kết con khác không có aria-current', str_contains( $html, '<a href="/ban-lanh-dao/">Ban lãnh đạo</a>' ) );
check( 'nav: mục không có con là liên kết thường', str_contains( $html, '<a href="/lien-he/">Liên hệ</a>' ) );
same( 'nav: không sinh thẻ đóng thiếu (số <div> mở = số </div> đóng)', substr_count( $html, '<div' ), substr_count( $html, '</div>' ) );

// Thoát HTML: ký tự đặc biệt không được phá markup — ở cả ba chỗ nhãn xuất hiện
// (liên kết lá, nút của mục có con, liên kết con) và ở nhãn bảng.
$evil = 'A "B" <script>alert(1)</script>';

ob_start();
vgarco_render_primary_nav( array( vgarco_menu_node( $evil, '/x/?a=1&b=2' ) ) );
$xss_leaf = ob_get_clean();
check( 'nav: thoát nhãn của liên kết lá', ! str_contains( $xss_leaf, '<script>' ) && str_contains( $xss_leaf, '&lt;script&gt;' ) );

ob_start();
vgarco_render_primary_nav( array( vgarco_menu_node( $evil, '/x/', false, $evil, array( vgarco_menu_node( $evil, '/y/' ) ) ) ) );
$xss_group = ob_get_clean();
check( 'nav: không có thẻ <script> thật nào lọt vào HTML của mục có con', ! str_contains( $xss_group, '<script>' ) );
same( 'nav: nhãn nút, nhãn bảng và liên kết con đều được thoát (3 lần)', 3, substr_count( $xss_group, '&lt;script&gt;' ) );

// Chuỗi nhiều dòng: thoát HTML TRƯỚC khi đổi xuống dòng thành <br>.
same( 'lines: xuống dòng thành <br>', 'Dòng 1<br>Dòng 2', vgarco_lines( "Dòng 1\nDòng 2" ) );
same( 'lines: cắt khoảng trắng đầu/cuối', 'A', vgarco_lines( "\n A \n" ) );
check( 'lines: thoát thẻ HTML nhưng vẫn giữ <br> do mình sinh', vgarco_lines( "<b>x</b>\ny" ) === '&lt;b&gt;x&lt;/b&gt;<br>y' );
check( 'lines: không lọt thẻ <script>', ! str_contains( vgarco_lines( '<script>alert(1)</script>' ), '<script>' ) );

// Biểu tượng: chỉ chấp nhận tên an toàn.
function get_theme_file_path( $p = '' ) {
	return ROOT . '/wp-content/themes/vgarco/' . ltrim( $p, '/' );
}
// Các phép thử này dùng đường dẫn CÓ TỒN TẠI nếu lớp chặn bị bỏ: nhờ vậy chúng phân biệt được
// "bị chặn" với "không có tệp". (Thử '../functions' thì luôn ra rỗng dù có chặn hay không.)
check( 'icon: chặn đi lùi thư mục (../icons/search có thật nếu không chặn)', '' === vgarco_icon( '../icons/search' ) );
check( 'icon: chặn tên rỗng', '' === vgarco_icon( '' ) );
check( 'icon: tên không có tệp trả về rỗng', '' === vgarco_icon( 'khong-co' ) );
$icon = vgarco_icon( 'search', 'x' );
check( 'icon: thêm aria-hidden và class', str_starts_with( $icon, '<svg aria-hidden="true" focusable="false" class="x"' ) );
check( 'icon: mọi biểu tượng dùng trong mẫu trang đều tồn tại', '' !== vgarco_icon( 'phone' ) && '' !== vgarco_icon( 'arrow-up-right' ) && '' !== vgarco_icon( 'arrow-up' ) );

// Trang 404 theo ngữ cảnh: đoán mục từ đường dẫn bị lỗi.
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}
$secs = array(
	'du_an'   => array( 'url' => 'https://vgarco.vn/du-an/', 'match' => true ),
	'tin_tuc' => array( 'url' => '/tin-tuc/', 'match' => true ),
	'careers' => array( 'url' => 'https://vgarco.vn/tuyen-dung/', 'match' => true ),
	'contact' => array( 'url' => '/lien-he/', 'match' => false ),
	'goc'     => array( 'url' => 'https://vgarco.vn/', 'match' => true ),
	'con'     => array( 'url' => '/tuyen-dung/ung-tuyen/', 'match' => true ),
);
same( '404: đường dẫn dưới /du-an/ → du_an', 'du_an', vgarco_url_section( '/du-an/bao-tang/', $secs ) );
same( '404: chính /du-an/ (không có gì phía sau) → du_an', 'du_an', vgarco_url_section( '/du-an/', $secs ) );
same( '404: thiếu dấu gạch cuối → vẫn khớp', 'du_an', vgarco_url_section( '/du-an', $secs ) );
same( '404: hoa thường không ảnh hưởng', 'tin_tuc', vgarco_url_section( '/TIN-TUC/Bai-Cu/', $secs ) );
same( '404: mục dài hơn thắng (/tuyen-dung/ung-tuyen/ hơn /tuyen-dung/)', 'con', vgarco_url_section( '/tuyen-dung/ung-tuyen/x/', $secs ) );
$secs2 = array(
	'loai'  => array( 'url' => '/du-an/loai/', 'match' => true ),
	'du_an' => array( 'url' => '/du-an/', 'match' => true ),
);
same( '404: mục dài đứng TRƯỚC mục ngắn vẫn thắng (không phụ thuộc thứ tự khai báo)', 'loai', vgarco_url_section( '/du-an/loai/x/', $secs2 ) );
same( '404: cùng bộ mục, đường dẫn chỉ khớp mục ngắn', 'du_an', vgarco_url_section( '/du-an/khac/', $secs2 ) );
same( '404: mục ngắn hơn khi không khớp mục dài', 'careers', vgarco_url_section( '/tuyen-dung/vi-tri/', $secs ) );
same( '404: tiền tố chung nhưng khác đoạn (/du-an-khac/) → không khớp', '', vgarco_url_section( '/du-an-khac/x/', $secs ) );
same( '404: mục không có match=true bị bỏ qua (/lien-he/)', '', vgarco_url_section( '/lien-he/x/', $secs ) );
same( '404: mục ở gốc site không khớp mọi thứ', '', vgarco_url_section( '/khong-biet/', $secs ) );
same( '404: đường dẫn gốc → không mục nào', '', vgarco_url_section( '/', $secs ) );
same( '404: đường dẫn rỗng → không mục nào', '', vgarco_url_section( '', $secs ) );

// Bộ chuyển ngôn ngữ (UC-10): mục liên kết và nhánh "chưa có bản dịch".
$langs = array(
	'vi' => array( 'url' => '/', 'active' => 1, 'native_name' => 'Tiếng Việt', 'missing' => 0 ),
	'de' => array( 'url' => '/de/', 'active' => 0, 'native_name' => 'Deutsch', 'missing' => 1 ),
);
$sw = vgarco_switcher_items( $langs, array( 'de' => '/de/du-an/' ) );
same( 'chuyển ngôn ngữ: thiếu bản dịch → dùng địa chỉ dự phòng và đánh dấu missing', array( '/de/du-an/', true ), array( $sw[1]['url'], $sw[1]['missing'] ) );
same( 'chuyển ngôn ngữ: ngôn ngữ đang xem giữ địa chỉ và không bao giờ bị đánh dấu missing', array( '/', true, false ), array( $sw[0]['url'], $sw[0]['active'], $sw[0]['missing'] ) );
same( 'chuyển ngôn ngữ: giữ thứ tự và tên', array( 'vi', 'de' ), array_column( $sw, 'code' ) );
$has = vgarco_switcher_items( array( 'de' => array( 'url' => '/de/bai/', 'active' => 0, 'missing' => 0, 'native_name' => 'Deutsch' ) ), array( 'de' => '/de/du-an/' ) );
same( 'chuyển ngôn ngữ: có bản dịch → liên kết thẳng, dù có địa chỉ dự phòng', array( '/de/bai/', false ), array( $has[0]['url'], $has[0]['missing'] ) );
$nofb = vgarco_switcher_items( $langs, array() );
same( 'chuyển ngôn ngữ: thiếu bản dịch mà không có địa chỉ dự phòng → dùng địa chỉ WPML (không để rỗng)', array( '/de/', true ), array( $nofb[1]['url'], $nofb[1]['missing'] ) );
same( 'chuyển ngôn ngữ: địa chỉ dự phòng rỗng bị bỏ qua', '/de/', vgarco_switcher_items( $langs, array( 'de' => '' ) )[1]['url'] );
same( 'chuyển ngôn ngữ: không có native_name → mã viết hoa', 'FR', vgarco_switcher_items( array( 'fr' => array( 'url' => '/fr/' ) ), array() )[0]['name'] );
same( 'chuyển ngôn ngữ: thiếu cờ missing coi như có bản dịch', false, vgarco_switcher_items( array( 'de' => array( 'url' => '/de/' ) ), array( 'de' => '/x/' ) )[0]['missing'] );

/* ---------- Kết quả ---------- */

echo "\n";
if ( $fail ) {
	echo 'THẤT BẠI ' . count( $fail ) . " / " . ( $pass + count( $fail ) ) . "\n";
	foreach ( $fail as $f ) {
		echo "  ✗ {$f}\n";
	}
	exit( 1 );
}
echo "ĐẠT {$pass} / {$pass} phép kiểm\n";

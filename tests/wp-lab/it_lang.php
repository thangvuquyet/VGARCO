<?php
require __DIR__ . '/lib.php';

$dom  = static function ( string $html ): DOMXPath {
	$d = new DOMDocument();
	libxml_use_internal_errors( true );
	$d->loadHTML( '<?xml encoding="UTF-8">' . $html );
	libxml_clear_errors();
	return new DOMXPath( $d );
};
$attr = static fn( DOMXPath $x, string $q, string $a = 'content' ): string => $x->query( $q )->length ? $x->query( $q )->item( 0 )->getAttribute( $a ) : '';
$path = static fn( string $url ): string => (string) wp_parse_url( $url, PHP_URL_PATH );
$tmp  = LAB . '/tmp-lang';
@mkdir( $tmp, 0777, true );

check( 'Polylang đang hoạt động', is_plugin_active( 'polylang/polylang.php' ) );
same( 'hai ngôn ngữ, tiếng Việt đứng đầu', array( 'vi', 'de' ), pll_languages_list() );
same( 'ngôn ngữ mặc định là tiếng Việt', 'vi', pll_default_language() );
$o = PLL()->options;
same( 'cài đặt: không tiền tố cho tiếng Việt, /de/ cho tiếng Đức, trang chủ là /de/, không đoán ngôn ngữ trình duyệt', array( true, 1, true, false ), array( $o['hide_default'], (int) $o['force_lang'], $o['redirect_lang'], $o['browser'] ) );
foreach ( array( 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri' ) as $t ) {
	check( "kiểu nội dung {$t} được dịch", PLL()->model->is_translated_post_type( $t ) );
}
check( 'hồ sơ ứng tuyển KHÔNG được dịch', ! PLL()->model->is_translated_post_type( 'ho_so_ut' ) );
foreach ( array( 'loai_cong_trinh', 'chuyen_muc', 'noi_lam_viec', 'phong_ban' ) as $t ) {
	check( "phân loại {$t} được dịch", PLL()->model->is_translated_taxonomy( $t ) );
}
same( 'sau khi cấu hình ngôn ngữ: trang quản trị không bị chuyển sang trình hướng dẫn của Polylang', false, get_transient( 'pll_activation_redirect' ) );
$again = vgarco_setup_languages();
same( 'chạy lại lệnh cấu hình ngôn ngữ: không tạo thêm, không đổi cài đặt', array( true, array( '= ngôn ngữ vi: đã có', '= ngôn ngữ de: đã có' ) ), array( $again['ok'], array_slice( $again['log'], 0, 2 ) ) );
same( 'giao diện WPML của Polylang trả đủ hai ngôn ngữ cho theme', array( 'vi', 'de' ), array_keys( (array) apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) ) );

$home = http( 'GET', '/' );
$de   = http( 'GET', '/de/', array( 'headers' => array( 'Accept-Language: de-DE,de;q=0.9' ) ) );
same( '/ : 200 và lang=vi', array( 200, 'vi' ), array( $home['status'], $attr( $dom( $home['body'] ), '//html', 'lang' ) ) );
same( '/de/ : 200 và lang=de-DE', array( 200, 'de-DE' ), array( $de['status'], $attr( $dom( $de['body'] ), '//html', 'lang' ) ) );
$guess = http( 'GET', '/', array( 'headers' => array( 'Accept-Language: de-DE,de;q=0.9' ) ) );
same( '/ với trình duyệt tiếng Đức: không bị chuyển hướng (không đoán ngôn ngữ, giữ bộ nhớ đệm Cloudflare)', 200, $guess['status'] );
$slug = http( 'GET', '/de/startseite/' );
same( '/de/startseite/ chuyển về /de/', array( 301, true ), array( $slug['status'], str_ends_with( trim( (string) preg_replace( '/^.*?Location:\s*(\S+).*$/ismu', '$1', $slug['headers'] ) ), '/de/' ) ) );

$pairs = array(
	'/gioi-thieu/'                 => '/de/ueber-uns/',
	'/gioi-thieu/ban-lanh-dao/'    => '/de/ueber-uns/geschaeftsfuehrung/',
	'/gioi-thieu/phong-ban/'       => '/de/ueber-uns/abteilungen/',
	'/linh-vuc-hoat-dong/'         => '/de/leistungen/',
	'/tuyen-dung/'                 => '/de/karriere/',
	'/ung-tuyen/'                  => '/de/bewerbung/',
	'/lien-he/'                    => '/de/kontakt/',
	'/chinh-sach-bao-ve-du-lieu/'  => '/de/datenschutz/',
);
foreach ( $pairs as $vi_path => $de_path ) {
	$a = http( 'GET', $vi_path );
	$b = http( 'GET', $de_path );
	same( "{$vi_path} ↔ {$de_path}: cùng trả 200", array( 200, 200 ), array( $a['status'], $b['status'] ) );
	$xa = $dom( $a['body'] );
	$xb = $dom( $b['body'] );
	same( "{$vi_path}: bộ chuyển ngôn ngữ trỏ thẳng sang trang tiếng Đức, không báo thiếu bản dịch", array( $de_path, false ), array( $path( $attr( $xa, '//div[contains(@class,"language-switch")]/a[@hreflang="de"]', 'href' ) ), $xa->query( '//div[contains(@class,"language-switch")]/a[@hreflang="de" and @data-lang-missing]' )->length > 0 ) );
	same( "{$de_path}: bộ chuyển ngôn ngữ trỏ ngược về trang tiếng Việt", $vi_path, $path( $attr( $xb, '//div[contains(@class,"language-switch")]/a[@hreflang="vi"]', 'href' ) ) );
	same( "{$vi_path}: thẻ hreflang của Polylang cho cả hai ngôn ngữ", array( $vi_path, $de_path ), array( $path( $attr( $xa, '//link[@rel="alternate" and @hreflang="vi"]', 'href' ) ), $path( $attr( $xa, '//link[@rel="alternate" and @hreflang="de"]', 'href' ) ) ) );
	same( "{$de_path}: lang=de-DE", 'de-DE', $attr( $xb, '//html', 'lang' ) );
}

$job = http( 'GET', '/tuyen-dung/vt-kts/' );
$xj  = $dom( $job['body'] );
same( 'vị trí chưa có bản Đức: bộ chuyển DE đưa tới trang Tuyển dụng đã dịch (/de/karriere/), có đánh dấu thiếu bản dịch', array( '/de/karriere/', true ), array( $path( $attr( $xj, '//div[contains(@class,"language-switch")]/a[@hreflang="de"]', 'href' ) ), $xj->query( '//div[contains(@class,"language-switch")]/a[@hreflang="de" and @data-lang-missing]' )->length > 0 ) );
$apply_de = http( 'GET', '/de/bewerbung/?id=' . ( get_option( 'lab_job_ids' )['kts'] ?? 0 ) );
same( 'trang Bewerbung: 200', 200, $apply_de['status'] );

$cli = new VGARCO_CLI_Command();
$run = static function ( array $args, array $assoc ) use ( $cli ): array {
	WP_CLI::$lines  = array();
	WP_CLI::$tables = array();
	try {
		$cli->import( $args, $assoc );
		$error = '';
	} catch ( RuntimeException $e ) {
		$error = $e->getMessage();
	}
	return array( 'lines' => WP_CLI::$lines, 'error' => $error );
};
$header = 'ma;thu_tu;ten_vi;ten_de;slug_vi;slug_de;loai;quoc_gia;dia_diem_vi;dia_diem_de;thoi_gian;vai_tro_vi;vai_tro_de;mo_ta_vi;mo_ta_de';
$row    = 'thu-ngon-ngu;99;Dự án thử ngôn ngữ;Sprachtest-Projekt;du-an-thu-ngon-ngu;sprachtest-projekt;van-hoa-di-tich;la;Viêng Chăn;Vientiane;2020;Thiết kế;Entwurf;Mô tả tiếng Việt.;Deutsche Beschreibung.';
file_put_contents( $tmp . '/lang.csv', $header . "\n" . $row . "\n" );
$r = $run( array( 'projects', $tmp . '/lang.csv' ), array( 'status' => 'publish' ) );
same( 'nhập dự án có bản Đức: không lỗi, tạo 2 bài (VI + DE)', array( '', true ), array( $r['error'], str_contains( implode( "\n", $r['lines'] ), '2 tạo mới' ) ) );
$vi_id = (int) ( get_posts( array( 'post_type' => 'du_an', 'post_status' => 'any', 'meta_key' => 'ma', 'meta_value' => 'thu-ngon-ngu', 'lang' => 'vi', 'fields' => 'ids' ) )[0] ?? 0 );
$de_id = (int) pll_get_post( $vi_id, 'de' );
check( 'bài tiếng Việt và bài tiếng Đức đều có', $vi_id > 0 && $de_id > 0 && $vi_id !== $de_id );
same( 'ngôn ngữ của hai bài', array( 'vi', 'de' ), array( pll_get_post_language( $vi_id ), pll_get_post_language( $de_id ) ) );
same( 'hai bài cùng một nhóm bản dịch', array( 'vi' => $vi_id, 'de' => $de_id ), pll_get_post_translations( $vi_id ) );
same( 'trường bản Đức theo bảng tính', array( 'Sprachtest-Projekt', 'Vientiane', 'Entwurf' ), array( get_the_title( $de_id ), get_field( 'dia_diem', $de_id ), get_field( 'vai_tro', $de_id ) ) );
same( 'trường chỉ-sao-chép (quốc gia) cùng giá trị ở hai bản', array( 'la', 'la' ), array( get_field( 'quoc_gia', $vi_id ), get_field( 'quoc_gia', $de_id ) ) );

$pv = http( 'GET', '/du-an/du-an-thu-ngon-ngu/' );
$pd = http( 'GET', '/de/du-an/sprachtest-projekt/' );
$xv = $dom( $pv['body'] );
$xd = $dom( $pd['body'] );
same( 'trang dự án: 200 ở cả hai ngôn ngữ', array( 200, 200 ), array( $pv['status'], $pd['status'] ) );
same( 'trang dự án tiếng Đức: lang=de-DE và tiêu đề tiếng Đức', array( 'de-DE', true ), array( $attr( $xd, '//html', 'lang' ), str_contains( $pd['body'], 'Sprachtest-Projekt' ) ) );
same( 'trang dự án tiếng Việt: bộ chuyển DE trỏ thẳng sang bản dịch', array( '/de/du-an/sprachtest-projekt/', false ), array( $path( $attr( $xv, '//div[contains(@class,"language-switch")]/a[@hreflang="de"]', 'href' ) ), $xv->query( '//div[contains(@class,"language-switch")]/a[@hreflang="de" and @data-lang-missing]' )->length > 0 ) );
same( 'trang dự án: hreflang hai chiều', array( '/du-an/du-an-thu-ngon-ngu/', '/de/du-an/sprachtest-projekt/' ), array( $path( $attr( $xd, '//link[@rel="alternate" and @hreflang="vi"]', 'href' ) ), $path( $attr( $xd, '//link[@rel="alternate" and @hreflang="de"]', 'href' ) ) ) );
$other = get_posts( array( 'post_type' => 'du_an', 'post_status' => 'publish', 'lang' => 'vi', 'posts_per_page' => 1, 'fields' => 'ids', 'post__not_in' => array( $vi_id ) ) );
if ( $other ) {
	$po = http( 'GET', $path( get_permalink( $other[0] ) ) );
	$xo = $dom( $po['body'] );
	same( 'dự án chưa có bản Đức: bộ chuyển DE đưa tới danh mục /de/du-an/, có đánh dấu thiếu bản dịch', array( '/de/du-an/', true ), array( $path( $attr( $xo, '//div[contains(@class,"language-switch")]/a[@hreflang="de"]', 'href' ) ), $xo->query( '//div[contains(@class,"language-switch")]/a[@hreflang="de" and @data-lang-missing]' )->length > 0 ) );
}
$arch_de = http( 'GET', '/de/du-an/' );
check( 'danh mục tiếng Đức chỉ liệt kê dự án đã dịch', str_contains( $arch_de['body'], 'Sprachtest-Projekt' ) && ! str_contains( $arch_de['body'], 'Bảo tàng Lịch sử' ) );
$arch_vi = http( 'GET', '/du-an/' );
check( 'danh mục tiếng Việt không lẫn bài tiếng Đức', str_contains( $arch_vi['body'], 'Dự án thử ngôn ngữ' ) && ! str_contains( $arch_vi['body'], 'Sprachtest-Projekt' ) );

$r = $run( array( 'projects', $tmp . '/lang.csv' ), array( 'status' => 'publish' ) );
same( 'chạy lại: 0 tạo mới, 0 cập nhật, 2 không đổi (bản Đức tìm đúng theo ngôn ngữ)', true, str_contains( implode( "\n", $r['lines'] ), '0 tạo mới, 0 cập nhật, 2 không đổi' ) );
same( 'chạy lại: vẫn đúng 2 bài thử', 2, count( get_posts( array( 'post_type' => 'du_an', 'post_status' => 'any', 'meta_key' => 'ma', 'meta_value' => 'thu-ngon-ngu', 'lang' => '', 'fields' => 'ids' ) ) ) );

WP_CLI::$tables = array();
$cli->report( array( 'translations' ), array( 'lang' => 'de' ) );
$listed = array_column( WP_CLI::$tables[0] ?? array(), 'ID' );
check( 'report translations: bài đã có bản Đức không bị liệt kê, bài chưa có thì có', ! in_array( $vi_id, $listed, true ) && ( ! $other || in_array( $other[0], $listed, true ) ) );

$blank = (int) wp_insert_post( array( 'post_type' => 'du_an', 'post_status' => 'draft', 'post_title' => 'Bản Đức trống' ) );
vgarco_set_post_language( $blank, 'de' );
PLL()->sync->post_metas->copy( $vi_id, $blank, 'de' );
same( 'tạo bản dịch mới trong trang quản trị: ô sao chép (quốc gia) được chép, ô cần dịch (địa điểm) để trống', array( 'la', '' ), array( get_field( 'quoc_gia', $blank ), (string) get_field( 'dia_diem', $blank ) ) );
check( 'tạo bản dịch mới: khóa tham chiếu của ACF đi cùng giá trị đã chép', '' !== (string) get_post_meta( $blank, '_quoc_gia', true ) && '' === (string) get_post_meta( $blank, '_dia_diem', true ) );
wp_delete_post( $blank, true );

$rows_vi = array(
	array( 'so' => '13', 'hau_to' => '+', 'nhan' => 'Năm kinh nghiệm LABVI' ),
	array( 'so' => '30', 'hau_to' => '', 'nhan' => 'Dự án LABVI' ),
	array( 'so' => '06', 'hau_to' => '', 'nhan' => 'Quốc gia LABVI' ),
);
update_field( 'chi_so_trang_chu', $rows_vi, 'option' );
update_field( 'chi_so_trang_chu_de', array( array( 'nhan' => 'Jahre LABDE' ), array( 'nhan' => 'Projekte LABDE' ), array( 'nhan' => 'Länder LABDE' ) ), 'option' );
wp_cache_flush();
$hv = http( 'GET', '/' );
$hd = http( 'GET', '/de/' );
check( 'cấu hình chỉ số: trang chủ tiếng Việt dùng nhãn tiếng Việt', str_contains( $hv['body'], 'Năm kinh nghiệm LABVI' ) && ! str_contains( $hv['body'], 'LABDE' ) );
check( 'cấu hình chỉ số: trang chủ tiếng Đức dùng nhãn tiếng Đức, vẫn lấy con số của bản gốc', str_contains( $hd['body'], 'Jahre LABDE' ) && ! str_contains( $hd['body'], 'LABVI' ) && str_contains( $hd['body'], '<strong>13<span>+</span></strong>' ) );
delete_field( 'chi_so_trang_chu_de', 'option' );
wp_cache_flush();
$hd2 = http( 'GET', '/de/' );
check( 'xóa bản dịch cấu hình: trang chủ tiếng Đức quay về mặc định tiếng Đức trong mã, không lộ chữ tiếng Việt đã lưu', ! str_contains( $hd2['body'], 'LABVI' ) && ! str_contains( $hd2['body'], 'LABDE' ) );
delete_field( 'chi_so_trang_chu', 'option' );

$admin = lab_cookie_for( (int) get_user_by( 'login', 'labadmin' )->ID );
$cj    = array( 'headers' => array( 'Cookie: ' . $admin ) );
$cfg_vi = http( 'GET', '/wp-admin/admin.php?page=vgarco-cau-hinh', $cj );
$cfg_de = http( 'GET', '/wp-admin/admin.php?page=vgarco-cau-hinh-de', $cj );
same( 'trang cấu hình gốc và trang cấu hình bản Đức đều mở được', array( 200, 200 ), array( $cfg_vi['status'], $cfg_de['status'] ) );
check( 'trang gốc có ô điện thoại, trang bản Đức không có (chỉ sao chép)', str_contains( $cfg_vi['body'], 'field_vg_opt_lien_he_dien_thoai' ) && ! str_contains( $cfg_de['body'], 'field_vg_opt_lien_he_dien_thoai' ) );
check( 'trang bản Đức có ô nhãn chỉ số và địa chỉ (cần dịch)', str_contains( $cfg_de['body'], 'field_vg_opt_cs_home_nhan_de' ) && str_contains( $cfg_de['body'], 'field_vg_opt_phap_nhan_dia_chi_van_phong_de' ) );
check( 'trang bản Đức không có ô con số của chỉ số (chỉ sao chép)', ! str_contains( $cfg_de['body'], 'field_vg_opt_cs_home_so_de' ) );

$edit = http( 'GET', '/wp-admin/post.php?post=' . $vi_id . '&action=edit', $cj );
same( 'màn hình sửa dự án: 200, có chọn ngôn ngữ của Polylang', array( 200, true ), array( $edit['status'], str_contains( $edit['body'], 'post_lang_choice' ) ) );
$new_de = http( 'GET', '/wp-admin/post-new.php?post_type=du_an&from_post=' . $vi_id . '&new_lang=de', $cj );
same( 'tạo bản dịch từ bài tiếng Việt: màn hình mở được, không lỗi', array( 200, false ), array( $new_de['status'], (bool) preg_match( '/Fatal error|Uncaught/', $new_de['body'] ) ) );
$langs_page = http( 'GET', '/wp-admin/admin.php?page=mlang', $cj );
same( 'trang Ngôn ngữ của Polylang mở được', array( 200, true ), array( $langs_page['status'], str_contains( $langs_page['body'], 'Deutsch' ) ) );
$list = http( 'GET', '/wp-admin/edit.php?post_type=du_an&lang=all', $cj );
same( 'danh sách dự án có cột ngôn ngữ', array( 200, true ), array( $list['status'], str_contains( $list['body'], 'language_de' ) || str_contains( $list['body'], 'column-language_vi' ) ) );

foreach ( array( $de_id, $vi_id ) as $pid ) {
	wp_delete_post( $pid, true );
}
$log = is_file( LAB . '/debug.log' ) ? (string) file_get_contents( LAB . '/debug.log' ) : '';
check( 'nhật ký PHP không có lỗi nghiêm trọng khi chạy kịch bản ngôn ngữ', ! preg_match( '/PHP (Fatal|Parse)/', $log ) );
finish();

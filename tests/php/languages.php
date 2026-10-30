<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );

$GLOBALS['hooks']  = array();
$GLOBALS['lang']   = 'vi';
$GLOBALS['stored'] = array();
$GLOBALS['pll']    = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = $callback;
}
function apply_filters( $hook, $value = null, ...$args ) {
	if ( 'wpml_current_language' === $hook ) {
		return $GLOBALS['lang'];
	}
	ksort( $GLOBALS['hooks'][ $hook ] );
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = $callback( $value, ...$args );
		}
	}
	return $value;
}
function __( $text ) {
	return $text;
}
function get_field( $name, $post_id = false ) {
	return $GLOBALS['stored'][ $post_id ][ $name ] ?? null;
}

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

define( 'VGARCO_CORE_DIR', ROOT . '/wp-content/plugins/vgarco-core/' );
foreach ( array( 'helpers', 'fields', 'languages' ) as $file ) {
	require_once VGARCO_CORE_DIR . "inc/{$file}.php";
}

check( 'bộ lọc pll_copy_post_metas đã được đăng ký', ! empty( $GLOBALS['hooks']['pll_copy_post_metas'] ) );

same( 'Polylang dịch 4 kiểu nội dung của VGARCO, không dịch hồ sơ ứng tuyển', array( 'post', 'page', 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri' ), array_keys( apply_filters( 'pll_get_post_types', array( 'post' => 'post', 'page' => 'page' ), false ) ) );
same( 'Polylang: trong màn hình cài đặt, các kiểu của VGARCO bị ẩn (không cho người dùng đổi)', array( 'post' ), array_keys( apply_filters( 'pll_get_post_types', array( 'post' => 'post', 'du_an' => 'du_an' ), true ) ) );
same( 'Polylang dịch 4 phân loại', array( 'category', 'loai_cong_trinh', 'chuyen_muc', 'noi_lam_viec', 'phong_ban' ), array_keys( apply_filters( 'pll_get_taxonomies', array( 'category' => 'category' ), false ) ) );
same( 'Polylang: ẩn 4 phân loại khỏi màn hình cài đặt', array( 'category' ), array_keys( apply_filters( 'pll_get_taxonomies', array( 'category' => 'category', 'chuyen_muc' => 'chuyen_muc' ), true ) ) );

$groups = vgarco_field_groups();
$names  = array();
foreach ( $groups as $group ) {
	foreach ( $group['fields'] as $field ) {
		$names[ $field['name'] ] = $field;
	}
}
$rules = vgarco_translation_rules();
check( 'quy tắc: có mọi trường cấp trên (trừ tab)', array_diff_key( array_filter( $names, static fn( $f ) => 'tab' !== $f['type'] ), $rules ) === array() );
same( 'quy tắc: trường lặp thư viện ảnh có trường con', true, isset( $rules['thu_vien']['subs']['anh'] ) );

$gallery = 'thu_vien';
same( 'khóa: ô văn bản dịch → không sao chép', array( 'dia_diem' => false ), array( 'dia_diem' => vgarco_meta_verdict( 'dia_diem', $rules, false ) ) );
same( 'khóa: ô sao chép → sao chép', true, vgarco_meta_verdict( 'quoc_gia', $rules, false ) );
same( 'khóa: dòng của trường lặp, ảnh (sao chép)', true, vgarco_meta_verdict( "{$gallery}_3_anh", $rules, false ) );
same( 'khóa: dòng của trường lặp, chú thích (dịch)', false, vgarco_meta_verdict( "{$gallery}_3_chu_thich", $rules, false ) );
same( 'khóa: số dòng của trường lặp có ô sao chép → sao chép', true, vgarco_meta_verdict( $gallery, $rules, false ) );
same( 'khóa: chỉ số không phải số → không khớp', null, vgarco_meta_verdict( "{$gallery}_x_anh", $rules, false ) );
same( 'khóa: khóa lạ (plugin khác) → không quyết định', null, vgarco_meta_verdict( '_yoast_x', $rules, false ) );
same( 'khóa: chép một lần được chép khi tạo bản dịch', true, vgarco_entry_copies( array( 'type' => 'text', 'pref' => 3 ), false ) );
same( 'khóa: chép một lần không chép khi đồng bộ', false, vgarco_entry_copies( array( 'type' => 'text', 'pref' => 3 ), true ) );
same( 'khóa: bỏ qua (0) không chép', false, vgarco_entry_copies( array( 'type' => 'text', 'pref' => 0 ), false ) );

$metas = vgarco_copy_post_metas(
	array( 'ma', 'dia_diem', 'quoc_gia', "{$gallery}_0_anh", "{$gallery}_0_chu_thich", $gallery, '_thumbnail_id', 'khoa_la' ),
	false
);
check( 'sao chép: giữ ô sao chép và kèm khóa tham chiếu của ACF', in_array( 'quoc_gia', $metas, true ) && in_array( '_quoc_gia', $metas, true ) );
check( 'sao chép: bỏ ô cần dịch và khóa tham chiếu của nó', ! in_array( 'dia_diem', $metas, true ) && ! in_array( '_dia_diem', $metas, true ) );
check( 'sao chép: ảnh trong thư viện đi cùng khóa tham chiếu, chú thích thì không', in_array( "{$gallery}_0_anh", $metas, true ) && in_array( "_{$gallery}_0_anh", $metas, true ) && ! in_array( "{$gallery}_0_chu_thich", $metas, true ) );
check( 'sao chép: giữ khóa của WordPress và plugin khác', in_array( '_thumbnail_id', $metas, true ) && in_array( 'khoa_la', $metas, true ) );
same( 'sao chép: không trùng khóa', count( $metas ), count( array_unique( $metas ) ) );

$entry = $rules['chi_so_trang_chu'];
$vi    = array(
	array( 'so' => '13', 'hau_to' => '+', 'nhan' => 'Năm hình thành' ),
	array( 'so' => '30', 'hau_to' => '', 'nhan' => 'Dự án' ),
);
$de    = array(
	array( 'nhan' => 'Jahre' ),
	array( 'nhan' => 'Projekte' ),
);
same(
	'gộp bản dịch: trường lặp lấy số từ bản gốc, nhãn từ bản dịch',
	array(
		array( 'so' => '13', 'hau_to' => '+', 'nhan' => 'Jahre' ),
		array( 'so' => '30', 'hau_to' => '', 'nhan' => 'Projekte' ),
	),
	vgarco_merge_translation( $entry, $vi, $de )
);
same(
	'gộp bản dịch: bản dịch thiếu dòng → nhãn rỗng (để mặc định thay thế)',
	array( array( 'so' => '13', 'hau_to' => '+', 'nhan' => 'Jahre' ), array( 'so' => '30', 'hau_to' => '', 'nhan' => null ) ),
	vgarco_merge_translation( $entry, $vi, array( array( 'nhan' => 'Jahre' ) ) )
);
same(
	'gộp bản dịch: nhóm lấy tên công ty từ bản gốc, địa chỉ từ bản dịch',
	array( 'ten' => 'VGARCO', 'ma_so_thue' => '0101', 'dia_chi_dang_ky' => 'Hanoi', 'dia_chi_van_phong' => null ),
	vgarco_merge_translation( $rules['phap_nhan'], array( 'ten' => 'VGARCO', 'ma_so_thue' => '0101', 'dia_chi_dang_ky' => 'Hà Nội' ), array( 'dia_chi_dang_ky' => 'Hanoi' ) )
);

$GLOBALS['stored'] = array(
	'option' => array(
		'chi_so_trang_chu'    => $vi,
		'chi_so_trang_chu_de' => $de,
		'lien_he'             => array( 'dien_thoai' => '0900 000 000' ),
		'lien_he_de'          => array( 'dien_thoai' => 'không được đọc' ),
	),
);
same( 'tùy chọn: tiếng Việt đọc thẳng bản gốc', 'Năm hình thành', vgarco_option( 'chi_so_trang_chu' )[0]['nhan'] );
$GLOBALS['lang'] = 'de';
$home_de         = vgarco_option( 'chi_so_trang_chu' );
same( 'tùy chọn: tiếng Đức lấy nhãn từ bản dịch', 'Jahre', $home_de[0]['nhan'] );
same( 'tùy chọn: tiếng Đức giữ con số của bản gốc', '13', $home_de[0]['so'] );
same( 'tùy chọn: trường chỉ sao chép luôn đọc bản gốc', '0900 000 000', vgarco_option( 'lien_he.dien_thoai' ) );
unset( $GLOBALS['stored']['option']['chi_so_trang_chu_de'] );
same( 'tùy chọn: bản dịch trống → dùng mặc định trong mã (không lộ chữ tiếng Việt đã lưu)', vgarco_option_defaults()['chi_so_trang_chu'][0]['nhan'], vgarco_option( 'chi_so_trang_chu' )[0]['nhan'] );
$GLOBALS['lang'] = 'fr';
same( 'ngôn ngữ chưa hỗ trợ → coi như tiếng Việt', 'vi', vgarco_current_language() );
$GLOBALS['lang'] = 'vi';

$de_groups = vgarco_option_translation_groups( 'de' );
check( 'nhóm bản Đức: có tạo nhóm', count( $de_groups ) >= 2 );
$de_keys = array();
$walk    = static function ( array $fields ) use ( &$walk, &$de_keys ): void {
	foreach ( $fields as $field ) {
		check( "nhóm bản Đức: khóa {$field['key']} có hậu tố _de", str_ends_with( $field['key'], '_de' ) );
		check( "nhóm bản Đức: khóa {$field['key']} không trùng", ! isset( $de_keys[ $field['key'] ] ) );
		$de_keys[ $field['key'] ] = true;
		check( "nhóm bản Đức: {$field['name']} không bắt buộc", empty( $field['required'] ) );
		if ( ! empty( $field['sub_fields'] ) ) {
			$walk( $field['sub_fields'] );
		} else {
			same( "nhóm bản Đức: {$field['name']} chỉ gồm ô cần dịch", 2, $field['wpml_cf_preferences'] );
		}
	}
};
foreach ( $de_groups as $group ) {
	same( "nhóm {$group['key']}: hiển thị ở trang cấu hình bản Đức", 'vgarco-cau-hinh-de', $group['location'][0][0]['value'] );
	$walk( $group['fields'] );
}
$gt_de = array_column( array_merge( ...array_column( $de_groups, 'fields' ) ), null, 'name' );
check( 'nhóm bản Đức: không có số điện thoại (chỉ sao chép)', ! isset( $gt_de['lien_he_de'] ) && ! isset( $gt_de['lien_he'] ) );
check( 'nhóm bản Đức: tên trường có hậu tố _de, không trùng tên nhóm gốc', array() === array_intersect_key( $gt_de, $names ) && isset( $gt_de['chi_so_trang_chu_de'] ) );
same( 'nhóm bản Đức: chỉ số trang chủ chỉ còn ô nhãn', array( 'nhan' ), array_column( $gt_de['chi_so_trang_chu_de']['sub_fields'], 'name' ) );
same( 'nhóm bản Đức: nhóm Pháp nhân chỉ còn hai địa chỉ', array( 'dia_chi_dang_ky', 'dia_chi_van_phong' ), array_column( $gt_de['phap_nhan_de']['sub_fields'], 'name' ) );
check( 'nhóm bản Đức: không nằm trong danh sách nhóm gốc (tránh trùng tên với kiểm tra hình dạng)', 0 === count( array_filter( vgarco_field_groups(), static fn( $g ) => str_ends_with( $g['key'], '_de' ) ) ) );

$pruned = vgarco_prune_translatable( array( array( 'key' => 'field_x', 'name' => 'x', 'type' => 'text', 'required' => 1, 'wpml_cf_preferences' => 2 ), array( 'key' => 'field_y', 'name' => 'y', 'type' => 'text', 'wpml_cf_preferences' => 1 ) ), '_de' );
same( 'cắt bớt: ô bắt buộc ở bản gốc thành tùy chọn ở bản Đức, ô sao chép bị bỏ, tên có hậu tố', array( 1, 0, 'x_de', 'field_x_de' ), array( count( $pruned ), $pruned[0]['required'], $pruned[0]['name'], $pruned[0]['key'] ) );

same( 'đặt ngôn ngữ bài khi không có Polylang: không làm gì', null, vgarco_set_post_language( 5, 'de' ) );
same( 'nối bản dịch khi không có Polylang: không làm gì', null, vgarco_link_translation( 5, 6, 'de' ) );
same( 'ngôn ngữ bài khi không có Polylang: không biết', null, vgarco_post_language( 5 ) );
same( 'bản dịch khi không có Polylang: không có', 0, vgarco_translation_id( 5, 'de' ) );

if ( ! function_exists( 'pll_set_post_language' ) ) {
	function pll_set_post_language( $id, $lang ) {
		$GLOBALS['pll']['lang'][ $id ] = $lang;
	}
	function pll_get_post_translations( $id ) {
		foreach ( $GLOBALS['pll']['groups'] ?? array() as $group ) {
			if ( in_array( $id, $group, true ) ) {
				return $group;
			}
		}
		return array();
	}
	function pll_get_post_language( $id ) {
		return $GLOBALS['pll']['lang'][ $id ] ?? false;
	}
	function pll_get_post( $id, $lang ) {
		return pll_get_post_translations( $id )[ $lang ] ?? 0;
	}
	function pll_save_post_translations( $group ) {
		$GLOBALS['pll']['saved'][] = $group;
		$GLOBALS['pll']['groups']  = array( $group );
	}
}

vgarco_link_translation( 10, 20, 'de' );
same( 'nối bản dịch: bài gốc chưa có nhóm → đặt tiếng Việt cho bài gốc', 'vi', $GLOBALS['pll']['lang'][10] );
same( 'nối bản dịch: nhóm vi + de', array( 'vi' => 10, 'de' => 20 ), $GLOBALS['pll']['saved'][0] );
vgarco_link_translation( 10, 20, 'de' );
same( 'nối bản dịch: chạy lại không ghi lần hai', 1, count( $GLOBALS['pll']['saved'] ) );
vgarco_link_translation( 10, 21, 'de' );
same( 'nối bản dịch: đổi sang bài khác thì cập nhật nhóm', array( 'vi' => 10, 'de' => 21 ), end( $GLOBALS['pll']['saved'] ) );
vgarco_set_post_language( 30, 'de' );
same( 'ngôn ngữ bài: đọc từ Polylang', 'de', vgarco_post_language( 30 ) );
same( 'ngôn ngữ bài chưa gán: null', null, vgarco_post_language( 31 ) );
same( 'bản dịch của bài 10 sang tiếng Đức', 21, vgarco_translation_id( 10, 'de' ) );
same( 'bài chưa có bản dịch: 0', 0, vgarco_translation_id( 30, 'de' ) );
same( 'đặt ngôn ngữ bài: gọi Polylang', 'de', $GLOBALS['pll']['lang'][30] );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

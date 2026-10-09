<?php

require_once __DIR__ . '/search-index.php';
require_once ROOT . '/wp-content/plugins/vgarco-core/inc/cli/import-lib.php';

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

$bom = "\xEF\xBB\xBF";
$p   = vgarco_csv_parse( $bom . "Ma;Ten_VI ;thu_tu\r\nbao-tang;Bảo tàng;1\r\ntruong;Trường;2\r\n" );
same( 'csv: bỏ BOM, hạ chữ và cắt khoảng trắng tiêu đề', array( 'ma', 'ten_vi', 'thu_tu' ), $p['headers'] );
same( 'csv: CRLF, hai dòng dữ liệu', array( array( 'ma' => 'bao-tang', 'ten_vi' => 'Bảo tàng', 'thu_tu' => '1' ), array( 'ma' => 'truong', 'ten_vi' => 'Trường', 'thu_tu' => '2' ) ), $p['rows'] );
same( 'csv: số dòng gốc', array( 2, 3 ), $p['lines'] );
same( 'csv: không lỗi', array(), $p['errors'] );

same( 'csv: tự nhận dấu phẩy', array( 'a', 'b' ), vgarco_csv_parse( "a,b\n1,2\n" )['headers'] );
same( 'csv: tự nhận tab', array( 'a', 'b' ), vgarco_csv_parse( "a\tb\n1\t2\n" )['headers'] );
same( 'csv: tự nhận ; khi hòa', ';', vgarco_csv_detect_delimiter( 'a;b,c' ) );
same( 'csv: dấu phẩy trong nháy kép không tính khi nhận dấu phân cách', ';', vgarco_csv_detect_delimiter( '"a,b,c,d";e;f' ) );
same( 'csv: không có dấu phân cách nào → ;', ';', vgarco_csv_detect_delimiter( 'mot_cot' ) );
same( 'csv: ép dấu phân cách', array( 'a;b' ), vgarco_csv_parse( "a;b\n1\n", ',' )['headers'] );

$q = vgarco_csv_parse( "ma;mo_ta;thu_tu\nx;\"Dòng 1; có dấu chấm phẩy\nDòng 2 với \"\"nháy\"\"\";5\ny;ngắn;6\n" );
same( 'csv: ô trong nháy kép chứa ; xuống dòng và "" (nháy thoát)', "Dòng 1; có dấu chấm phẩy\nDòng 2 với \"nháy\"", $q['rows'][0]['mo_ta'] ?? '' );
same( 'csv: sau ô nhiều dòng số dòng vẫn đúng (dòng y ở dòng 4)', array( 2, 4 ), $q['lines'] );
same( 'csv: cột sau ô nhiều dòng không lệch', array( '5', '6' ), array_column( $q['rows'], 'thu_tu' ) );

same( 'csv: dòng trống và dòng toàn ô trống (;;; do Excel xuất thừa) bị bỏ', 2, count( vgarco_csv_parse( "a;b;c\n1;2;3\n\n;;;\n  ; ;  \n4;5;6\n;;;\n" )['rows'] ) );
same( 'csv: dòng ngắn hơn tiêu đề được bù ô rỗng', array( 'a' => '1', 'b' => '', 'c' => '' ), vgarco_csv_parse( "a;b;c\n1\n" )['rows'][0] );
$long = vgarco_csv_parse( "a;b\n1;2;3;4\n5;6\n" );
same( 'csv: dòng dư ô (dấu ; trong nội dung) → lỗi nêu số dòng, dòng đó bị bỏ, dòng sau vẫn đọc', array( 1, true ), array( count( $long['rows'] ), str_contains( $long['errors'][0] ?? '', 'Dòng 2' ) ) );
same( 'csv: ô thừa nhưng trống thì không phải lỗi', array(), vgarco_csv_parse( "a;b\n1;2;;\n" )['errors'] );
check( 'csv: tiêu đề trùng → lỗi', str_contains( vgarco_csv_parse( "ma;ten;ma\n1;2;3\n" )['errors'][0] ?? '', '"ma"' ) );
same( 'csv: cột tiêu đề trống bị bỏ khỏi dữ liệu', array( 'a' => '1', 'b' => '2' ), vgarco_csv_parse( "a;;b\n1;x;2\n" )['rows'][0] );
check( 'csv: nháy kép mở mà không đóng → lỗi nêu dòng', str_contains( vgarco_csv_parse( "a;b\n1;\"chưa đóng\n" )['errors'][0] ?? '', 'dòng 2' ) );
check( 'csv: không phải UTF-8 → lỗi có hướng dẫn', str_contains( vgarco_csv_parse( "a;b\n1;\xE9\n" )['errors'][0] ?? '', 'UTF-8' ) );
same( 'csv: tệp trống → lỗi', array( 'Tệp trống.' ), vgarco_csv_parse( '' )['errors'] );
same( 'csv: ô giữ xuống dòng bên trong, cắt khoảng trắng hai đầu', "một\nhai", vgarco_csv_parse( "a\n\"  một\nhai  \"\n" )['rows'][0]['a'] );
same( 'csv: dòng cuối không có xuống dòng vẫn đọc', 1, count( vgarco_csv_parse( "a;b\n1;2" )['rows'] ) );
same( 'csv: CR đơn (Mac cũ)', 2, count( vgarco_csv_parse( "a;b\r1;2\r3;4" )['rows'] ) );

foreach ( array( '' => '0', '0' => '0', 'false' => '0', 'Không' => '0', 'no' => '0', '1' => '1', 'TRUE' => '1', 'Có' => '1', 'x' => '1', 'yes' => '1' ) as $in => $out ) {
	same( 'bool: ' . json_encode( $in ), $out, vgarco_import_bool( (string) $in ) );
}
foreach ( array( '2', 'maybe', 'đúng' ) as $bad ) {
	same( 'bool: giá trị lạ → null: ' . $bad, null, vgarco_import_bool( $bad ) );
}
foreach ( array( 'vn' => 'vn', 'Việt Nam' => 'vn', 'LÀO' => 'la', 'CHDCND Lào' => 'la', 'Đức' => 'de', 'Deutschland' => 'de', '' => '' ) as $in => $out ) {
	same( 'quốc gia: ' . json_encode( $in ), $out, vgarco_import_country( (string) $in ) );
}
same( 'quốc gia: lạ → null', null, vgarco_import_country( 'Mars' ) );
same( 'đoạn văn: dòng trống tách đoạn, xuống dòng đơn thành <br>', "<p>Đoạn 1<br>dòng 2</p>\n<p>Đoạn 2</p>", vgarco_import_paragraphs( "Đoạn 1\ndòng 2\n\n\nĐoạn 2" ) );
same( 'đoạn văn: thoát HTML (chống chèn thẻ từ bảng tính)', '<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;x&quot;</p>', vgarco_import_paragraphs( '<script>alert(1)</script> & "x"' ) );
same( 'đoạn văn: rỗng', '', vgarco_import_paragraphs( " \n " ) );
same( 'đoạn văn: CRLF', "<p>a<br>b</p>", vgarco_import_paragraphs( "a\r\nb" ) );
foreach ( array( 'bao-tang-qdnd-lao', 'a', 'a1-b2' ) as $ok ) {
	check( "slug hợp lệ: {$ok}", vgarco_import_slug_valid( $ok ) );
}
foreach ( array( '', 'Bao-Tang', 'bao_tang', 'bao tang', '-a', 'a-', 'a--b', 'bảo-tàng' ) as $bad ) {
	check( 'slug không hợp lệ: ' . json_encode( $bad ), ! vgarco_import_slug_valid( $bad ) );
}

$allowed = array( 'terms' => array( 'van-hoa-di-tich' => 'Văn hóa & di tích', 'giao-duc-dao-tao' => 'Giáo dục & đào tạo', 'y-te' => 'Y tế' ) );
$full    = array(
	'ma' => 'bao-tang-qdnd-lao', 'thu_tu' => '3', 'ten_vi' => 'Bảo tàng Lịch sử QĐND Lào', 'ten_de' => 'Armeemuseum Laos', 'slug_vi' => 'bao-tang', 'slug_de' => 'armeemuseum',
	'loai' => 'van-hoa-di-tich|Giáo dục & đào tạo', 'quoc_gia' => 'Lào', 'dia_diem_vi' => 'Viêng Chăn', 'dia_diem_de' => 'Vientiane', 'thoi_gian' => '2019–2020',
	'vai_tro_vi' => 'Thiết kế', 'vai_tro_de' => 'Entwurf', 'chu_dau_tu_vi' => 'Bộ QP', 'chu_dau_tu_de' => 'Verteidigungsministerium', 'doi_tac' => 'AVITYCO',
	'cong_bo_chu_dau_tu' => 'có', 'mo_ta_vi' => "Đoạn 1\n\nĐoạn 2", 'mo_ta_de' => 'Absatz', 'loai_anh_dai_dien' => 'cong_trinh', 'noi_bat' => '1', 'tieu_bieu' => '0',
	'mo_ta_seo_vi' => 'SEO vi', 'mo_ta_seo_de' => 'SEO de',
);
$r = vgarco_import_project_plan( $full, 7, $allowed );
same( 'kế hoạch: dòng đầy đủ không lỗi', array(), $r['errors'] );
$plan = $r['plan'];
same( 'kế hoạch: mã, thứ tự', array( 'bao-tang-qdnd-lao', 3 ), array( $plan['ma'], $plan['order'] ) );
same( 'kế hoạch: bản VI', array( 'Bảo tàng Lịch sử QĐND Lào', 'bao-tang', "<p>Đoạn 1</p>\n<p>Đoạn 2</p>", 'Viêng Chăn', 'Bộ QP', 'AVITYCO', 'SEO vi' ), array( $plan['vi']['title'], $plan['vi']['slug'], $plan['vi']['content'], $plan['vi']['meta']['dia_diem'], $plan['vi']['meta']['chu_dau_tu'], $plan['vi']['meta']['doi_tac'], $plan['vi']['meta']['mo_ta_seo'] ) );
same( 'kế hoạch: bản DE (đối tác dùng chung một cột)', array( 'Armeemuseum Laos', 'armeemuseum', 'Vientiane', 'Verteidigungsministerium', 'AVITYCO' ), array( $plan['de']['title'], $plan['de']['slug'], $plan['de']['meta']['dia_diem'], $plan['de']['meta']['chu_dau_tu'], $plan['de']['meta']['doi_tac'] ) );
same( 'kế hoạch: trường dùng chung', array( '2019–2020', 'la', '1', 'cong_trinh', '1', '0' ), array_values( $plan['shared'] ) );
same( 'kế hoạch: loại công trình nhận cả slug lẫn tên (không dấu, hoa thường)', array( 'van-hoa-di-tich', 'giao-duc-dao-tao' ), $plan['terms'] );

$min = vgarco_import_project_plan( array( 'ma' => 'a', 'ten_vi' => 'Tên' ), 2, $allowed );
same( 'kế hoạch tối thiểu: không lỗi, không bản DE, mặc định đúng', array( array(), null, 0, 'thiet_ke', '0', array() ), array( $min['errors'], $min['plan']['de'], $min['plan']['order'], $min['plan']['shared']['loai_anh_dai_dien'], $min['plan']['shared']['noi_bat'], $min['plan']['terms'] ) );
same( 'kế hoạch: nội dung HTML bị thoát', '<p>&lt;b&gt;x&lt;/b&gt;</p>', vgarco_import_project_plan( array( 'ma' => 'a', 'ten_vi' => 'T', 'mo_ta_vi' => '<b>x</b>' ), 1, $allowed )['plan']['vi']['content'] );
same( 'kế hoạch: loại trùng chỉ tính một lần', array( 'y-te' ), vgarco_import_project_plan( array( 'ma' => 'a', 'ten_vi' => 'T', 'loai' => 'y-te, Y tế|y-te' ), 1, $allowed )['plan']['terms'] );

$bad = vgarco_import_project_plan(
	array( 'ma' => 'Bảo Tàng', 'ten_vi' => '', 'thu_tu' => '-1', 'slug_vi' => 'Sai Slug', 'loai' => 'khong-co', 'quoc_gia' => 'Sao Hỏa', 'cong_bo_chu_dau_tu' => 'maybe', 'loai_anh_dai_dien' => 'ảnh', 'thoi_gian' => str_repeat( '9', 21 ), 'mo_ta_seo_vi' => str_repeat( 'x', 161 ) ),
	9,
	$allowed
);
same( 'kế hoạch lỗi: không có kế hoạch', null, $bad['plan'] );
same( 'kế hoạch lỗi: mọi lỗi được gom và đều nêu "Dòng 9"', array( 10, true ), array( count( $bad['errors'] ), 10 === count( array_filter( $bad['errors'], static fn( $e ) => str_starts_with( $e, 'Dòng 9:' ) ) ) ) );
foreach ( array( '"ma"', '"ten_vi"', '"thu_tu"', '"slug_vi"', '"loai"', '"quoc_gia"', '"cong_bo_chu_dau_tu"', '"loai_anh_dai_dien"', '"thoi_gian"', '"mo_ta_seo_vi"' ) as $field ) {
	check( "kế hoạch lỗi: có báo lỗi trường {$field}", (bool) array_filter( $bad['errors'], static fn( $e ) => str_contains( $e, $field ) ) );
}
same( 'kế hoạch lỗi: thiếu ma', true, str_contains( vgarco_import_project_plan( array( 'ten_vi' => 'T' ), 4, $allowed )['errors'][0] ?? '', 'thiếu "ma"' ) );
check( 'kế hoạch lỗi: có trường tiếng Đức mà thiếu ten_de', (bool) array_filter( vgarco_import_project_plan( array( 'ma' => 'a', 'ten_vi' => 'T', 'dia_diem_de' => 'Hanoi' ), 3, $allowed )['errors'], static fn( $e ) => str_contains( $e, 'ten_de' ) ) );
same( 'kế hoạch: đúng 160 ký tự SEO và 20 ký tự thời gian vẫn hợp lệ', array(), vgarco_import_project_plan( array( 'ma' => 'a', 'ten_vi' => 'T', 'mo_ta_seo_vi' => str_repeat( 'ạ', 160 ), 'thoi_gian' => str_repeat( '9', 20 ) ), 1, $allowed )['errors'] );

$csv = $bom . "ma;thu_tu;ten_vi;loai;noi_bat;tieu_bieu\r\n"
	. "a;1;Dự án A;y-te;1;1\r\n"
	. "b;2;Dự án B;Giáo dục & đào tạo;1;1\r\n"
	. ";;;;;\r\n"
	. "c;3;Dự án C;van-hoa-di-tich;0;1\r\n";
$ok = vgarco_import_projects( $csv, $allowed );
same( 'tệp: 3 dự án hợp lệ, bỏ dòng trống', array( array(), array( 'a', 'b', 'c' ) ), array( $ok['errors'], array_column( $ok['plans'], 'ma' ) ) );

$dup = vgarco_import_projects( "ma;ten_vi\na;A\nb;B\na;A2\n", $allowed );
same( 'tệp: trùng ma → lỗi nêu cả hai dòng, không có kế hoạch nào (không ghi một phần)', array( array(), true ), array( $dup['plans'], str_contains( $dup['errors'][0] ?? '', 'Dòng 4' ) && str_contains( $dup['errors'][0] ?? '', 'dòng 2' ) ) );
$hdr = vgarco_import_projects( "ma;ten_sai;ten_vi_\na;A;B\n", $allowed );
same( 'tệp: sai tiêu đề → nêu cột thiếu và cột lạ, dừng ở đây', true, (bool) array_filter( $hdr['errors'], static fn( $e ) => str_contains( $e, 'Thiếu cột bắt buộc "ten_vi"' ) ) && (bool) array_filter( $hdr['errors'], static fn( $e ) => str_contains( $e, 'Cột lạ "ten_sai"' ) ) );
$many = "ma;ten_vi;noi_bat;tieu_bieu\n";
for ( $i = 1; $i <= 6; $i++ ) {
	$many .= "p{$i};Dự án {$i};1;" . ( $i <= 4 ? '1' : '0' ) . "\n";
}
$limits = vgarco_import_projects( $many, $allowed );
same( 'tệp: 6 dự án nổi bật (tối đa 5) và 4 tiêu biểu (tối đa 3) → 2 lỗi', 2, count( $limits['errors'] ) );
same( 'tệp: đúng 5 nổi bật và 3 tiêu biểu thì được', array(), vgarco_import_projects( "ma;ten_vi;noi_bat;tieu_bieu\na;A;1;1\nb;B;1;1\nc;C;1;1\nd;D;1;0\ne;E;1;0\n", $allowed )['errors'] );
same( 'tệp: chỉ có tiêu đề → lỗi không có dữ liệu', array( 'Tệp không có dòng dữ liệu nào.' ), vgarco_import_projects( "ma;ten_vi\n", $allowed )['errors'] );
$mixed = vgarco_import_projects( "ma;ten_vi;quoc_gia\na;A;xx\nB-sai;;vn\nc;C;la\n", $allowed );
same( 'tệp: lỗi nhiều dòng được báo MỘT lượt (dòng 2 và dòng 3), kế hoạch rỗng', array( array(), true ), array( $mixed['plans'], ! empty( array_filter( $mixed['errors'], static fn( $e ) => str_starts_with( $e, 'Dòng 2' ) ) ) && ! empty( array_filter( $mixed['errors'], static fn( $e ) => str_starts_with( $e, 'Dòng 3' ) ) ) ) );
check( 'tệp: tên cột có thể viết hoa (Ma, TEN_VI)', array() === vgarco_import_projects( "Ma;TEN_VI\na;A\n", $allowed )['errors'] );

$flat = vgarco_import_flatten( $plan, 'vi' );
same( 'flatten: khóa cơ bản và siêu dữ liệu', true, isset( $flat['post_title'], $flat['post_name'], $flat['post_content'], $flat['menu_order'], $flat['meta.dia_diem'], $flat['meta.thoi_gian'], $flat['meta.quoc_gia'], $flat['terms.loai_cong_trinh'] ) );
same( 'flatten: loại công trình nối bằng dấu phẩy', 'van-hoa-di-tich,giao-duc-dao-tao', $flat['terms.loai_cong_trinh'] );
same( 'flatten: ngôn ngữ không có → rỗng', array(), vgarco_import_flatten( $min['plan'], 'de' ) );

same( 'diff: bài chưa có → mọi trường có giá trị đều là thay đổi', true, isset( vgarco_import_diff( array(), $flat )['post_title'] ) && ! isset( vgarco_import_diff( array(), $flat )['meta.doi_tac_khong_co'] ) );
same( 'diff: bản ghi giống hệt → không có thay đổi', array(), vgarco_import_diff( $flat, $flat ) );
$cur            = $flat;
$cur['post_title'] = 'Tên cũ';
same( 'diff: chỉ trường khác được liệt kê, kèm [cũ, mới]', array( 'post_title' => array( 'Tên cũ', 'Bảo tàng Lịch sử QĐND Lào' ) ), vgarco_import_diff( $cur, $flat ) );
$cur2                   = $flat;
$cur2['meta.vai_tro']   = 'Biên tập viên đã nhập tay';
$new2                   = $flat;
$new2['meta.vai_tro']   = '';
same( 'diff: ô trống trong bảng KHÔNG xóa dữ liệu đã có', array(), vgarco_import_diff( $cur2, $new2 ) );
$cur3                 = $flat;
$cur3['menu_order']   = '5';
$new3                 = $flat;
$new3['menu_order']   = '0';
same( 'diff: thứ tự 0 là giá trị thật, được cập nhật', array( 'menu_order' => array( '5', '0' ) ), vgarco_import_diff( $cur3, $new3 ) );
same( 'diff: khoảng trắng thừa không tạo khác biệt', array(), vgarco_import_diff( array( 'post_title' => ' A ' ), array( 'post_title' => 'A' ) ) );

$known = array( 'a', 'b' );
$img   = "ma;tep;vai_tro;thu_tu;loai_anh;chu_thich_vi;chu_thich_de\n"
	. "a;a/hero.jpg;hero;;;;\n"
	. "a;a/the.webp;the;;;;\n"
	. "a;a/slide.png;slide;;;;\n"
	. "a;a/3.jpg;tv;3;cong_trinh;Mặt đứng;Fassade\n"
	. "a;a/1.jpg;tv;1;;Phối cảnh;Ansicht\n"
	. "a;a/x.jpg;tv;;;Không thứ tự;\n"
	. "a;a/y.jpg;tv;;;Không thứ tự 2;\n"
	. "b;b/hero.JPEG;hero;;;;\n";
$ri = vgarco_import_images( $img, $known );
same( 'ảnh: không lỗi', array(), $ri['errors'] );
same( 'ảnh: vai trò hero/the/slide', array( 'a/hero.jpg', 'a/the.webp', 'a/slide.png' ), array( $ri['images']['a']['hero'], $ri['images']['a']['the'], $ri['images']['a']['slide'] ) );
same( 'ảnh: thư viện sắp theo thu_tu, ô trống đứng cuối và giữ thứ tự dòng', array( 'a/1.jpg', 'a/3.jpg', 'a/x.jpg', 'a/y.jpg' ), array_column( $ri['images']['a']['tv'], 'file' ) );
same( 'ảnh: loại ảnh mặc định thiet_ke, chú thích hai ngôn ngữ', array( 'thiet_ke', 'Phối cảnh', 'Ansicht', 'cong_trinh' ), array( $ri['images']['a']['tv'][0]['kind'], $ri['images']['a']['tv'][0]['caption_vi'], $ri['images']['a']['tv'][0]['caption_de'], $ri['images']['a']['tv'][1]['kind'] ) );
same( 'ảnh: khóa phụ (order, line) không lộ ra ngoài', array( 'file', 'kind', 'caption_vi', 'caption_de' ), array_keys( $ri['images']['a']['tv'][0] ) );
same( 'ảnh: dự án khác có phần riêng, đuôi viết hoa được chấp nhận', 'b/hero.JPEG', $ri['images']['b']['hero'] );

foreach ( array( '../etc/passwd.jpg', '/abs/a.jpg', 'C:\\a.jpg', 'C:/a.jpg', 'a\\b.jpg', 'a/../../b.jpg', 'a.gif', 'a.php', 'a.jpg.php', 'a', '', "a\0.jpg" ) as $path ) {
	check( 'ảnh: đường dẫn không hợp lệ bị từ chối: ' . json_encode( $path ), ! vgarco_import_image_path_valid( $path ) );
}
foreach ( array( 'a.jpg', 'sub/dir/a.PNG', 'a.b.webp', 'a.jpeg' ) as $path ) {
	check( 'ảnh: đường dẫn hợp lệ: ' . $path, vgarco_import_image_path_valid( $path ) );
}

$cases = array(
	'ma lạ'                   => array( "ma;tep;vai_tro\nz;a.jpg;hero\n", '"z"' ),
	'vai trò sai'             => array( "ma;tep;vai_tro\na;a.jpg;banner\n", 'vai_tro' ),
	'tệp sai đuôi'            => array( "ma;tep;vai_tro\na;a.gif;hero\n", 'tep' ),
	'hero trùng'              => array( "ma;tep;vai_tro\na;1.jpg;hero\na;2.jpg;hero\n", 'đã có ảnh vai trò "hero"' ),
	'thư viện thiếu chú thích' => array( "ma;tep;vai_tro;chu_thich_vi\na;1.jpg;tv;\n", 'chu_thich_vi' ),
	'loại ảnh sai'            => array( "ma;tep;vai_tro;loai_anh\na;1.jpg;hero;xxx\n", 'loai_anh' ),
	'thứ tự sai'              => array( "ma;tep;vai_tro;thu_tu;chu_thich_vi\na;1.jpg;tv;abc;Cap\n", 'thu_tu' ),
	'thiếu cột'               => array( "ma;tep\na;1.jpg\n", 'vai_tro' ),
	'cột lạ'                  => array( "ma;tep;vai_tro;mau\na;1.jpg;hero;do\n", 'cột lạ' ),
);
foreach ( $cases as $label => [ $text, $needle ] ) {
	$r = vgarco_import_images( $text, $known );
	check( "ảnh lỗi ({$label}): có lỗi chứa {$needle} và không có kết quả nào", (bool) array_filter( $r['errors'], static fn( $e ) => str_contains( $e, $needle ) ) && array() === $r['images'] );
}
$sixteen = "ma;tep;vai_tro;chu_thich_vi\n";
for ( $i = 1; $i <= 16; $i++ ) {
	$sixteen .= "a;{$i}.jpg;tv;Ảnh {$i}\n";
}
check( 'ảnh: 16 ảnh thư viện → lỗi (tối đa 15)', (bool) array_filter( vgarco_import_images( $sixteen, $known )['errors'], static fn( $e ) => str_contains( $e, 'tối đa 15' ) ) );
same( 'ảnh: đúng 15 ảnh thư viện thì được', array(), vgarco_import_images( str_replace( "a;16.jpg;tv;Ảnh 16\n", '', $sixteen ), $known )['errors'] );

$seed_terms = array(
	'ngoai-giao-cong-quyen' => 'Ngoại giao & công quyền', 'quan-su-an-ninh' => 'Quân sự & an ninh', 'giao-duc-dao-tao' => 'Giáo dục & đào tạo',
	'van-hoa-di-tich' => 'Văn hóa & di tích', 'y-te' => 'Y tế', 'cong-nghiep-ha-tang' => 'Công nghiệp & hạ tầng', 'nha-o-dan-dung' => 'Nhà ở & công trình dân dụng',
);
$repo = vgarco_import_projects( (string) file_get_contents( ROOT . '/content/projects.csv' ), array( 'terms' => $seed_terms ) );
same( 'content/projects.csv: nhập được, không lỗi', array(), $repo['errors'] );
same( 'content/projects.csv: 19 dự án, mã không trùng', array( 19, 19 ), array( count( $repo['plans'] ), count( array_unique( array_column( $repo['plans'], 'ma' ) ) ) ) );
same( 'content/projects.csv: KHÔNG dự án nào bật công bố chủ đầu tư (cần chủ đầu tư đồng ý, mục 3.2 #8)', array(), array_keys( array_filter( array_column( $repo['plans'], 'shared' ), static fn( $s ) => '1' === $s['cong_bo_chu_dau_tu'] ) ) );
same( 'content/projects.csv: 3 nổi bật, 3 tiêu biểu', array( 3, 3 ), array( count( array_filter( array_column( $repo['plans'], 'shared' ), static fn( $s ) => '1' === $s['noi_bat'] ) ), count( array_filter( array_column( $repo['plans'], 'shared' ), static fn( $s ) => '1' === $s['tieu_bieu'] ) ) ) );
same( 'content/projects.csv: mọi dự án có ít nhất một loại công trình', 0, count( array_filter( array_column( $repo['plans'], 'terms' ), static fn( $t ) => ! $t ) ) );
$repo_images = vgarco_import_images( (string) file_get_contents( ROOT . '/content/images.example.csv' ), array_column( $repo['plans'], 'ma' ) );
same( 'content/images.example.csv: hợp lệ với projects.csv', array( array(), true ), array( $repo_images['errors'], isset( $repo_images['images']['bao-tang-lich-su-quan-doi-nhan-dan-lao']['tv'][1] ) ) );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

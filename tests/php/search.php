<?php
/**
 * Kiểm thử tìm kiếm dự án (UC-09): chuẩn hóa, so khớp bỏ dấu, xếp hạng, giới hạn. Dùng 19 dự án thật của hồ sơ (tests/php/data/projects.json).
 *
 *   php -d extension_dir=<ext> -d extension=mbstring tests/php/search.php
 *
 * Không thay cho chạy thử đường REST và chỉ mục trên WordPress thật.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );

require_once __DIR__ . '/search-index.php';

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

function titles( array $results ): array {
	return array_column( $results, 'title' );
}

/* ---------- Chỉ mục từ dữ liệu thật ---------- */

$index = fx_search_index();
same( 'chỉ mục: 19 dự án', 19, count( $index ) );

/* ---------- Chuẩn hóa ---------- */

same( 'chuẩn hóa: bỏ dấu, hạ chữ, đ → d', 'bao tang lich su dai su', vgarco_search_normalize( 'Bảo Tàng Lịch Sử ĐẠI SỨ' ) );
same( 'chuẩn hóa: gộp khoảng trắng và cắt hai đầu', 'bao tang', vgarco_search_normalize( "  bao \t\n  tang  " ) );
same( 'chuẩn hóa: cắt ở 100 ký tự', 100, mb_strlen( vgarco_search_normalize( str_repeat( 'a', 300 ) ) ) );
same( 'chuẩn hóa: tiếng Đức ä → ae, ß → ss', 'grosse', vgarco_search_normalize( 'Große' ) );

/* ---------- Độ dài tối thiểu ---------- */

foreach ( array( '', 'b', 'ba', '  ba  ', "b\t", 'đ ', ' ạ' ) as $short ) {
	same( 'dưới 3 ký tự → rỗng: ' . json_encode( $short ), array(), vgarco_search_rank( $index, $short ) );
}
check( 'đúng 3 ký tự thì tìm', count( vgarco_search_rank( $index, 'lao' ) ) > 0 );
check( '3 ký tự có dấu tính theo ký tự (không theo byte)', count( vgarco_search_rank( $index, 'lào' ) ) > 0 );

/* ---------- So khớp ---------- */

$museum = 'Bảo tàng Lịch sử Quân đội nhân dân Lào';
$r      = vgarco_search_rank( $index, 'bao tang' );
same( 'không dấu: "bao tang" ra bảo tàng, xếp đầu vì tên bắt đầu bằng cụm từ', $museum, $r[0]['title'] ?? '' );
same( 'có dấu và không dấu cho cùng kết quả', titles( vgarco_search_rank( $index, 'bao tang' ) ), titles( vgarco_search_rank( $index, 'Bảo Tàng' ) ) );
same( 'hoa thường không ảnh hưởng', titles( vgarco_search_rank( $index, 'bao tang' ) ), titles( vgarco_search_rank( $index, 'BẢO TÀNG' ) ) );
same( 'khoảng trắng thừa không ảnh hưởng', titles( vgarco_search_rank( $index, 'bao tang' ) ), titles( vgarco_search_rank( $index, "  bao    tang " ) ) );

$lao = titles( vgarco_search_rank( $index, 'lào' ) );
check( 'địa điểm: "Lào" ra bảo tàng ở Viêng Chăn', in_array( $museum, $lao, true ) );
check( 'địa điểm: mọi dự án trả về đều có "Lào" trong tên, địa điểm, năm hoặc loại', count( $lao ) >= 3 );
same( 'địa điểm: tìm "vieng chan" (không dấu) ra bảo tàng', array( $museum ), array_slice( titles( vgarco_search_rank( $index, 'vieng chan' ) ), 0, 1 ) );
check( 'năm: "2019" ra bảo tàng (2019–2020)', in_array( $museum, titles( vgarco_search_rank( $index, '2019' ) ), true ) );
check( 'loại công trình: "van hoa" ra công trình thuộc Văn hóa & di tích', in_array( $museum, titles( vgarco_search_rank( $index, 'van hoa' ) ), true ) );
check( 'loại công trình: "giao duc" chỉ ra dự án thuộc Giáo dục', count( vgarco_search_rank( $index, 'giao duc' ) ) >= 3 );

same( 'mọi từ đều phải khớp: "truong bac" chỉ ra THCS Bắc Từ Liêm', array( 'Trường THCS chất lượng cao Bắc Từ Liêm' ), titles( vgarco_search_rank( $index, 'truong bac' ) ) );
same( 'từ không có trong dự án nào → rỗng', array(), vgarco_search_rank( $index, 'khong-co-du-an-nay' ) );
same( 'một từ đúng, một từ sai → rỗng (không phải "một trong")', array(), vgarco_search_rank( $index, 'bao tang zzzz' ) );

// Ký tự đặc biệt chỉ là chữ, không phải mẫu tìm hay SQL.
foreach ( array( '<script>alert(1)</script>', '%%%', '___', '.*', '\\', "'; DROP TABLE x; --", '(((', '[a-z]+' ) as $weird ) {
	same( 'ký tự đặc biệt không ra kết quả và không lỗi: ' . json_encode( $weird ), array(), vgarco_search_rank( $index, $weird ) );
}
same( 'truy vấn dài 300 ký tự: cắt còn 100, không lỗi, không ra kết quả', array(), vgarco_search_rank( $index, str_repeat( 'a', 300 ) ) );

/* ---------- Xếp hạng, giới hạn, hình dạng kết quả ---------- */

$mini = array_map(
	'vgarco_search_prepare',
	array(
		array( 'title' => 'Sân vận động', 'location' => 'Phố Trường Chinh', 'years' => '2021', 'types' => array(), 'url' => '/c/', 'thumb' => '' ),
		array( 'title' => 'Nhà văn hóa Trường Sơn', 'location' => 'Hà Nội', 'years' => '2020', 'types' => array(), 'url' => '/b/', 'thumb' => '' ),
		array( 'title' => 'Trường mầm non Sao Mai', 'location' => 'Hà Nội', 'years' => '2022', 'types' => array(), 'url' => '/a/', 'thumb' => '' ),
		array( 'title' => 'Trường Tiểu học A', 'location' => 'Hà Nội', 'years' => '2023', 'types' => array(), 'url' => '/d/', 'thumb' => '' ),
		array( 'title' => 'Trường Sơn Đông', 'location' => 'Huế', 'years' => '2024', 'types' => array(), 'url' => '/f/', 'thumb' => '' ),
	)
);
same(
	'xếp hạng: tên bắt đầu bằng từ khóa → có từ trong tên bắt đầu bằng từ khóa → chỉ khớp ở nơi khác; cùng hạng giữ thứ tự chỉ mục',
	array( '/a/', '/d/', '/f/', '/b/', '/c/' ),
	array_column( vgarco_search_rank( $mini, 'truong' ), 'url' )
);
same(
	'xếp hạng: tên bắt đầu bằng CẢ cụm từ xếp trước tên chỉ bắt đầu bằng từ đầu',
	array( '/f/', '/b/' ),
	array_column( vgarco_search_rank( $mini, 'truong son' ), 'url' )
);
same( 'xếp hạng: cụm từ "truong tieu" chỉ có Tiểu học A', array( '/d/' ), array_column( vgarco_search_rank( $mini, 'truong tieu' ), 'url' ) );

$many = array();
for ( $i = 1; $i <= 25; $i++ ) {
	$many[] = vgarco_search_prepare( array( 'title' => "Trường học số {$i}", 'location' => '', 'years' => '', 'types' => array(), 'url' => "/t{$i}/", 'thumb' => '' ) );
}
same( 'giới hạn: tối đa 10 kết quả', 10, count( vgarco_search_rank( $many, 'truong hoc' ) ) );
same( 'giới hạn: 10 kết quả đầu theo thứ tự chỉ mục', array_map( fn( $i ) => "/t{$i}/", range( 1, 10 ) ), array_column( vgarco_search_rank( $many, 'truong hoc' ), 'url' ) );
same( 'giới hạn: tham số giới hạn tùy chỉnh', 3, count( vgarco_search_rank( $many, 'truong hoc', 3 ) ) );
same( 'giới hạn: 0 → rỗng', array(), vgarco_search_rank( $many, 'truong hoc', 0 ) );

$one = vgarco_search_rank( $index, 'bao tang' )[0] ?? array();
same( 'kết quả chỉ có 5 trường công khai', array( 'location', 'thumb', 'title', 'url', 'years' ), ( function () use ( $one ) { $k = array_keys( $one ); sort( $k ); return $k; } )() );
same( 'kết quả: đủ dữ liệu để hiển thị', array( $museum, 'Viêng Chăn, Lào', '2019–2020', 'https://vgarco.vn/du-an/museum/', 'https://vgarco.vn/x/museum-150.jpg' ), array( $one['title'] ?? '', $one['location'] ?? '', $one['years'] ?? '', $one['url'] ?? '', $one['thumb'] ?? '' ) );

// Tiếng Đức: chuỗi truy vấn và chỉ mục đi qua cùng một bảng đổi nên gõ đúng chữ Đức là khớp.
$german = array( vgarco_search_prepare( array( 'title' => 'Fußgängerzone Größe', 'location' => 'Köln', 'years' => '', 'types' => array(), 'url' => '/de/', 'thumb' => '' ) ) );
same( 'tiếng Đức: gõ đúng "Größe" khớp', array( '/de/' ), array_column( vgarco_search_rank( $german, 'Größe' ), 'url' ) );
same( 'tiếng Đức: gõ "koeln" khớp "Köln"', array( '/de/' ), array_column( vgarco_search_rank( $german, 'koeln' ), 'url' ) );

/* ---------- Kết quả ---------- */

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

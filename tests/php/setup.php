<?php

define( 'ROOT', dirname( __DIR__, 2 ) );

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

final class WP_CLI {
	public static array $log = array();

	public static function error( string $message ): void {
		self::$log[] = array( 'error', $message );
		throw new RuntimeException( $message );
	}

	public static function success( string $message ): void {
		self::$log[] = array( 'success', $message );
	}
}

$GLOBALS['db'] = array();

function db_reset(): void {
	$GLOBALS['db'] = array( 'posts' => array(), 'meta' => array(), 'options' => array(), 'next' => 10, 'inserts' => 0, 'flushed' => 0, 'refuse' => '' );
	WP_CLI::$log   = array();
}

function get_page_by_path( $path ) {
	foreach ( $GLOBALS['db']['posts'] as $post ) {
		if ( $post->path === $path ) {
			return $post;
		}
	}
	return null;
}

function wp_insert_post( array $args ) {
	$db = &$GLOBALS['db'];
	if ( '' !== $db['refuse'] && $db['refuse'] === $args['post_name'] ) {
		return 0;
	}
	$id     = $db['next']++;
	$parent = $args['post_parent'] ? $db['posts'][ $args['post_parent'] ]->path . '/' : '';

	$db['posts'][ $id ] = (object) array( 'ID' => $id, 'path' => $parent . $args['post_name'], 'args' => $args );
	++$db['inserts'];
	return $id;
}

function update_post_meta( $id, $key, $value ) {
	$GLOBALS['db']['meta'][ $id ][ $key ] = $value;
}

function update_option( $key, $value ) {
	$GLOBALS['db']['options'][ $key ] = $value;
}

function flush_rewrite_rules() {
	++$GLOBALS['db']['flushed'];
}

function is_wp_error( $value ) {
	return false;
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

$setup = static function (): void {
	require ROOT . '/scripts/ops/setup-pages.php';
};
$by_path = static function () {
	$out = array();
	foreach ( $GLOBALS['db']['posts'] as $post ) {
		$out[ $post->path ] = $post;
	}
	return $out;
};

$doc   = (string) file_get_contents( ROOT . '/docs/TRIEN-KHAI.md' );
$want  = array();
$start = strpos( $doc, '| Trang | Đường dẫn | Mẫu |' );
check( 'bảng trang trong TRIEN-KHAI.md có mặt', false !== $start );
foreach ( explode( "\n", substr( $doc, (int) $start, 2500 ) ) as $row ) {
	if ( 1 === preg_match( '/^\| [^|]+ \| `([^`]+)`[^|]*\| (?:`([^`]+)`|—|mặc định) \|$/u', $row, $m ) ) {
		$path          = '/' === $m[1] ? 'trang-chu' : trim( $m[1], '/' );
		$want[ $path ] = $m[2] ?? '';
	}
}
ksort( $want );
same( 'bảng trang trong tài liệu có 9 dòng', 9, count( $want ) );

db_reset();
$setup();
$pages = $by_path();
$got   = array();
foreach ( $pages as $path => $post ) {
	$got[ $path ] = $GLOBALS['db']['meta'][ $post->ID ]['_wp_page_template'] ?? '';
}
ksort( $got );
same( 'kịch bản tạo đúng các trang và mẫu như bảng trong tài liệu triển khai', $want, $got );
same( 'tạo đúng 9 trang', 9, $GLOBALS['db']['inserts'] );
check( 'mọi trang đều là page đã xuất bản', 9 === count( array_filter( $pages, static fn( $p ) => 'page' === $p->args['post_type'] && 'publish' === $p->args['post_status'] ) ) );
same( 'trang con "Ban lãnh đạo" thuộc "Về công ty"', $pages['gioi-thieu']->ID, $pages['gioi-thieu/ban-lanh-dao']->args['post_parent'] );
same( 'trang con "Phòng ban" thuộc "Về công ty"', $pages['gioi-thieu']->ID, $pages['gioi-thieu/phong-ban']->args['post_parent'] );
same( 'trang cấp một không có trang cha', array( 0 ), array_values( array_unique( array_map( static fn( $p ) => $p->args['post_parent'], array_intersect_key( $pages, array_flip( array( 'trang-chu', 'gioi-thieu', 'linh-vuc-hoat-dong', 'tuyen-dung', 'ung-tuyen', 'lien-he', 'chinh-sach-bao-ve-du-lieu' ) ) ) ) ) ) );
same( 'trang chủ tĩnh là trang "Trang chủ"', array( 'page', $pages['trang-chu']->ID ), array( $GLOBALS['db']['options']['show_on_front'] ?? '', $GLOBALS['db']['options']['page_on_front'] ?? 0 ) );
check( 'mô tả ngắn của site được đặt', '' !== ( $GLOBALS['db']['options']['blogdescription'] ?? '' ) );
same( 'xóa bộ nhớ đường dẫn đúng một lần', 1, $GLOBALS['db']['flushed'] );
$privacy = $pages['chinh-sach-bao-ve-du-lieu']->args['post_content'];
check( 'trang chính sách có dòng trung tính, không còn chữ "bản thử"', str_contains( $privacy, 'đang được cập nhật' ) && ! str_contains( $privacy, 'bản thử' ) );
same( 'báo thành công một lần, không lỗi', array( 'success' ), array_values( array_unique( array_column( WP_CLI::$log, 0 ) ) ) );

$ids_before = array_map( static fn( $p ) => $p->ID, $pages );
$setup();
$again = $by_path();
same( 'chạy lại không tạo thêm trang', 9, $GLOBALS['db']['inserts'] );
same( 'chạy lại giữ nguyên mã trang', $ids_before, array_map( static fn( $p ) => $p->ID, $again ) );

db_reset();
$GLOBALS['db']['posts'][5] = (object) array( 'ID' => 5, 'path' => 'lien-he', 'args' => array( 'post_content' => 'Nội dung khách đã nhập' ) );
$setup();
same( 'trang đã có sẵn thì không tạo lại', 8, $GLOBALS['db']['inserts'] );
same( 'trang đã có sẵn giữ nguyên nội dung', 'Nội dung khách đã nhập', $GLOBALS['db']['posts'][5]->args['post_content'] );
same( 'trang đã có sẵn vẫn được gán mẫu', 'templates/contact.php', $GLOBALS['db']['meta'][5]['_wp_page_template'] ?? '' );

db_reset();
$GLOBALS['db']['refuse'] = 'phong-ban';
$message                 = '';
try {
	$setup();
} catch ( RuntimeException $e ) {
	$message = $e->getMessage();
}
same( 'không tạo được một trang thì dừng và nêu đường dẫn', 'không tạo được trang gioi-thieu/phong-ban', $message );
check( 'dừng thì không đặt trang chủ tĩnh', ! isset( $GLOBALS['db']['options']['page_on_front'] ) );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

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

$prov = (string) file_get_contents( ROOT . '/scripts/ops/provision.sh' );

preg_match( '/security-headers\.inc [^\n]*<<\'EOF\'[^\n]*\n(.*?)\nEOF/s', $prov, $block );
check( 'có tệp tiêu đề bảo mật do provision.sh sinh', ! empty( $block[1] ) );
preg_match_all( '/^add_header (\S+) (?:"(.*)"|(\$vg_csp)) always;$/m', $block[1] ?? '', $found, PREG_SET_ORDER );
$headers = array();
foreach ( $found as $h ) {
	$headers[ $h[1] ] = '' !== ( $h[3] ?? '' ) ? '$vg_csp' : $h[2];
}
foreach ( array( 'Strict-Transport-Security', 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy', 'Content-Security-Policy' ) as $name ) {
	check( "tiêu đề {$name} có mặt và luôn gửi (always)", isset( $headers[ $name ] ) );
}
check( 'HSTS tối thiểu 1 năm', 1 === preg_match( '/max-age=(\d+)/', $headers['Strict-Transport-Security'] ?? '', $m ) && (int) $m[1] >= 31536000 );
same( 'nosniff', 'nosniff', $headers['X-Content-Type-Options'] ?? '' );
check( 'CSP lấy từ biến $vg_csp', '$vg_csp' === ( $headers['Content-Security-Policy'] ?? '' ) );

preg_match( '/map "([^"]*)" \$vg_csp \{\n\s*default "(.*?)";\n(.*?)\n\}/s', $prov, $map );
check( 'có bảng map $vg_csp', ! empty( $map[2] ) );
$csp = $map[2] ?? '';
$dirs = array();
foreach ( explode( ';', $csp ) as $part ) {
	$bits = preg_split( '/\s+/', trim( $part ) );
	if ( $bits && '' !== $bits[0] ) {
		$dirs[ array_shift( $bits ) ] = $bits;
	}
}
same( 'CSP: mặc định chỉ cùng nguồn', array( "'self'" ), $dirs['default-src'] ?? array() );
check( 'CSP: script chỉ cùng nguồn (không unsafe-inline, không unsafe-eval, không miền ngoài)', isset( $dirs['script-src'] ) && ! array_intersect( array( "'unsafe-inline'", "'unsafe-eval'", '*', 'https:' ), $dirs['script-src'] ) );
check( 'CSP: cho phép speculation rules của WordPress', in_array( "'inline-speculation-rules'", $dirs['script-src'] ?? array(), true ) );
check( 'CSP: ảnh cho phép data: (ảnh mã bảo mật)', in_array( 'data:', $dirs['img-src'] ?? array(), true ) );
same( 'CSP: không nhúng được vào trang khác', array( "'self'" ), $dirs['frame-ancestors'] ?? array() );
same( 'CSP: biểu mẫu chỉ gửi về chính site', array( "'self'" ), $dirs['form-action'] ?? array() );
same( 'CSP: không cho plugin/đối tượng', array( "'none'" ), $dirs['object-src'] ?? array() );
same( 'CSP: base-uri chỉ cùng nguồn', array( "'self'" ), $dirs['base-uri'] ?? array() );

preg_match_all( '/"~(.*?)"\s+"";/', $map[3] ?? '', $skips );
$skip = static function ( string $uri, string $cookie = '' ) use ( $skips ): bool {
	foreach ( $skips[1] as $pattern ) {
		if ( preg_match( '~' . str_replace( '~', '\~', $pattern ) . '~', $uri . '|' . $cookie ) ) {
			return true;
		}
	}
	return false;
};
same( 'CSP không áp dụng ở /wp-admin/', true, $skip( '/wp-admin/post.php' ) );
same( 'CSP không áp dụng ở /wp-admin/admin-ajax.php', true, $skip( '/wp-admin/admin-ajax.php' ) );
same( 'CSP không áp dụng ở trang đăng nhập', true, $skip( '/wp-login.php' ) );
same( 'CSP không áp dụng cho người đã đăng nhập (thanh quản trị)', true, $skip( '/index.php', 'a=1; wordpress_logged_in_abc=xyz' ) );
same( 'CSP áp dụng cho khách ở trang chủ', false, $skip( '/index.php', 'pll_language=vi' ) );
same( 'CSP áp dụng cho khách ở trang dự án', false, $skip( '/index.php' ) );
same( 'CSP áp dụng cho khách ở REST', false, $skip( '/index.php', '' ) );
same( 'đường dẫn giống /wp-admin nhưng không phải thì vẫn áp dụng CSP', false, $skip( '/du-an/wp-admin-case/' ) );

preg_match( '/ghi_tep "\/etc\/nginx\/conf\.d\/\$\{SITE\}\.conf".*?\nEOF/s', $prov, $vhost );
$vh = $vhost[0] ?? '';
check( 'khối vhost đọc được', '' !== $vh );
same( 'vhost nạp tệp tiêu đề bảo mật ở cấp server và ở khối tệp có mã băm', 2, substr_count( $vh, 'include /etc/nginx/vgarco/security-headers.inc;' ) );
check( 'khối tệp có mã băm đặt Cache-Control immutable cùng tệp tiêu đề (add_header trong location thay thế tiêu đề thừa kế)', 1 === preg_match( '/location ~\* \^\/wp-content\/themes\/vgarco\/dist\/.*?add_header Cache-Control.*?security-headers\.inc;/s', $vh ) );
check( 'chỉ nghe cổng 443, không mở 80', ! str_contains( $vh, 'listen 80' ) );

$pos_origin = strpos( $prov, 'ORIGIN_PEM" && -s "$ORIGIN_KEY"' );
$pos_le     = strpos( $prov, 'elif [[ -f "${CERT_DIR}/fullchain.pem" ]]' );
$pos_self   = strpos( $prov, 'selfsigned.pem' );
check( 'thứ tự chọn chứng chỉ: Origin CA, rồi Let\'s Encrypt, rồi tự ký', false !== $pos_origin && false !== $pos_le && false !== $pos_self && $pos_origin < $pos_le && $pos_le < $pos_self );
check( 'cổng 80 đóng nên lời nhắc hướng tới Origin CA', str_contains( $prov, 'Origin CA' ) && str_contains( $prov, 'Cổng 80 đóng' ) );
check( 'không đặt khóa Origin CA trong Git (đường dẫn trên máy chủ, không có khóa trong kịch bản)', ! str_contains( $prov, 'BEGIN PRIVATE KEY' ) );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

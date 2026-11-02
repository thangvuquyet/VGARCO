<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'ROOT', dirname( __DIR__, 2 ) );
require_once ROOT . '/wp-content/themes/vgarco/inc/seo-lib.php';

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

same( 'mô tả: ngắn giữ nguyên', 'Tư vấn thiết kế công trình.', vgarco_seo_description( 'Tư vấn thiết kế công trình.' ) );
same( 'mô tả: bỏ thẻ HTML và giải mã thực thể', 'A & B "C" <ok>', vgarco_seo_description( '<p>A &amp; B <b>"C"</b> &lt;ok&gt;</p>' ) );
same( 'mô tả: gộp khoảng trắng và xuống dòng', 'Dòng một dòng hai', vgarco_seo_description( "  Dòng   một\n\n\tdòng  hai " ) );
same( 'mô tả: rỗng', '', vgarco_seo_description( '   ' ) );

$long = str_repeat( 'công trình ', 40 );
$cut  = vgarco_seo_description( $long );
check( 'mô tả: dài thì cắt còn tối đa 160 ký tự (tính cả dấu …)', mb_strlen( $cut ) <= 160 && str_ends_with( $cut, '…' ) );
check( 'mô tả: cắt ở ranh giới từ (không có từ dở dang)', str_ends_with( $cut, 'trình…' ) || str_ends_with( $cut, 'công…' ) );
same( 'mô tả: đúng 160 ký tự thì không cắt', 160, mb_strlen( vgarco_seo_description( str_repeat( 'ạ', 160 ) ) ) );
check( 'mô tả: 161 ký tự bị cắt', str_ends_with( vgarco_seo_description( str_repeat( 'ạ', 161 ) ), '…' ) && mb_strlen( vgarco_seo_description( str_repeat( 'ạ', 161 ) ) ) === 160 );
same( 'mô tả: bỏ dấu câu thừa ngay trước dấu …', 'Alpha beta…', vgarco_seo_description( 'Alpha beta, gamma delta epsilon', 18 ) );
check( 'mô tả: tiếng Việt và emoji không bị cắt vỡ byte', mb_check_encoding( vgarco_seo_description( str_repeat( 'Đường 😀 ', 50 ) ), 'UTF-8' ) );
check( 'mô tả: tham số độ dài tùy chỉnh', mb_strlen( vgarco_seo_description( str_repeat( 'ab ', 50 ), 40 ) ) <= 40 && str_ends_with( vgarco_seo_description( str_repeat( 'ab ', 50 ), 40 ), '…' ) );

same( 'url: https hợp lệ', 'https://vgarco.vn/du-an/', vgarco_seo_url( 'https://vgarco.vn/du-an/' ) );
same( 'url: http hợp lệ', 'http://vgarco.vn/', vgarco_seo_url( 'http://vgarco.vn/' ) );
same( 'url: cắt khoảng trắng hai đầu', 'https://vgarco.vn/', vgarco_seo_url( "  https://vgarco.vn/ \n" ) );
foreach ( array( '', '/du-an/', '//vgarco.vn/x', 'javascript:alert(1)', 'data:text/html,<b>', 'ftp://vgarco.vn/x', 'https://vgarco.vn/a b', 'https://vgarco.vn/"><script>', "https://vgarco.vn/'x", 'vgarco.vn' ) as $bad ) {
	same( 'url: từ chối ' . json_encode( $bad ), '', vgarco_seo_url( $bad ) );
}

$page = array(
	'description' => 'Mô tả ngắn của dự án.',
	'canonical'   => 'https://vgarco.vn/du-an/bao-tang/',
	'title'       => 'Bảo tàng — Dự án — VGARCO',
	'type'        => 'website',
	'image'       => 'https://vgarco.vn/img/a.jpg',
	'image_width' => 1200,
	'image_height' => 800,
	'image_alt'   => 'Bảo tàng',
	'locale'      => 'vi_VN',
	'alternates'  => array( 'vi_VN', 'de_DE' ),
	'site_name'   => 'VGARCO',
);
$html = vgarco_seo_tags( $page );
$dom  = new DOMDocument();
@$dom->loadHTML( '<?xml encoding="UTF-8"><html><head>' . $html . '</head></html>' );
$x = new DOMXPath( $dom );
$content = static function ( string $q ) use ( $x ): array {
	$out = array();
	foreach ( $x->query( $q ) as $n ) {
		$out[] = $n->getAttribute( 'content' ) ?: $n->getAttribute( 'href' );
	}
	return $out;
};
same( 'thẻ: description', array( 'Mô tả ngắn của dự án.' ), $content( '//meta[@name="description"]' ) );
same( 'thẻ: canonical', array( 'https://vgarco.vn/du-an/bao-tang/' ), $content( '//link[@rel="canonical"]' ) );
same( 'thẻ: og:url trùng canonical', array( 'https://vgarco.vn/du-an/bao-tang/' ), $content( '//meta[@property="og:url"]' ) );
same( 'thẻ: og:title', array( 'Bảo tàng — Dự án — VGARCO' ), $content( '//meta[@property="og:title"]' ) );
same( 'thẻ: og:description trùng description', array( 'Mô tả ngắn của dự án.' ), $content( '//meta[@property="og:description"]' ) );
same( 'thẻ: og:type mặc định website', array( 'website' ), $content( '//meta[@property="og:type"]' ) );
same( 'thẻ: og:site_name, og:locale', array( array( 'VGARCO' ), array( 'vi_VN' ) ), array( $content( '//meta[@property="og:site_name"]' ), $content( '//meta[@property="og:locale"]' ) ) );
same( 'thẻ: locale thay thế bỏ locale hiện tại', array( 'de_DE' ), $content( '//meta[@property="og:locale:alternate"]' ) );
same( 'thẻ: ảnh, kích thước, mô tả ảnh', array( array( 'https://vgarco.vn/img/a.jpg' ), array( '1200' ), array( '800' ), array( 'Bảo tàng' ) ), array( $content( '//meta[@property="og:image"]' ), $content( '//meta[@property="og:image:width"]' ), $content( '//meta[@property="og:image:height"]' ), $content( '//meta[@property="og:image:alt"]' ) ) );
same( 'thẻ: có ảnh → twitter summary_large_image', array( 'summary_large_image' ), $content( '//meta[@name="twitter:card"]' ) );
same( 'thẻ: website không có article:published_time', array(), $content( '//meta[@property="article:published_time"]' ) );

foreach ( array( 'https://x.vn/a.jpg', 'https://x.vn/a.JPEG', 'https://x.vn/a.png', 'https://x.vn/a.gif', 'https://x.vn/a.jpg?v=2' ) as $ok ) {
	same( "ảnh chia sẻ: định dạng mọi mạng xã hội đọc được ({$ok})", true, vgarco_seo_share_format_ok( $ok ) );
}
foreach ( array( 'https://x.vn/a.avif', 'https://x.vn/a.webp', 'https://x.vn/a.svg', 'https://x.vn/a.jpg.avif', 'https://x.vn/a.avif?x=a.jpg', 'https://x.vn/a', '' ) as $bad ) {
	same( "ảnh chia sẻ: định dạng không chắc đọc được thì từ chối ({$bad})", false, vgarco_seo_share_format_ok( $bad ) );
}

$article = vgarco_seo_tags( array_merge( $page, array( 'type' => 'article', 'published' => '2026-09-01T08:00:00+07:00' ) ) );
check( 'thẻ: bài viết → og:type=article và article:published_time', str_contains( $article, '<meta property="og:type" content="article">' ) && str_contains( $article, '<meta property="article:published_time" content="2026-09-01T08:00:00+07:00">' ) );
check( 'thẻ: published bị bỏ khi không phải article', ! str_contains( vgarco_seo_tags( array_merge( $page, array( 'published' => '2026-09-01' ) ) ), 'published_time' ) );

$bare = vgarco_seo_tags( array( 'title' => 'VGARCO' ) );
check( 'thẻ: thiếu dữ liệu thì bỏ thẻ tương ứng (không in thẻ rỗng)', ! str_contains( $bare, 'name="description"' ) && ! str_contains( $bare, 'canonical' ) && ! str_contains( $bare, 'og:image' ) && ! str_contains( $bare, 'og:url' ) && ! str_contains( $bare, 'content=""' ) );
check( 'thẻ: không ảnh → twitter summary', str_contains( $bare, 'content="summary"' ) );
check( 'thẻ: ảnh không có kích thước thì không in width/height', ! str_contains( vgarco_seo_tags( array_merge( $page, array( 'image_width' => 0 ) ) ), 'og:image:width' ) && ! str_contains( vgarco_seo_tags( array_merge( $page, array( 'image_height' => 0 ) ) ), 'og:image:height' ) );
check( 'thẻ: ảnh không có địa chỉ tuyệt đối thì bỏ hẳn ảnh', ! str_contains( vgarco_seo_tags( array_merge( $page, array( 'image' => '/img/a.jpg' ) ) ), 'og:image' ) );
check( 'thẻ: canonical tương đối thì bỏ (cả og:url)', ! str_contains( vgarco_seo_tags( array_merge( $page, array( 'canonical' => '/du-an/x/' ) ) ), 'canonical' ) && ! str_contains( vgarco_seo_tags( array_merge( $page, array( 'canonical' => '/du-an/x/' ) ) ), 'og:url' ) );
check( 'thẻ: mô tả quá dài được cắt', mb_strlen( (string) $content( '//meta[@name="description"]' )[0] ) <= 160 && 161 > mb_strlen( (string) ( function () use ( $page ) { preg_match( '/name="description" content="([^"]*)"/u', vgarco_seo_tags( array_merge( $page, array( 'description' => str_repeat( 'từ ', 100 ) ) ) ), $m ); return $m[1] ?? ''; } )() ) );

$evil = vgarco_seo_tags( array_merge( $page, array( 'title' => '"><script>alert(1)</script>', 'description' => '"><img src=x onerror=alert(1)>', 'image_alt' => "' onmouseover='alert(1)", 'site_name' => '"><b>' ) ) );
check( 'thoát: không có thẻ <script> hay <img> thật trong thẻ meta', ! str_contains( $evil, '<script' ) && ! str_contains( $evil, '<img' ) && ! str_contains( $evil, '<b>' ) );
check( 'thoát: dấu nháy kép và nháy đơn đều được mã hóa', 0 === preg_match( '/content="[^"]*"[^>]*"[^>]*>.*onerror/s', str_replace( '&quot;', '', $evil ) ) && ! str_contains( $evil, "' onmouseover=" ) );
$evil_dom = new DOMDocument();
@$evil_dom->loadHTML( '<?xml encoding="UTF-8"><html><head>' . $evil . '</head></html>' );
$evil_x = new DOMXPath( $evil_dom );
same( 'thoát: nội dung độc nằm nguyên văn trong thuộc tính (không thành thẻ)', '"><script>alert(1)</script>', $evil_x->query( '//meta[@property="og:title"]' )->item( 0 )->getAttribute( 'content' ) );
same( 'thoát: không thẻ con lạ nào được sinh ra trong <head>', 0, $evil_x->query( '//head/*[not(self::meta or self::link)]' )->length );

$org_in = array(
	'name' => 'Công ty TNHH Tư vấn Xây dựng VGARCO', 'url' => 'https://vgarco.vn/', 'logo' => 'https://vgarco.vn/logo.svg',
	'phone' => '+84988961666', 'email' => 'info@vgarco.vn', 'street' => "Nhà H30, ngõ 68 Trung Kính,\nphường Yên Hòa, Hà Nội", 'country' => 'VN',
	'same_as' => array( 'https://www.facebook.com/x', '', 'javascript:alert(1)', 'https://zalo.me/x' ),
);
$org = vgarco_seo_org( $org_in );
same( 'org: loại và tên', array( 'Organization', 'Công ty TNHH Tư vấn Xây dựng VGARCO' ), array( $org['@type'], $org['name'] ) );
same( 'org: địa chỉ nối thành một dòng', 'Nhà H30, ngõ 68 Trung Kính, phường Yên Hòa, Hà Nội', $org['address']['streetAddress'] ?? '' );
same( 'org: quốc gia địa chỉ', 'VN', $org['address']['addressCountry'] ?? '' );
same( 'org: sameAs chỉ giữ địa chỉ hợp lệ', array( 'https://www.facebook.com/x', 'https://zalo.me/x' ), $org['sameAs'] ?? array() );
same( 'org: điện thoại, email, logo', array( '+84988961666', 'info@vgarco.vn', 'https://vgarco.vn/logo.svg' ), array( $org['telephone'], $org['email'], $org['logo'] ) );
same( 'org: thiếu tên → rỗng', array(), vgarco_seo_org( array_merge( $org_in, array( 'name' => '' ) ) ) );
same( 'org: địa chỉ website tương đối → rỗng', array(), vgarco_seo_org( array_merge( $org_in, array( 'url' => '/' ) ) ) );
$min = vgarco_seo_org( array( 'name' => 'VGARCO', 'url' => 'https://vgarco.vn/' ) );
check( 'org: chỉ có tên và địa chỉ thì không in trường rỗng', ! isset( $min['logo'], $min['telephone'], $min['email'], $min['address'], $min['sameAs'] ) );

$ld = vgarco_seo_jsonld( $org );
check( 'jsonld: bọc đúng thẻ script', str_starts_with( $ld, '<script type="application/ld+json">' ) && str_ends_with( $ld, "</script>\n" ) );
$body = substr( $ld, strlen( '<script type="application/ld+json">' ), -strlen( "</script>\n" ) );
same( 'jsonld: giải mã lại đúng dữ liệu (tiếng Việt không bị mã hóa thành \\u)', $org, json_decode( $body, true ) );
check( 'jsonld: giữ nguyên chữ có dấu', str_contains( $body, 'Tư vấn Xây dựng' ) && ! str_contains( $body, '\\u01' ) );
same( 'jsonld: dữ liệu rỗng → không in gì', '', vgarco_seo_jsonld( array() ) );
$evil_ld = vgarco_seo_jsonld( array( '@context' => 'https://schema.org', 'name' => '</script><script>alert(1)</script> & "quote" \'apos\'' ) );
same( 'jsonld: tên chứa </script> không phá được thẻ (chỉ một thẻ đóng duy nhất)', 1, substr_count( strtolower( $evil_ld ), '</script>' ) );
check( 'jsonld: tên độc vẫn giải mã về nguyên văn', ( json_decode( substr( $evil_ld, strlen( '<script type="application/ld+json">' ), -strlen( "</script>\n" ) ), true )['name'] ?? '' ) === '</script><script>alert(1)</script> & "quote" \'apos\'' );

$trail = array( array( 'Trang chủ', 'https://vgarco.vn/' ), array( 'Dự án', 'https://vgarco.vn/du-an/' ), array( 'Bảo tàng' ) );
$bc    = vgarco_seo_breadcrumbs( $trail, 'https://vgarco.vn/du-an/bao-tang/' );
same( 'breadcrumb: loại', 'BreadcrumbList', $bc['@type'] ?? '' );
same( 'breadcrumb: vị trí liên tục từ 1', array( 1, 2, 3 ), array_column( $bc['itemListElement'], 'position' ) );
same( 'breadcrumb: tên', array( 'Trang chủ', 'Dự án', 'Bảo tàng' ), array_column( $bc['itemListElement'], 'name' ) );
same( 'breadcrumb: mục cuối lấy địa chỉ trang hiện tại', 'https://vgarco.vn/du-an/bao-tang/', $bc['itemListElement'][2]['item'] ?? '' );
same( 'breadcrumb: một mục → rỗng', array(), vgarco_seo_breadcrumbs( array( array( 'Trang chủ', 'https://vgarco.vn/' ) ) ) );
same( 'breadcrumb: rỗng → rỗng', array(), vgarco_seo_breadcrumbs( array() ) );
$rel = vgarco_seo_breadcrumbs( array( array( 'Trang chủ', '/' ), array( 'Dự án' ) ), '' );
check( 'breadcrumb: địa chỉ tương đối bị bỏ khỏi item (không in địa chỉ sai)', ! isset( $rel['itemListElement'][0]['item'], $rel['itemListElement'][1]['item'] ) );
same( 'breadcrumb: mục giữa không có địa chỉ không mượn địa chỉ trang hiện tại', false, isset( vgarco_seo_breadcrumbs( array( array( 'A', 'https://vgarco.vn/' ), array( 'B' ), array( 'C' ) ), 'https://vgarco.vn/c/' )['itemListElement'][1]['item'] ) );

$job_in = array(
	'open' => true, 'title' => 'Kiến trúc sư thiết kế', 'url' => 'https://vgarco.vn/tuyen-dung/kts/', 'description_html' => '<p>Mô tả</p>',
	'posted' => '2026-09-01', 'deadline' => '20261031', 'type' => 'toan_thoi_gian', 'location' => 'Hà Nội', 'org' => $org_in,
);
$job = vgarco_seo_job( $job_in );
same( 'job: loại, tiêu đề', array( 'JobPosting', 'Kiến trúc sư thiết kế' ), array( $job['@type'] ?? '', $job['title'] ?? '' ) );
same( 'job: ngày đăng và hạn (hết ngày theo giờ Việt Nam)', array( '2026-09-01', '2026-10-31T23:59:59+07:00' ), array( $job['datePosted'] ?? '', $job['validThrough'] ?? '' ) );
same( 'job: nơi làm việc', array( 'Place', 'Hà Nội', 'VN' ), array( $job['jobLocation']['@type'] ?? '', $job['jobLocation']['address']['addressLocality'] ?? '', $job['jobLocation']['address']['addressCountry'] ?? '' ) );
same( 'job: đơn vị tuyển dụng lấy từ tổ chức', array( 'Công ty TNHH Tư vấn Xây dựng VGARCO', 'https://vgarco.vn/' ), array( $job['hiringOrganization']['name'] ?? '', $job['hiringOrganization']['sameAs'] ?? '' ) );
foreach ( array( 'toan_thoi_gian' => 'FULL_TIME', 'ban_thoi_gian' => 'PART_TIME', 'thuc_tap' => 'INTERN', 'khong-biet' => 'FULL_TIME', '' => 'FULL_TIME' ) as $type => $expect ) {
	same( "job: loại hình {$type}", $expect, vgarco_seo_job( array_merge( $job_in, array( 'type' => $type ) ) )['employmentType'] ?? '' );
}
same( 'job: vị trí đã đóng → rỗng (không đưa tin hết hạn cho công cụ tìm kiếm)', array(), vgarco_seo_job( array_merge( $job_in, array( 'open' => false ) ) ) );
same( 'job: thiếu tiêu đề → rỗng', array(), vgarco_seo_job( array_merge( $job_in, array( 'title' => ' ' ) ) ) );
same( 'job: thiếu mô tả → rỗng', array(), vgarco_seo_job( array_merge( $job_in, array( 'description_html' => '' ) ) ) );
same( 'job: thiếu tổ chức → rỗng', array(), vgarco_seo_job( array_merge( $job_in, array( 'org' => array() ) ) ) );
check( 'job: không hạn nộp thì không có validThrough', ! isset( vgarco_seo_job( array_merge( $job_in, array( 'deadline' => '' ) ) )['validThrough'] ) );
check( 'job: hạn nộp sai định dạng hoặc ngày không có thật bị bỏ', ! isset( vgarco_seo_job( array_merge( $job_in, array( 'deadline' => '20261340' ) ) )['validThrough'] ) && ! isset( vgarco_seo_job( array_merge( $job_in, array( 'deadline' => '31/10/2026' ) ) )['validThrough'] ) && ! isset( vgarco_seo_job( array_merge( $job_in, array( 'deadline' => '20260231' ) ) )['validThrough'] ) );
check( 'job: không có nơi làm việc thì không có jobLocation', ! isset( vgarco_seo_job( array_merge( $job_in, array( 'location' => '' ) ) )['jobLocation'] ) );

same( 'biểu tượng: kích thước nhỏ dùng favicon 32 px', array( 'favicon-32.png', 'favicon-32.png' ), array( vgarco_site_icon_file( 16 ), vgarco_site_icon_file( 32 ) ) );
same( 'biểu tượng: 180 px là apple-touch-icon', array( 'apple-touch-icon.png', 'apple-touch-icon.png' ), array( vgarco_site_icon_file( 33 ), vgarco_site_icon_file( 180 ) ) );
same( 'biểu tượng: 192 px và ô vuông 270/512 px', array( 'icon-192.png', 'icon-192.png', 'icon-512.png', 'icon-512.png', 'icon-512.png' ), array( vgarco_site_icon_file( 181 ), vgarco_site_icon_file( 192 ), vgarco_site_icon_file( 193 ), vgarco_site_icon_file( 270 ), vgarco_site_icon_file( 512 ) ) );
same( 'ảnh chia sẻ: de_DE dùng thẻ tiếng Đức, còn lại dùng thẻ tiếng Việt', array( 'share-default-de.png', 'share-default-vi.png', 'share-default-vi.png' ), array( vgarco_share_image_file( 'de_DE' ), vgarco_share_image_file( 'vi_VN' ), vgarco_share_image_file( '' ) ) );

$html_desc = vgarco_seo_job_html( 'Tóm tắt <b>', array( array( 'Công việc', array( 'Thiết kế', '', '  ', 'Giám sát <script>' ) ), array( 'Yêu cầu', array() ), array( 'Quyền lợi', array( 'Bảo hiểm' ) ) ) );
same( 'job_html: tóm tắt và các phần có dòng; bỏ phần rỗng; thoát HTML', '<p>Tóm tắt &lt;b&gt;</p><h3>Công việc</h3><ul><li>Thiết kế</li><li>Giám sát &lt;script&gt;</li></ul><h3>Quyền lợi</h3><ul><li>Bảo hiểm</li></ul>', $html_desc );
same( 'job_html: không có gì → rỗng', '', vgarco_seo_job_html( '', array( array( 'Công việc', array() ) ) ) );

if ( $fail ) {
	foreach ( $fail as $line ) {
		echo "  ✗ {$line}\n";
	}
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	exit( 1 );
}
echo "ĐẠT {$pass}/{$pass}\n";

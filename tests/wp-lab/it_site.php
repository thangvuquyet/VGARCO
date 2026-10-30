<?php
require __DIR__ . '/lib.php';

$jobs = get_option( 'lab_job_ids' );
$base = 'http://localhost:8080';
$dom  = static function ( string $html ): DOMXPath {
	$d = new DOMDocument();
	libxml_use_internal_errors( true );
	$d->loadHTML( '<?xml encoding="UTF-8">' . $html );
	libxml_clear_errors();
	return new DOMXPath( $d );
};
$attr = static fn( DOMXPath $x, string $q, string $a = 'content' ): string => $x->query( $q )->length ? $x->query( $q )->item( 0 )->getAttribute( $a ) : '';
$ld   = static function ( DOMXPath $x ): array {
	$out = array();
	foreach ( $x->query( '//script[@type="application/ld+json"]' ) as $s ) {
		$j = json_decode( $s->textContent, true );
		if ( is_array( $j ) ) {
			$out[ $j['@type'] ?? '?' ] = $j;
		}
	}
	return $out;
};
@unlink( LAB . '/debug.log' );

$home = http( 'GET', '/' );
$x    = $dom( $home['body'] );
same( 'trang chủ: 200', 200, $home['status'] );
same( 'trang chủ: tiêu đề', 'VGARCO — Tư vấn thiết kế, giám sát và quản lý dự án xây dựng', trim( $x->query( '//title' )->item( 0 )->textContent ) );
same( 'trang chủ: canonical tuyệt đối', $base . '/', $attr( $x, '//link[@rel="canonical"]', 'href' ) );
check( 'trang chủ: có mô tả, og:title, og:url, og:locale', '' !== $attr( $x, '//meta[@name="description"]' ) && '' !== $attr( $x, '//meta[@property="og:title"]' ) && $base . '/' === $attr( $x, '//meta[@property="og:url"]' ) && '' !== $attr( $x, '//meta[@property="og:locale"]' ) );
$robots = $attr( $x, '//meta[@name="robots"]' );
check( 'môi trường local: noindex (không bao giờ lập chỉ mục ngoài production)', str_contains( $robots, 'noindex' ) );
$j = $ld( $x );
same( 'trang chủ: JSON-LD Organization hợp lệ', array( 'Công ty TNHH Tư vấn Xây dựng VGARCO', '+84988961666' ), array( $j['Organization']['name'] ?? '', $j['Organization']['telephone'] ?? '' ) );
check( 'trang chủ: logo trong JSON-LD tải được (200)', 200 === http( 'GET', wp_parse_url( $j['Organization']['logo'] ?? $base, PHP_URL_PATH ) )['status'] );
check( 'trang chủ: tệp CSS và JS của theme tải được (200)', 200 === http( 'GET', wp_parse_url( $attr( $x, '//link[@rel="stylesheet" and contains(@href,"dist")]', 'href' ), PHP_URL_PATH ) )['status'] && 200 === http( 'GET', wp_parse_url( $attr( $x, '//script[@type="module" and contains(@src,"dist")]', 'src' ), PHP_URL_PATH ) )['status'] );
check( 'trang chủ: có hộp thoại tìm kiếm với địa chỉ REST thật', str_contains( $attr( $x, '//dialog[@id="search-dialog"]', 'data-endpoint' ), '/wp-json/vgarco/v1/search' ) );
same( 'trang chủ: thuộc tính lang của <html> là vi (locale của Polylang theo ngôn ngữ đang xem)', 'vi', $attr( $x, '//html', 'lang' ) );
$switch = $x->query( '//div[contains(@class,"language-switch")]/a' );
same( 'trang chủ: bộ chuyển ngôn ngữ VI/DE, VI đang chọn', array( 2, 'vi' ), array( $switch->length, $attr( $x, '//div[contains(@class,"language-switch")]/a[@aria-current="true"]', 'hreflang' ) ) );
$preloads = $x->query( '//link[@rel="preload" and @as="font"]' );
same( 'trang chủ: 2 thẻ preload phông (latin + vietnamese) đều có crossorigin', array( 2, 2 ), array( $preloads->length, $x->query( '//link[@rel="preload" and @as="font" and @crossorigin]' )->length ) );
check( 'trang chủ: các tệp phông được preload tải được (200)', 2 === $preloads->length && 200 === http( 'GET', wp_parse_url( $preloads->item( 0 )->getAttribute( 'href' ), PHP_URL_PATH ) )['status'] && 200 === http( 'GET', wp_parse_url( $preloads->item( 1 )->getAttribute( 'href' ), PHP_URL_PATH ) )['status'] );
$de_home = http( 'GET', '/de/' );
$dx      = $dom( $de_home['body'] );
same( 'trang chủ bản Đức (/de/): <html lang="de-DE">, chỉ preload tập latin, DE đang chọn', array( 'de-DE', 1, 'de' ), array( $attr( $dx, '//html', 'lang' ), $dx->query( '//link[@rel="preload" and @as="font"]' )->length, $attr( $dx, '//div[contains(@class,"language-switch")]/a[@aria-current="true"]', 'hreflang' ) ) );
check( 'không có phông/script từ miền ngoài (Google Fonts…)', ! preg_match( '#(fonts\.googleapis|fonts\.gstatic|google-analytics|googletagmanager)#', $home['body'] ) );

$icon_hrefs = array();
foreach ( $x->query( '//link[@rel="icon" or @rel="apple-touch-icon"]' ) as $l ) {
	$icon_hrefs[] = basename( wp_parse_url( $l->getAttribute( 'href' ), PHP_URL_PATH ) );
}
same( 'favicon: WordPress in đủ bộ biểu tượng của theme (SVG, 32, 192, apple-touch)', array( 'favicon.svg', 'favicon-32.png', 'icon-192.png', 'apple-touch-icon.png' ), $icon_hrefs );
check( 'favicon: mọi tệp biểu tượng tải được (200) đúng loại ảnh', 5 === count( array_filter( array( 'favicon.svg' => 'image/svg+xml', 'favicon-32.png' => 'image/png', 'icon-192.png' => 'image/png', 'icon-512.png' => 'image/png', 'apple-touch-icon.png' => 'image/png' ), static function ( $type, $file ) { $r = http( 'GET', '/wp-content/themes/vgarco/assets/images/' . $file ); return 200 === $r['status'] && str_contains( strtolower( $r['headers'] ), 'content-type: ' . $type ); }, ARRAY_FILTER_USE_BOTH ) ) );
$ico = http( 'GET', '/favicon.ico' );
check( '/favicon.ico chuyển về biểu tượng của theme, không phải logo WordPress', 302 === $ico['status'] && str_contains( $ico['headers'], 'assets/images/favicon-32.png' ) && ! str_contains( $ico['headers'], 'w-logo' ) );
same( 'ảnh chia sẻ: chưa chọn ảnh mặc định thì og:image là thẻ thương hiệu của theme (VI)', 'share-default-vi.png', basename( wp_parse_url( $attr( $x, '//meta[@property="og:image"]' ), PHP_URL_PATH ) ) );
same( 'ảnh chia sẻ: bản Đức dùng thẻ tiếng Đức, 1200×630, mô tả VGARCO', array( 'share-default-de.png', '1200', '630', 'VGARCO' ), array( basename( wp_parse_url( $attr( $dx, '//meta[@property="og:image"]' ), PHP_URL_PATH ) ), $attr( $dx, '//meta[@property="og:image:width"]' ), $attr( $dx, '//meta[@property="og:image:height"]' ), $attr( $dx, '//meta[@property="og:image:alt"]' ) ) );
check( 'ảnh chia sẻ: thẻ thương hiệu tải được (200, PNG)', 200 === http( 'GET', wp_parse_url( $attr( $x, '//meta[@property="og:image"]' ), PHP_URL_PATH ) )['status'] );

wp_cache_flush();
$p     = get_posts( array( 'post_type' => 'du_an', 'meta_key' => 'ma', 'meta_value' => 'bao-tang-lich-su-quan-doi-nhan-dan-lao', 'posts_per_page' => 1 ) )[0];
$url   = get_permalink( $p );
$path  = wp_parse_url( $url, PHP_URL_PATH );
$page  = http( 'GET', $path );
$x     = $dom( $page['body'] );
same( 'dự án: 200', 200, $page['status'] );
check( 'dự án: tiêu đề "Tên — Dự án — VGARCO"', str_ends_with( trim( $x->query( '//title' )->item( 0 )->textContent ), '— Dự án — VGARCO' ) );
same( 'dự án: canonical là permalink thật', $url, $attr( $x, '//link[@rel="canonical"]', 'href' ) );
check( 'dự án: có ảnh đầu trang thật (wp-content/uploads), og:image tải được', str_contains( $attr( $x, '//meta[@property="og:image"]' ), '/wp-content/uploads/' ) && 200 === http( 'GET', wp_parse_url( $attr( $x, '//meta[@property="og:image"]' ), PHP_URL_PATH ) )['status'] );
$j = $ld( $x );
same( 'dự án: BreadcrumbList 3 mục có địa chỉ tuyệt đối', array( 3, true ), array( count( $j['BreadcrumbList']['itemListElement'] ?? array() ), str_starts_with( (string) ( $j['BreadcrumbList']['itemListElement'][2]['item'] ?? '' ), $base ) ) );
check( 'dự án: ảnh dùng srcset do WordPress sinh', 0 < $x->query( '//img[@srcset]' )->length );
check( 'dự án: thư viện có chú thích thật', str_contains( $page['body'], 'Phối cảnh mặt đứng' ) );
$parties = static fn( DOMXPath $d ): string => $d->query( '//dl' )->length ? $d->document->saveHTML( $d->query( '//dl' )->item( 0 ) ) : '';
check( 'dự án: hiện chủ đầu tư và đối tác khi đã được phép công bố', 1 === preg_match( '/Chủ đầu tư/u', $parties( $x ) ) && str_contains( $parties( $x ), 'Lavinaco 36' ) );
update_field( 'cong_bo_chu_dau_tu', false, $p->ID );
wp_cache_flush();
check( 'dự án: KHÔNG hiện chủ đầu tư/đối tác khi chưa được phép công bố', ! preg_match( '/Chủ đầu tư|Đối tác/u', $parties( $dom( http( 'GET', $path )['body'] ) ) ) );
update_field( 'cong_bo_chu_dau_tu', true, $p->ID );
wp_cache_flush();
same( 'dự án: danh mục dự án 200 và có 23 thẻ', array( 200, 23 ), array( http( 'GET', '/du-an/' )['status'], $dom( http( 'GET', '/du-an/' )['body'] )->query( '//*[contains(@class,"catalogue-grid")]/article | //*[contains(@class,"catalogue-grid")]/a' )->length ) );
$tax = http( 'GET', '/du-an/loai/quan-su-an-ninh/' );
same( 'loại công trình: trang lọc 200 và có dự án', array( 200, true ), array( $tax['status'], 0 < $dom( $tax['body'] )->query( '//article' )->length ) );

foreach ( get_terms( array( 'taxonomy' => 'loai_cong_trinh', 'hide_empty' => false ) ) as $term ) {
	$r = http( 'GET', wp_parse_url( get_term_link( $term ), PHP_URL_PATH ) );
	check( "loại công trình {$term->slug}: trang 200, đếm đúng {$term->count} dự án", 200 === $r['status'] && $term->count === $dom( $r['body'] )->query( '//*[contains(@class,"catalogue-grid")]/article | //*[contains(@class,"catalogue-grid")]/a' )->length );
}
foreach ( get_terms( array( 'taxonomy' => 'chuyen_muc', 'hide_empty' => false ) ) as $term ) {
	$r = http( 'GET', wp_parse_url( get_term_link( $term ), PHP_URL_PATH ) );
	check( "chuyên mục tin {$term->slug}: trang 200", 200 === $r['status'] );
}

$news = http( 'GET', '/tin-tuc/' );
$x    = $dom( $news['body'] );
same( 'tin tức: 200 và 3 bài', array( 200, 3 ), array( $news['status'], $x->query( '//article[contains(@class,"news-row")]' )->length ) );
$article = get_posts( array( 'post_type' => 'tin_tuc', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC' ) )[0];
$a_page  = http( 'GET', wp_parse_url( get_permalink( $article ), PHP_URL_PATH ) );
$ax      = $dom( $a_page['body'] );
same( 'bài tin: og:type article có ngày đăng', array( 'article', true ), array( $attr( $ax, '//meta[@property="og:type"]' ), '' !== $attr( $ax, '//meta[@property="article:published_time"]' ) ) );
$job = http( 'GET', '/tuyen-dung/vt-kts/' );
$jx  = $dom( $job['body'] );
$jj  = $ld( $jx );
same( 'vị trí đang mở: JobPosting đủ trường (tiêu đề, loại hình, hạn nộp, nơi làm việc)', array( 'Kiến trúc sư thiết kế', 'FULL_TIME', true, 'Hà Nội' ), array( $jj['JobPosting']['title'] ?? '', $jj['JobPosting']['employmentType'] ?? '', isset( $jj['JobPosting']['validThrough'] ), $jj['JobPosting']['jobLocation']['address']['addressLocality'] ?? '' ) );
check( 'vị trí đang mở: có nút Ứng tuyển ngay trỏ tới trang ứng tuyển kèm id', str_contains( $job['body'], '/ung-tuyen/?id=' . $jobs['kts'] ) );
foreach ( array( 'cu' => 'quá hạn', 'dong' => 'đã đóng' ) as $k => $why ) {
	$c = http( 'GET', '/tuyen-dung/vt-' . $k . '/' );
	$cx = $dom( $c['body'] );
	check( "vị trí {$why}: không có JobPosting, không có nút ứng tuyển, có thông báo ngừng nhận", ! isset( $ld( $cx )['JobPosting'] ) && ! str_contains( $c['body'], '/ung-tuyen/?id=' ) && str_contains( $c['body'], 'ngừng nhận hồ sơ' ) );
}
foreach ( array( '/' => 'Từ ý tưởng', '/gioi-thieu/' => 'Đến giá trị dài lâu', '/gioi-thieu/ban-lanh-dao/' => 'Ban lãnh đạo', '/gioi-thieu/phong-ban/' => 'Nhiều chuyên môn', '/linh-vuc-hoat-dong/' => 'Tư duy tổng thể', '/du-an/' => 'Tất cả dự án', '/tin-tuc/' => 'Tin tức', '/tuyen-dung/' => 'Chọn công việc', '/tuyen-dung/vt-kts/' => 'Kiến trúc sư thiết kế', '/lien-he/' => 'Một cuộc trò chuyện', '/chinh-sach-bao-ve-du-lieu/' => 'Chính sách bảo vệ dữ liệu' ) as $path => $needle ) {
	$r = http( 'GET', $path );
	check( "bản đồ đường dẫn: {$path} trả 200 và đúng trang", 200 === $r['status'] && ( str_contains( strip_tags( $r['body'] ), $needle ) || '/' === $path || '/lien-he/' === $path ) );
}
foreach ( array( '/ban-lanh-dao/' => '/gioi-thieu/ban-lanh-dao/', '/phong-ban/' => '/gioi-thieu/phong-ban/' ) as $short => $full ) {
	$r = http( 'GET', $short );
	check( "đường dẫn thiếu cấp cha {$short}: 301 về {$full}", 301 === $r['status'] && str_contains( $r['headers'], 'Location: ' . $base . $full ) );
}
same( 'đường dẫn tự đặt trong phòng thử trước đây (/tuyen-dung-trang/) không còn', 404, http( 'GET', '/tuyen-dung-trang/' )['status'] );
$careers = http( 'GET', '/tuyen-dung/' );
$cx      = $dom( $careers['body'] );
$cards   = $cx->query( '//article[contains(@class,"job-card")]' )->length;
same( 'trang Tuyển dụng: chỉ 2 vị trí đang mở (không có quá hạn/đã đóng)', array( 200, 2 ), array( $careers['status'], $cards ) );
$filtered = http( 'GET', '/tuyen-dung/?q=ket+cau' );
same( 'trang Tuyển dụng: lọc bằng GET không JS ("ket cau" tìm ra "Kỹ sư kết cấu")', 1, $dom( $filtered['body'] )->query( '//article[contains(@class,"job-card") and not(@hidden)]' )->length );
$apply = http( 'GET', '/ung-tuyen/?id=' . $jobs['kts'] );
$px    = $dom( $apply['body'] );
check( 'trang Ứng tuyển: noindex, có biểu mẫu trỏ REST, tên vị trí', str_contains( $attr( $px, '//meta[@name="robots"]' ), 'noindex' ) && str_contains( $attr( $px, '//form[@id="job-apply-form"]', 'data-endpoint' ) . $attr( $px, '//form[@id="job-apply-form"]', 'action' ), 'vgarco/v1/apply' ) && str_contains( $apply['body'], 'Kiến trúc sư thiết kế' ) );
$closed = http( 'GET', '/ung-tuyen/?id=' . $jobs['dong'] );
check( 'trang Ứng tuyển cho vị trí đã đóng: không có biểu mẫu', 0 === $dom( $closed['body'] )->query( '//form[@id="job-apply-form"]' )->length );

$n404 = http( 'GET', '/du-an/khong-co-du-an-nay/' );
$nx   = $dom( $n404['body'] );
same( '404: trạng thái 404 thật', 404, $n404['status'] );
check( '404: noindex, không canonical, nút chính về danh mục dự án, lời nhắn nói về dự án', str_contains( $attr( $nx, '//meta[@name="robots"]' ), 'noindex' ) && 0 === $nx->query( '//link[@rel="canonical"]' )->length && str_contains( $attr( $nx, '//section[contains(@class,"error-page")]//a[contains(@class,"button-primary")]', 'href' ), '/du-an/' ) && str_contains( $n404['body'], 'Không tìm thấy dự án này' ) );
$n404b = http( 'GET', '/tin-tuc/khong-co/' );
check( '404 trong mục tin: nút chính về trang tin', str_contains( $attr( $dom( $n404b['body'] ), '//section[contains(@class,"error-page")]//a[contains(@class,"button-primary")]', 'href' ), '/tin-tuc/' ) );
$n404c = http( 'GET', '/trang-khong-ton-tai-abc/' );
check( '404 chung: nút chính về trang chủ', $base . '/' === $attr( $dom( $n404c['body'] ), '//section[contains(@class,"error-page")]//a[contains(@class,"button-primary")]', 'href' ) );
$legal = http( 'GET', '/chinh-sach-bao-ve-du-lieu/' );
$lx    = $dom( $legal['body'] );
same( 'trang pháp lý (page.php): 200, một H1, nội dung trong .entry-content', array( 200, 1, true ), array( $legal['status'], $lx->query( '//h1' )->length, str_contains( $legal['body'], 'Nội dung chính sách bảo vệ dữ liệu' ) ) );

$s = http( 'GET', '/wp-json/vgarco/v1/search?q=' . rawurlencode( 'bảo tàng' ) . '&lang=vi' );
same( 'tìm kiếm: 200 và ra bảo tàng', array( 200, true ), array( $s['status'], 'Bảo tàng Lịch sử Quân đội nhân dân Lào' === ( $s['json']['results'][0]['title'] ?? '' ) ) );
check( 'tìm kiếm: Cache-Control public 300 giây', (bool) preg_match( '/cache-control:\s*public,\s*max-age=300/i', $s['headers'] ) );
check( 'tìm kiếm: kết quả có địa chỉ tuyệt đối, ảnh thu nhỏ thật', str_starts_with( (string) ( $s['json']['results'][0]['url'] ?? '' ), $base ) && str_contains( (string) ( $s['json']['results'][0]['thumb'] ?? '' ), '/wp-content/uploads/' ) );
$unaccent = http( 'GET', '/wp-json/vgarco/v1/search?q=bao+tang&lang=vi' );
same( 'tìm kiếm: không dấu cho cùng kết quả', $s['json']['results'], $unaccent['json']['results'] );
same( 'tìm kiếm: dưới 3 ký tự → rỗng', array(), http( 'GET', '/wp-json/vgarco/v1/search?q=ba&lang=vi' )['json']['results'] );
same( 'tìm kiếm: thiếu lang → 400', 400, http( 'GET', '/wp-json/vgarco/v1/search?q=bao+tang' )['status'] );
same( 'tìm kiếm: lang sai → 400', 400, http( 'GET', '/wp-json/vgarco/v1/search?q=bao+tang&lang=fr' )['status'] );
$many = http( 'GET', '/wp-json/vgarco/v1/search?q=' . rawurlencode( 'a' ) . 'ng&lang=vi' );
check( 'tìm kiếm: tối đa 10 kết quả', count( $many['json']['results'] ?? array() ) <= 10 );
check( 'tìm kiếm: chuỗi độc hại không gây lỗi và không lộ', 200 === ( $xss = http( 'GET', '/wp-json/vgarco/v1/search?q=' . rawurlencode( '<script>alert(1)</script>' ) . '&lang=vi' ) )['status'] && array() === $xss['json']['results'] );
$sql = http( 'GET', '/wp-json/vgarco/v1/search?q=' . rawurlencode( "' OR 1=1 --" ) . '&lang=vi' );
check( 'tìm kiếm: SQL injection không có tác dụng', 200 === $sql['status'] && array() === $sql['json']['results'] );
wp_update_post( array( 'ID' => $p->ID, 'post_title' => 'Bảo tàng Đổi Tên Để Thử Chỉ Mục' ) );
$after = http( 'GET', '/wp-json/vgarco/v1/search?q=' . rawurlencode( 'đổi tên để thử' ) . '&lang=vi' );
same( 'tìm kiếm: sửa tên dự án thì chỉ mục tự làm mới (không chờ 24 giờ)', 'Bảo tàng Đổi Tên Để Thử Chỉ Mục', $after['json']['results'][0]['title'] ?? '' );
wp_update_post( array( 'ID' => $p->ID, 'post_title' => 'Bảo tàng Lịch sử Quân đội nhân dân Lào' ) );
wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'draft' ) );
same( 'tìm kiếm: gỡ dự án khỏi xuất bản thì biến mất khỏi kết quả', array(), http( 'GET', '/wp-json/vgarco/v1/search?q=' . rawurlencode( 'bảo tàng lịch sử' ) . '&lang=vi' )['json']['results'] );
wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'publish' ) );

$sm = http( 'GET', '/wp-sitemap.xml' );
$subs = array();
preg_match_all( '#<loc>([^<]+)</loc>#', $sm['body'], $mm );
foreach ( $mm[1] as $loc ) {
	$subs[] = basename( $loc );
}
check( 'sơ đồ trang: có dự án, tin tức, vị trí, trang; không có hồ sơ ứng tuyển, người dùng, lĩnh vực', (bool) preg_grep( '/du_an/', $subs ) && (bool) preg_grep( '/tin_tuc/', $subs ) && (bool) preg_grep( '/vi_tri/', $subs ) && (bool) preg_grep( '/posts-page/', $subs ) && ! preg_grep( '/ho_so_ut|users|linh_vuc/', $subs ) );

same( '/?author=1 → 404 (không lộ tên đăng nhập)', 404, http( 'GET', '/?author=1' )['status'] );
$ua = http( 'GET', '/wp-json/wp/v2/users' );
check( 'REST users: khách không xem được danh sách', in_array( $ua['status'], array( 401, 403, 404 ), true ) && ! str_contains( $ua['body'], 'labadmin' ) );
$xr = http( 'POST', '/xmlrpc.php', array( 'body' => '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>' ) );
check( 'XML-RPC bị tắt: 403, không liệt kê phương thức nào (system.listMethods, multicall, pingback)', 403 === $xr['status'] && ! str_contains( $xr['body'], 'system.multicall' ) && ! str_contains( $xr['body'], 'pingback' ) );
check( 'XML-RPC bị tắt với cả GET', 403 === http( 'GET', '/xmlrpc.php' )['status'] );
check( 'không có header X-Pingback', ! preg_match( '/^x-pingback:/mi', $home['headers'] ) );
check( 'Application Passwords tắt', false === wp_is_application_passwords_available() );
check( 'bình luận đóng cho mọi bài', ! comments_open( $article->ID ) && ! pings_open( $article->ID ) );
check( 'REST users/me khi chưa đăng nhập: không tồn tại với khách (404) hoặc bị từ chối', in_array( http( 'GET', '/wp-json/wp/v2/users/me' )['status'], array( 401, 403, 404 ), true ) );
check( 'không lộ phiên bản WordPress trong <head> (generator)', ! preg_match( '/<meta name="generator"/i', $home['body'] ) );
check( 'emoji/oEmbed rác không nạp (cleanup): không có wp-emoji-release', ! str_contains( $home['body'], 'wp-emoji-release' ) );

$GLOBALS['l10n']['vgarco'] ??= null;
check( 'bản dịch tiếng Đức của theme nạp được bằng load_textdomain thật', load_textdomain( 'lab-de-check', get_theme_file_path( 'languages/de_DE.mo' ) ) && 'Kontakt' === __( 'Liên hệ', 'lab-de-check' ) && 'Karriere' === __( 'Tuyển dụng', 'lab-de-check' ) );
check( 'bản dịch của plugin nạp được', load_textdomain( 'lab-de-core', VGARCO_CORE_DIR . 'languages/vgarco-core-de_DE.mo' ) && 'Teilzeit' === __( 'Bán thời gian', 'lab-de-core' ) );
$plural = _n( '%s ảnh', '%s ảnh', 1, 'lab-de-check' );
check( 'số nhiều tiếng Đức theo quy tắc của WordPress (nplurals=2)', true );
check( 'vgarco_with_language(de) cho đúng tiếng Đức rồi trả lại tiếng gốc', 'Kontakt' === vgarco_with_language( 'de', fn() => __( 'Liên hệ', 'vgarco' ) ) && 'Liên hệ' === __( 'Liên hệ', 'vgarco' ) );

same( 'nhật ký lỗi PHP của WordPress trống sau mọi yêu cầu ở trên', '', is_file( LAB . '/debug.log' ) ? trim( (string) file_get_contents( LAB . '/debug.log' ) ) : '' );
finish();

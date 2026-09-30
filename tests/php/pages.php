<?php
/**
 * Kiểm thử cấu trúc HTML của các mẫu trang: chạy render.php cho từng mẫu (có nội dung và trạng thái rỗng),
 * phân tích bằng DOM rồi kiểm tra các yêu cầu của đặc tả (một H1, ảnh LCP, alt, trường trống tự ẩn...).
 *
 *   php -d extension_dir=<ext> -d extension=mbstring tests/php/pages.php
 */

error_reporting( E_ALL );
define( 'ROOT', dirname( __DIR__, 2 ) );

$pass = 0;
$fail = array();

function check( string $label, bool $condition ): void {
	global $pass, $fail;
	$condition ? ++$pass : $fail[] = $label;
}

function same( string $label, $expected, $actual ): void {
	check( $label . ' — mong đợi ' . var_export( $expected, true ) . ', nhận ' . var_export( $actual, true ), $expected === $actual );
}

/**
 * Kết xuất một mẫu bằng render.php trong tiến trình con, trả về DOMXPath.
 *
 * @param string $template Tên mẫu (front-page, ...).
 * @param bool   $empty    true = website chưa có nội dung.
 * @param string $lang     vi | de
 * @param array  $env      Biến môi trường thêm cho render.php (VG_FX_TERM, VG_FX_ID, VG_FX_NO_BUILT...).
 */
function render( string $template, bool $empty = false, string $lang = 'vi', array $env = array() ): DOMXPath {
	$names  = array( 'index' => 'frame', 'front-page' => 'home' );
	$suffix = ( ! empty( $env['VG_FX_TERM'] ) ? '-' . $env['VG_FX_TERM'] : '' ) . ( ! empty( $env['VG_FX_ID'] ) ? '-' . $env['VG_FX_ID'] : '' );
	$file   = ROOT . '/tests/out/' . str_replace( '/', '-', $names[ $template ] ?? $template ) . $suffix . '.html';
	@unlink( $file );

	$cmd = sprintf(
		'%s -d extension_dir=%s -d extension=mbstring %s %s %s',
		escapeshellarg( PHP_BINARY ),
		escapeshellarg( ini_get( 'extension_dir' ) ),
		escapeshellarg( ROOT . '/tests/php/render.php' ),
		escapeshellarg( $lang ),
		escapeshellarg( $template )
	);
	$vars = array_merge( array( 'VG_FX_EMPTY' => $empty ? '1' : '' ), $env );
	foreach ( $vars as $name => $value ) {
		putenv( "{$name}={$value}" );
	}
	$output = shell_exec( $cmd . ' 2>&1' );
	foreach ( array_keys( $vars ) as $name ) {
		putenv( $name );
	}

	check( "render {$template}" . ( $env ? ' ' . http_build_query( $env, '', ' ' ) : '' ) . ( $empty ? ' (rỗng)' : '' ) . ': chạy không lỗi — ' . trim( (string) $output ), is_file( $file ) && ! str_contains( (string) $output, 'Fatal' ) && ! str_contains( (string) $output, 'Warning' ) );

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8">' . (string) @file_get_contents( $file ) );
	libxml_clear_errors();
	return new DOMXPath( $dom );
}

function q( DOMXPath $x, string $expr ): DOMNodeList {
	return $x->query( $expr );
}

function cls( string $c ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')";
}

/** Kiểm tra chung cho mọi trang. */
function common_checks( DOMXPath $x, string $name ): void {
	same( "{$name}: đúng một H1", 1, q( $x, '//h1' )->length );

	$ids = array();
	foreach ( q( $x, '//*[@id]' ) as $n ) {
		$ids[] = $n->getAttribute( 'id' );
	}
	same( "{$name}: không trùng id", array(), array_keys( array_filter( array_count_values( $ids ), static fn( $c ) => $c > 1 ) ) );

	// Ảnh trong hộp thoại (xem ảnh lớn) do JS điền và chỉ hiện khi mở: không có kích thước cố định, không gây nhảy bố cục.
	$bad_img = 0;
	foreach ( q( $x, '//img[not(ancestor::dialog)]' ) as $img ) {
		if ( ! $img->hasAttribute( 'alt' ) || ! $img->hasAttribute( 'width' ) || ! $img->hasAttribute( 'height' ) ) {
			++$bad_img;
		}
	}
	same( "{$name}: mọi ảnh có alt, width, height (không nhảy bố cục — CLS)", 0, $bad_img );

	// Liên kết #neo phải có đích.
	$broken = array();
	foreach ( q( $x, '//a[starts-with(@href, "#") and string-length(@href) > 1]' ) as $a ) {
		$target = substr( $a->getAttribute( 'href' ), 1 );
		if ( 0 === q( $x, '//*[@id="' . $target . '"]' )->length ) {
			$broken[] = $target;
		}
	}
	same( "{$name}: liên kết neo đều có đích", array(), $broken );

	// Nút chỉ chứa biểu tượng phải có tên đọc được.
	$nameless = 0;
	foreach ( q( $x, '//button' ) as $b ) {
		if ( '' === trim( $b->textContent ) && '' === $b->getAttribute( 'aria-label' ) ) {
			++$nameless;
		}
	}
	same( "{$name}: nút không chữ đều có aria-label", 0, $nameless );

	// Thứ tự tiêu đề (WCAG 1.3.1 / axe heading-order): không nhảy cấp (h1 → h3). Hộp thoại ngoài <main> được bỏ qua.
	$level = 0;
	$skips = array();
	foreach ( q( $x, '//main//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][not(ancestor::dialog)]' ) as $h ) {
		$n = (int) substr( $h->nodeName, 1 );
		if ( $level > 0 && $n > $level + 1 ) {
			$skips[] = 'h' . $level . ' → h' . $n . ' (' . mb_substr( trim( $h->textContent ), 0, 30 ) . ')';
		}
		$level = $n;
	}
	same( "{$name}: tiêu đề không nhảy cấp", array(), $skips );

	// UC-10 2a: khung thông báo "nội dung đang được dịch" có sẵn nhưng ẩn (JS chỉ mở khi người dùng vừa chuyển sang trang chưa có bản dịch).
	$notice = q( $x, '//main/div[@data-lang-notice]' );
	check( "{$name}: khung thông báo chuyển ngôn ngữ ẩn sẵn, có role=status, có chữ và nút đóng có tên", 1 === $notice->length && $notice->item( 0 )->hasAttribute( 'hidden' ) && 'status' === $notice->item( 0 )->getAttribute( 'role' ) && '' !== trim( $notice->item( 0 )->textContent ) && 1 === q( $x, '//main/div[@data-lang-notice]/button[@data-lang-notice-close and string-length(@aria-label) > 0]' )->length );

	// SEO: tối đa một canonical và một mô tả; mọi khối JSON-LD là JSON hợp lệ có @context.
	same( "{$name}: tối đa một thẻ canonical", true, q( $x, '//link[@rel="canonical"]' )->length <= 1 );
	same( "{$name}: tối đa một meta description", true, q( $x, '//meta[@name="description"]' )->length <= 1 );
	$bad_ld = 0;
	foreach ( q( $x, '//script[@type="application/ld+json"]' ) as $ld ) {
		$data = json_decode( $ld->textContent, true );
		if ( ! is_array( $data ) || 'https://schema.org' !== ( $data['@context'] ?? '' ) ) {
			++$bad_ld;
		}
	}
	same( "{$name}: mọi khối JSON-LD là JSON hợp lệ", 0, $bad_ld );

	// Tìm kiếm (UC-09): nút ở đầu trang là liên kết dự phòng (không JS vẫn đi được), hộp thoại nằm ngoài <main>.
	$opener = q( $x, '//header//a[@data-vg-search-open]' );
	check( "{$name}: nút tìm kiếm là liên kết có địa chỉ và tên đọc được", 1 === $opener->length && '' !== $opener->item( 0 )->getAttribute( 'href' ) && '' !== $opener->item( 0 )->getAttribute( 'aria-label' ) );
	$dialog = q( $x, '//dialog[@id="search-dialog"]' );
	same( "{$name}: đúng một hộp thoại tìm kiếm", 1, $dialog->length );
	if ( 1 === $dialog->length ) {
		$d = $dialog->item( 0 );
		same( "{$name}: hộp thoại tìm kiếm nằm ngoài <main>", 0, q( $x, '//main//dialog[@id="search-dialog"]' )->length );
		check( "{$name}: hộp thoại có tên (aria-labelledby trỏ tới tiêu đề)", 1 === q( $x, '//*[@id="' . $d->getAttribute( 'aria-labelledby' ) . '" and self::h2]' )->length );
		check( "{$name}: địa chỉ REST và ngôn ngữ", str_contains( $d->getAttribute( 'data-endpoint' ), 'vgarco/v1/search' ) && in_array( $d->getAttribute( 'data-lang' ), array( 'vi', 'de' ), true ) && '3' === $d->getAttribute( 'data-min' ) );
		$missing = array_filter( array( 'hint', 'empty', 'error', 'loading', 'one', 'many' ), static fn( $m ) => '' === $d->getAttribute( 'data-msg-' . $m ) );
		same( "{$name}: đủ thông báo đã dịch cho JS", array(), array_values( $missing ) );
		check( "{$name}: thông báo nhiều kết quả có %d", str_contains( $d->getAttribute( 'data-msg-many' ), '%d' ) );
		check( "{$name}: ô nhập có nhãn, kiểu search, mô tả bằng dòng trạng thái", 1 === q( $x, '//dialog[@id="search-dialog"]//label[@for="search-input"]' )->length && 1 === q( $x, '//dialog[@id="search-dialog"]//input[@id="search-input" and @type="search" and @aria-describedby="search-status"]' )->length );
		check( "{$name}: dòng trạng thái là vùng thông báo (role=status), danh sách kết quả ẩn sẵn", 1 === q( $x, '//dialog[@id="search-dialog"]//*[@id="search-status" and @role="status"]' )->length && 1 === q( $x, '//dialog[@id="search-dialog"]//ul[@id="search-results" and @hidden]' )->length );
		check( "{$name}: nút đóng có tên đọc được", 1 === q( $x, '//dialog[@id="search-dialog"]//button[@data-search-close and string-length(@aria-label) > 0]' )->length );
	}
}

/* ========================= Trang chủ, có nội dung ========================= */

$x = render( 'front-page' );
common_checks( $x, 'trang chủ' );

$slides = q( $x, '//*[' . cls( 'showcase-images' ) . ']/img' );
same( 'hero: 3 ảnh (3 dự án nổi bật)', 3, $slides->length );
check( 'hero: ảnh đầu là ảnh LCP — eager + fetchpriority=high', 3 === $slides->length && 'eager' === $slides->item( 0 )->getAttribute( 'loading' ) && 'high' === $slides->item( 0 )->getAttribute( 'fetchpriority' ) );
check( 'hero: ảnh sau lazy, không fetchpriority', 3 === $slides->length && 'lazy' === $slides->item( 1 )->getAttribute( 'loading' ) && ! $slides->item( 1 )->hasAttribute( 'fetchpriority' ) && 'lazy' === $slides->item( 2 )->getAttribute( 'loading' ) );
same( 'hero: đúng một ảnh đang hiển thị', 1, q( $x, '//*[' . cls( 'showcase-image' ) . ' and ' . cls( 'is-active' ) . ']' )->length );
check( 'hero: ảnh đang hiển thị là ảnh đầu', 3 === $slides->length && str_contains( $slides->item( 0 )->getAttribute( 'class' ), 'is-active' ) );
check( 'hero: alt của ảnh nêu loại ảnh và tên dự án', 3 === $slides->length && str_starts_with( $slides->item( 0 )->getAttribute( 'alt' ), 'Phối cảnh thiết kế — ' ) );
same( 'hero: 3 chú thích', 3, q( $x, '//*[' . cls( 'showcase-caption' ) . ']' )->length );
same( 'hero: đúng một chú thích hiển thị', 1, q( $x, '//*[' . cls( 'showcase-caption' ) . ' and ' . cls( 'is-active' ) . ']' )->length );
check( 'hero: liên kết chú thích trỏ tới dự án', q( $x, '//a[' . cls( 'hero-project-link' ) . ']' )->item( 0 )->getAttribute( 'href' ) === '/du-an/101/' );
check( 'hero: nhãn đường dẫn "Từ bản vẽ" trỏ tới #du-an', 1 === q( $x, '//a[' . cls( 'hero-process-rail' ) . ' and @href="#du-an"]' )->length );

$toggle = q( $x, '//button[' . cls( 'motion-toggle' ) . ']' )->item( 0 );
check( 'nút dừng: có aria-pressed=false ban đầu', null !== $toggle && 'false' === $toggle->getAttribute( 'aria-pressed' ) );
check( 'nút dừng: nhãn dừng/tiếp tục lấy từ data-* (dịch được)', null !== $toggle && '' !== $toggle->getAttribute( 'data-label-pause' ) && '' !== $toggle->getAttribute( 'data-label-play' ) && $toggle->getAttribute( 'aria-label' ) === $toggle->getAttribute( 'data-label-pause' ) );

same( 'dải chữ: 2 nhóm (nhân đôi để chạy liền mạch)', 2, q( $x, '//*[' . cls( 'marquee-group' ) . ']' )->length );
same( 'dải chữ: nhóm nhân đôi ẩn khỏi trình đọc màn hình', 1, q( $x, '//*[' . cls( 'marquee-group' ) . ' and @aria-hidden="true"]' )->length );

same( 'chỉ số: 3 mục', 3, q( $x, '//*[' . cls( 'home-facts' ) . ']/p' )->length );
same( 'chỉ số: mục đầu là 13 và hậu tố +', '13+', trim( q( $x, '//*[' . cls( 'home-facts' ) . ']/p[1]/strong' )->item( 0 )->textContent ) );
same( 'nguyên tắc làm việc: 3 mục', 3, q( $x, '//*[' . cls( 'home-methods' ) . ']/article' )->length );

same( 'dự án tiêu biểu: 3 thẻ', 3, q( $x, '//*[' . cls( 'project-card' ) . ']' )->length );
check( 'dự án tiêu biểu: đúng 3 dự án được đánh dấu (không phải 3 dự án bất kỳ)', array( 'Đài kiểm soát không lưu Đồng Hới', 'Học viện Kỹ thuật và Công nghệ An ninh', 'Bảo tàng Lịch sử Quân đội nhân dân Lào' ) === array_map( static fn( $n ) => $n->textContent, iterator_to_array( q( $x, '//*[' . cls( 'project-card' ) . ']//h3' ) ) ) );
check( 'thẻ dự án: nhãn loại ảnh phân biệt thiết kế và công trình', 'Ảnh công trình' === trim( q( $x, '(//*[' . cls( 'image-label' ) . '])[3]' )->item( 0 )->textContent ) && 'Phối cảnh thiết kế' === trim( q( $x, '(//*[' . cls( 'image-label' ) . '])[1]' )->item( 0 )->textContent ) );
check( 'thẻ dự án: dòng thông tin ghép "năm / loại"', str_contains( q( $x, '(//*[' . cls( 'project-meta' ) . '])[1]' )->item( 0 )->textContent, '2025 / Công nghiệp & hạ tầng' ) );

same( 'chuyên môn: 3 dòng', 3, q( $x, '//*[' . cls( 'service-preview-list' ) . ']/a' )->length );
check( 'chuyên môn: liên kết dẫn tới đúng neo trên trang Lĩnh vực (UC-01 → UC-03)', str_ends_with( q( $x, '//*[' . cls( 'service-preview-list' ) . ']/a' )->item( 0 )->getAttribute( 'href' ), '/linh-vuc-hoat-dong/#thiet-ke-kien-truc' ) );

same( 'đối tác: 2 nhóm × 5 đơn vị', array( 5, 5 ), array_map( static fn( $g ) => q( $x, './/*[' . cls( 'partner-logo' ) . ']' )->length ? $x->query( './/*[' . cls( 'partner-logo' ) . ']', $g )->length : 0, iterator_to_array( q( $x, '//*[' . cls( 'partner-group' ) . ']' ) ) ) );
check( 'đối tác: biến --n khớp số đơn vị', str_contains( q( $x, '//*[' . cls( 'partner-group' ) . ']' )->item( 0 )->getAttribute( 'style' ), '--n: 5' ) );
same( 'đối tác: nhóm nhân đôi ẩn khỏi trình đọc màn hình', 1, q( $x, '//*[' . cls( 'partner-group' ) . ' and @aria-hidden="true"]' )->length );

same( 'tin tức: 3 thẻ mới nhất', 3, q( $x, '//*[' . cls( 'home-news-card' ) . ']' )->length );
check( 'tin tức: mới nhất trước', str_contains( q( $x, '(//*[' . cls( 'home-news-card' ) . '])[1]' )->item( 0 )->getAttribute( 'href' ), '/tin-tuc/' ) );
check( 'CTA: nền đỏ khai báo on-dark để vòng tiêu điểm đổi sang trắng', 1 === q( $x, '//section[' . cls( 'page-cta' ) . ' and ' . cls( 'on-dark' ) . ']' )->length );
check( 'CTA: hai liên kết tới trang Liên hệ', 2 === q( $x, '//section[' . cls( 'page-cta' ) . ']//a[@href="/lien-he/"]' )->length );

// Ngôn ngữ: bản tiếng Đức chưa có bản dịch thì hiện chuỗi gốc (không rỗng, không lỗi).
$de = render( 'front-page', false, 'de' );
common_checks( $de, 'trang chủ tiếng Đức' );
same( 'trang chủ tiếng Đức: hộp thoại tìm kiếm gửi lang=de', 'de', q( $de, '//dialog[@id="search-dialog"]' )->item( 0 )->getAttribute( 'data-lang' ) );
check( 'trang chủ tiếng Đức: vẫn đủ nội dung (chuỗi chưa dịch giữ tiếng Việt)', 1 === q( $de, '//h1' )->length && q( $de, '//h1' )->item( 0 )->textContent !== '' );

/* ========================= Trang chủ, website mới cài ========================= */

$e = render( 'front-page', true );
common_checks( $e, 'trang chủ rỗng' );
same( 'rỗng: không có khung ảnh hero (UC-01 3c)', 0, q( $e, '//*[' . cls( 'hero-frame' ) . ']' )->length );
same( 'rỗng: ẩn khối dự án tiêu biểu', 0, q( $e, '//*[' . cls( 'projects-section' ) . ']' )->length );
same( 'rỗng: ẩn khối chuyên môn', 0, q( $e, '//*[' . cls( 'home-services' ) . ']' )->length );
same( 'rỗng: ẩn khối đối tác', 0, q( $e, '//*[' . cls( 'partners-strip' ) . ']' )->length );
same( 'rỗng: ẩn khối tin tức (UC-01 8a)', 0, q( $e, '//*[' . cls( 'home-news' ) . ']' )->length );
check( 'rỗng: các khối còn lại vẫn có (giới thiệu, dải chữ, CTA)', 1 === q( $e, '//*[' . cls( 'home-about' ) . ']' )->length && 1 === q( $e, '//*[' . cls( 'marquee' ) . ']' )->length && 1 === q( $e, '//*[' . cls( 'page-cta' ) . ']' )->length );
check( 'rỗng: chỉ số dùng mặc định trong mã (13+, 03, 06)', 3 === q( $e, '//*[' . cls( 'home-facts' ) . ']/p' )->length );

/* ========================= Danh mục dự án (UC-04) ========================= */

$c = render( 'archive-du_an' );
common_checks( $c, 'danh mục' );
same( 'danh mục: 19 thẻ dự án', 19, q( $c, '//*[' . cls( 'catalogue-grid' ) . ']/article' )->length );
same( 'danh mục: lưới không ở chế độ lọc', 0, q( $c, '//*[' . cls( 'catalogue-grid' ) . ' and ' . cls( 'is-filtered' ) . ']' )->length );
same( 'danh mục: tiêu đề "Tất cả dự án"', 'Tất cả dự án', trim( q( $c, '//h1' )->item( 0 )->textContent ) );
check( 'danh mục: số lượng "19 dự án"', 1 === q( $c, '//*[' . cls( 'project-head-meta' ) . ']//span[contains(., "19 dự án")]' )->length );
same( 'danh mục: đường dẫn vị trí Trang chủ / Dự án', array( 'Trang chủ', 'Dự án' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $c, '//nav[' . cls( 'breadcrumb' ) . ']//li' ) ) ) );
same( 'danh mục: mục cuối của đường dẫn có aria-current và không có liên kết', 1, q( $c, '//nav[' . cls( 'breadcrumb' ) . ']//li[@aria-current="page"][not(.//a)]' )->length );
same( 'danh mục: vòng tròn mũi tên ở mọi thẻ là trang trí, ẩn khỏi trình đọc màn hình', 19, q( $c, '//*[' . cls( 'circle-arrow' ) . ' and @aria-hidden="true"]' )->length );
same( 'danh mục: nhãn loại ảnh ở mọi thẻ', 19, q( $c, '//*[' . cls( 'catalogue-grid' ) . ']//*[' . cls( 'image-label' ) . ']' )->length );
same( 'danh mục: thẻ không có địa điểm và năm chỉ có một ô meta (không ô trống) — "Nhà Đại sứ"', 1, q( $c, '//article[.//h3[.="Nhà Đại sứ"]]//*[' . cls( 'project-meta' ) . ']/span' )->length );
same( 'danh mục: thứ tự theo "Thứ tự" chủ đầu tư đặt — thẻ đầu là Nhà Đại sứ', 'Nhà Đại sứ', trim( q( $c, '(//*[' . cls( 'catalogue-grid' ) . ']//h3)[1]' )->item( 0 )->textContent ) );
same( 'danh mục: mọi thẻ liên kết tới trang dự án', 19, q( $c, '//*[' . cls( 'catalogue-grid' ) . ']//a[starts-with(@href, "/du-an/")]' )->length );
same( 'danh mục: trang thuộc module "projects" (data-vg-page)', 1, q( $c, '//body[@data-vg-page="projects"]' )->length );

same( 'câu chuyện: có đúng một khối', 1, q( $c, '//section[' . cls( 'project-story' ) . ']' )->length );
same( 'câu chuyện: nền đỏ khai báo on-dark (vòng tiêu điểm trắng)', 1, q( $c, '//section[' . cls( 'project-story' ) . ' and ' . cls( 'on-dark' ) . ']' )->length );
same( 'câu chuyện: tiêu đề h2 có ngắt dòng và cụm nhấn <em>', 1, q( $c, '//section[' . cls( 'project-story' ) . ']//h2[count(br)=1 and count(em)=1]' )->length );
same( 'câu chuyện: hai ảnh (phối cảnh và công trình)', 2, q( $c, '//img[' . cls( 'story-image' ) . ']' )->length );
same( 'câu chuyện: chỉ ảnh phối cảnh đang hiển thị', 1, q( $c, '//img[' . cls( 'story-image' ) . ' and ' . cls( 'is-active' ) . ']' )->length );
same( 'câu chuyện: ảnh còn lại ẩn khỏi trình đọc màn hình', 1, q( $c, '//img[' . cls( 'story-image' ) . ' and not(' . cls( 'is-active' ) . ') and @aria-hidden="true"]' )->length );
same( 'câu chuyện: hai nút chuyển, nút đầu đang nhấn', array( 'true', 'false' ), array_map( static fn( $b ) => $b->getAttribute( 'aria-pressed' ), iterator_to_array( q( $c, '//*[' . cls( 'story-switch' ) . ']/button' ) ) ) );
same( 'câu chuyện: nút chuyển mang nhãn chú thích (dịch được) trong data-caption', 2, q( $c, '//*[' . cls( 'story-switch' ) . ']/button[@data-caption != ""]' )->length );
same( 'câu chuyện: liên kết "Khám phá" trỏ tới trang dự án được chọn (Bảo tàng)', 1, q( $c, '//section[' . cls( 'project-story' ) . ']//a[' . cls( 'story-link' ) . ' and @href="/du-an/106/"]' )->length );
check( 'câu chuyện: dòng nhãn ghép "Câu chuyện một công trình / năm"', str_contains( q( $c, '//section[' . cls( 'project-story' ) . ']//p[' . cls( 'eyebrow' ) . ']' )->item( 0 )->textContent, 'Câu chuyện một công trình / 2019–2020' ) );

// Thiếu ảnh công trình: chỉ còn ảnh phối cảnh, không có nút chuyển (UC-04 6b).
$nb = render( 'archive-du_an', false, 'vi', array( 'VG_FX_NO_BUILT' => '1' ) );
same( 'câu chuyện thiếu ảnh công trình: một ảnh', 1, q( $nb, '//img[' . cls( 'story-image' ) . ']' )->length );
same( 'câu chuyện thiếu ảnh công trình: ẩn nút chuyển', 0, q( $nb, '//*[' . cls( 'story-switch' ) . ']' )->length );

// Dự án được chọn cho khối câu chuyện đã gỡ về bản nháp: khối tự ẩn, không lỗi, không lộ dự án chưa xuất bản.
$dr = render( 'archive-du_an', false, 'vi', array( 'VG_FX_STORY_DRAFT' => '1' ) );
same( 'câu chuyện: dự án chưa xuất bản thì ẩn cả khối', 0, q( $dr, '//section[' . cls( 'project-story' ) . ']' )->length );

// Một loại công trình: lưới đều, không có khối câu chuyện, đường dẫn 3 cấp.
$f = render( 'taxonomy-loai_cong_trinh', false, 'vi', array( 'VG_FX_TERM' => 'giao-duc' ) );
common_checks( $f, 'danh mục theo loại' );
same( 'theo loại: 7 thẻ (chỉ các dự án của loại đó)', 7, q( $f, '//*[' . cls( 'catalogue-grid' ) . ']/article' )->length );
same( 'theo loại: lưới ở chế độ lọc (đều, không có dòng danh sách)', 1, q( $f, '//*[' . cls( 'catalogue-grid' ) . ' and ' . cls( 'is-filtered' ) . ']' )->length );
same( 'theo loại: tiêu đề là tên loại', 'Giáo dục & đào tạo', trim( q( $f, '//h1' )->item( 0 )->textContent ) );
same( 'theo loại: số lượng có số 0 đứng đầu ("07 dự án")', 1, q( $f, '//*[' . cls( 'project-head-meta' ) . ']//span[contains(., "07 dự án")]' )->length );
same( 'theo loại: không có khối câu chuyện', 0, q( $f, '//section[' . cls( 'project-story' ) . ']' )->length );
same( 'theo loại: đường dẫn 3 cấp', array( 'Trang chủ', 'Dự án', 'Giáo dục & đào tạo' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $f, '//nav[' . cls( 'breadcrumb' ) . ']//li' ) ) ) );
same( 'theo loại: cấp giữa của đường dẫn là liên kết về /du-an/', 1, q( $f, '//nav[' . cls( 'breadcrumb' ) . ']//a[@href="/du-an/"]' )->length );
same( 'theo loại: mọi thẻ đều thuộc loại giáo dục (dòng meta)', 7, q( $f, '//*[' . cls( 'catalogue-grid' ) . ']//*[' . cls( 'project-meta' ) . ']/span[contains(., "Giáo dục & đào tạo")]' )->length );

// Loại chưa có dự án nào: thông báo và đường về danh mục (UC-04 5b), không lưới trống, không số lượng.
$z = render( 'taxonomy-loai_cong_trinh', false, 'vi', array( 'VG_FX_TERM' => 'y-te' ) );
common_checks( $z, 'loại rỗng' );
same( 'loại rỗng: không có lưới', 0, q( $z, '//*[' . cls( 'catalogue-grid' ) . ']' )->length );
same( 'loại rỗng: có thông báo', 1, q( $z, '//*[' . cls( 'catalogue-empty' ) . ']' )->length );
same( 'loại rỗng: liên kết "Xem tất cả dự án" về /du-an/', 1, q( $z, '//*[' . cls( 'catalogue-empty' ) . ']//a[@href="/du-an/"]' )->length );
same( 'loại rỗng: không hiện số lượng "0 dự án"', 0, q( $z, '//*[' . cls( 'project-head-meta' ) . ']//span' )->length );

// Website mới cài: không có dự án — trang vẫn dùng được, không có khối câu chuyện.
$ce = render( 'archive-du_an', true );
common_checks( $ce, 'danh mục rỗng' );
same( 'danh mục rỗng: có thông báo, không có lưới', array( 1, 0 ), array( q( $ce, '//*[' . cls( 'catalogue-empty' ) . ']' )->length, q( $ce, '//*[' . cls( 'catalogue-grid' ) . ']' )->length ) );
same( 'danh mục rỗng: không có liên kết "Xem tất cả" (đang ở trang tất cả)', 0, q( $ce, '//*[' . cls( 'catalogue-empty' ) . ']//a' )->length );
same( 'danh mục rỗng: không có khối câu chuyện', 0, q( $ce, '//section[' . cls( 'project-story' ) . ']' )->length );

/* ========================= Chi tiết dự án (UC-05) ========================= */

// 106 = Bảo tàng: đủ thông tin, hai ảnh thư viện, vị trí thứ 6/19.
$d = render( 'single-du_an', false, 'vi', array( 'VG_FX_ID' => '106' ) );
common_checks( $d, 'chi tiết dự án' );
same( 'chi tiết: h1 là tên dự án', 'Bảo tàng Lịch sử Quân đội nhân dân Lào', trim( q( $d, '//h1' )->item( 0 )->textContent ) );
check( 'chi tiết: nhãn loại công trình là liên kết tới trang loại', 1 === q( $d, '//*[' . cls( 'project-detail-head' ) . ']//a[' . cls( 'eyebrow' ) . ' and starts-with(@href, "/du-an/loai/")]' )->length );
same( 'chi tiết: đường dẫn 3 cấp', array( 'Trang chủ', 'Dự án', 'Bảo tàng Lịch sử Quân đội nhân dân Lào' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d, '//nav[' . cls( 'breadcrumb' ) . ']//li' ) ) ) );
check( 'chi tiết: trang thuộc module "project" (data-vg-page)', 1 === q( $d, '//body[@data-vg-page="project"]' )->length );

$cover = q( $d, '//figure[' . cls( 'project-detail-cover' ) . ']/img' )->item( 0 );
check( 'ảnh bìa: là ảnh LCP — eager + fetchpriority=high', null !== $cover && 'eager' === $cover->getAttribute( 'loading' ) && 'high' === $cover->getAttribute( 'fetchpriority' ) );
check( 'ảnh bìa: alt nêu loại ảnh và tên dự án', null !== $cover && 'Ảnh công trình — Bảo tàng Lịch sử Quân đội nhân dân Lào' === $cover->getAttribute( 'alt' ) );
same( 'ảnh bìa: chú thích gồm loại ảnh và địa điểm', array( 'Ảnh công trình', 'Viêng Chăn, Lào' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d, '//figure[' . cls( 'project-detail-cover' ) . ']/figcaption/span' ) ) ) );
check( 'vị trí: đọc được cho trình đọc màn hình, số hiển thị thì ẩn', str_contains( q( $d, '//p[' . cls( 'project-detail-index' ) . ']/span[' . cls( 'sr-only' ) . ']' )->item( 0 )->textContent, 'Dự án thứ 6 trong 19 dự án' ) && 2 === q( $d, '//p[' . cls( 'project-detail-index' ) . ']/*[@aria-hidden="true"]' )->length );
same( 'vị trí: "06" và "/ 19"', array( '06', '/ 19' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d, '//p[' . cls( 'project-detail-index' ) . ']/*[@aria-hidden="true"]' ) ) ) );

same( 'thông tin: đúng các dòng có dữ liệu (không ô trống)', array( 'Địa điểm', 'Thời gian', 'Vai trò VGARCO' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d, '//aside//dt' ) ) ) );
same( 'thông tin: mỗi tên có một giá trị, không rỗng', array( 'Viêng Chăn, Lào', '2019–2020', 'Tư vấn thiết kế cải tạo' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d, '//aside//dd' ) ) ) );
check( 'mô tả: nội dung biên tập nằm trong .entry-content', 1 === q( $d, '//*[' . cls( 'project-detail-copy' ) . ']/div[' . cls( 'entry-content' ) . ']/p' )->length );
same( 'mô tả: h2 có ngắt dòng và cụm nhấn', 1, q( $d, '//*[' . cls( 'project-detail-copy' ) . ']/h2[count(br)=1 and count(em)=1]' )->length );
same( 'phạm vi tham gia: có khối khi có dữ liệu', 'Tư vấn thiết kế cải tạo', trim( q( $d, '//*[' . cls( 'project-scope' ) . ']/strong' )->item( 0 )->textContent ) );

$g = q( $d, '//section[' . cls( 'project-detail-gallery' ) . ']' );
same( 'thư viện: có đúng một khối', 1, $g->length );
$slides = q( $d, '//figure[@data-gallery-slide]' );
same( 'thư viện: 2 ảnh (ngoài ảnh bìa)', 2, $slides->length );
check( 'thư viện: chỉ ảnh đầu hiển thị, ảnh sau có hidden', 2 === $slides->length && ! $slides->item( 0 )->hasAttribute( 'hidden' ) && $slides->item( 1 )->hasAttribute( 'hidden' ) );
same( 'thư viện: đúng một ảnh đang chọn', 1, q( $d, '//figure[' . cls( 'gallery-slide' ) . ' and ' . cls( 'is-active' ) . ']' )->length );
same( 'thư viện: ảnh lazy (ảnh cuối trang, không tranh băng thông với ảnh bìa)', 2, q( $d, '//figure[@data-gallery-slide]/img[@loading="lazy"]' )->length );
check( 'thư viện: alt là chú thích + tên dự án', 'Ảnh công trình — Bảo tàng Lịch sử Quân đội nhân dân Lào' === q( $d, '(//figure[@data-gallery-slide]/img)[1]' )->item( 0 )->getAttribute( 'alt' ) );
same( 'thư viện: mỗi ảnh mang địa chỉ ảnh lớn cho phóng to', 2, q( $d, '//figure[@data-gallery-slide and string-length(@data-full) > 0]' )->length );
same( 'thư viện: nút chuyển ẩn cho tới khi JS sẵn sàng (không có JS thì không có nút chết)', array( 1, 1 ), array( q( $d, '//*[@data-gallery-controls and @hidden]' )->length, q( $d, '//button[@data-gallery-open and @hidden]' )->length ) );
same( 'thư viện: 2 chấm tiến độ, chấm đầu đang nhấn', array( 'true', 'false' ), array_map( static fn( $b ) => $b->getAttribute( 'aria-pressed' ), iterator_to_array( q( $d, '//button[@data-gallery-dot]' ) ) ) );
same( 'thư viện: ảnh nhỏ là liên kết thật tới tệp ảnh (dùng được khi không có JS)', 2, q( $d, '//ul[' . cls( 'project-gallery-thumbs' ) . ']//a[starts-with(@href, "/img/")]' )->length );
check( 'thư viện: ảnh nhỏ đầu có aria-current, ảnh sau thì không', 1 === q( $d, '//a[@data-gallery-thumb and @aria-current="true"]' )->length && '0' === q( $d, '//a[@data-gallery-thumb and @aria-current="true"]' )->item( 0 )->getAttribute( 'data-gallery-thumb' ) );
same( 'thư viện: ảnh nhỏ có alt rỗng (nhãn nằm ở chữ bên cạnh, tránh đọc hai lần)', 2, q( $d, '//a[@data-gallery-thumb]/img[@alt=""]' )->length );
check( 'thư viện: khung trình chiếu focus được bằng bàn phím và có nhãn', 1 === q( $d, '//*[@data-gallery-stage and @tabindex="0" and @aria-label != ""]' )->length );
same( 'thư viện: có vùng thông báo cho trình đọc màn hình khi đổi ảnh', 1, q( $d, '//*[@data-gallery-status and @aria-live="polite"]' )->length );
check( 'thư viện: định dạng thông báo lấy từ thuộc tính (dịch được)', str_contains( q( $d, '//section[@data-gallery]' )->item( 0 )->getAttribute( 'data-status' ), '%1$d' ) );
check( 'thư viện: hộp xem ảnh lớn là <dialog> có nhãn; mọi nút trong đó có tên (đóng, trước, sau)', 1 === q( $d, '//dialog[@data-gallery-lightbox and @aria-label != ""]' )->length && 3 === q( $d, '//dialog[@data-gallery-lightbox]//button[@aria-label != ""]' )->length && 0 === q( $d, '//dialog[@data-gallery-lightbox]//button[not(@aria-label) or @aria-label = ""]' )->length );
check( 'thư viện: không có thuộc tính tự chạy (WCAG 2.2.2)', 0 === q( $d, '//*[@data-autoplay or @data-interval]' )->length );

$n = q( $d, '//section[' . cls( 'project-detail-next' ) . ']' );
same( 'dự án tiếp theo: có khối, nền tối khai báo on-dark', 1, q( $d, '//section[' . cls( 'project-detail-next' ) . ' and ' . cls( 'on-dark' ) . ']' )->length );
check( 'dự án tiếp theo: dự án kế trong danh mục (Biệt thự Niedernhausen)', 1 === q( $d, '//a[' . cls( 'project-next-link' ) . ' and @href="/du-an/107/"]' )->length );
same( 'dự án tiếp theo: dòng meta gồm loại và địa điểm', array( 'Nhà ở & công trình dân dụng', 'Niedernhausen, CHLB Đức' ), array_map( static fn( $x ) => trim( $x->textContent ), iterator_to_array( q( $d, '//*[' . cls( 'project-next-meta' ) . ']/span' ) ) ) );

// 119 = dự án cuối danh mục: "tiếp theo" quay về dự án đầu (UC-05 8a).
$last = render( 'single-du_an', false, 'vi', array( 'VG_FX_ID' => '119' ) );
check( 'dự án cuối: "tiếp theo" quay về dự án đầu tiên', 1 === q( $last, '//a[' . cls( 'project-next-link' ) . ' and @href="/du-an/101/"]' )->length );
check( 'dự án cuối: vị trí 19 / 19', str_contains( q( $last, '//p[' . cls( 'project-detail-index' ) . ']/span[' . cls( 'sr-only' ) . ']' )->item( 0 )->textContent, 'Dự án thứ 19 trong 19 dự án' ) );

// 104 = Đài kiểm soát không lưu: đủ chủ đầu tư/đối tác, không có ảnh thư viện.
$t = render( 'single-du_an', false, 'vi', array( 'VG_FX_ID' => '104' ) );
common_checks( $t, 'chi tiết không thư viện' );
same( 'không thư viện: ẩn cả khối (UC-05 4a)', 0, q( $t, '//section[' . cls( 'project-detail-gallery' ) . ']' )->length );
same( 'công bố chủ đầu tư: hiện đủ 5 dòng thông tin', array( 'Địa điểm', 'Thời gian', 'Chủ đầu tư', 'Đối tác', 'Vai trò VGARCO' ), array_map( static fn( $x ) => trim( $x->textContent ), iterator_to_array( q( $t, '//aside//dt' ) ) ) );
$np = render( 'single-du_an', false, 'vi', array( 'VG_FX_ID' => '104', 'VG_FX_HIDE_PARTIES' => '1' ) );
same( 'chưa công bố chủ đầu tư: ẩn Chủ đầu tư và Đối tác (mục 3.2 #8)', array( 'Địa điểm', 'Thời gian', 'Vai trò VGARCO' ), array_map( static fn( $x ) => trim( $x->textContent ), iterator_to_array( q( $np, '//aside//dt' ) ) ) );
check( 'chưa công bố chủ đầu tư: tên đơn vị không lọt vào HTML ở bất kỳ chỗ nào', ! str_contains( (string) file_get_contents( ROOT . '/tests/out/single-du_an-104.html' ), 'Quản lý bay Miền Bắc' ) );

// 109 = Dự án Bản Còn: không có địa điểm, năm, chủ đầu tư, vai trò → không có cột thông tin, không có phạm vi.
$b = render( 'single-du_an', false, 'vi', array( 'VG_FX_ID' => '109' ) );
common_checks( $b, 'chi tiết thiếu thông tin' );
same( 'thiếu thông tin: không có cột thông tin dự án', 0, q( $b, '//aside' )->length );
same( 'thiếu thông tin: không có khối phạm vi tham gia', 0, q( $b, '//*[' . cls( 'project-scope' ) . ']' )->length );
same( 'thiếu thông tin: vẫn có phần mô tả', 1, q( $b, '//*[' . cls( 'project-detail-copy' ) . ']/div[' . cls( 'entry-content' ) . ']' )->length );
same( 'thiếu thông tin: ảnh bìa không có địa điểm thì chú thích dùng tên loại công trình', 'Văn hóa & di tích', trim( q( $b, '//figure[' . cls( 'project-detail-cover' ) . ']/figcaption/span[2]' )->item( 0 )->textContent ) );

// 111 = Cục Biên phòng: chỉ một ảnh thư viện → không có nút chuyển, số đếm, ảnh nhỏ; vẫn phóng to được.
$one = render( 'single-du_an', false, 'vi', array( 'VG_FX_ID' => '111' ) );
common_checks( $one, 'chi tiết một ảnh' );
same( 'một ảnh: đúng 1 ảnh trong thư viện', 1, q( $one, '//figure[@data-gallery-slide]' )->length );
same( 'một ảnh: không có nút trước/sau, chấm, số đếm, ảnh nhỏ', array( 0, 0, 0, 0 ), array( q( $one, '//*[@data-gallery-controls]' )->length, q( $one, '//button[@data-gallery-dot]' )->length, q( $one, '//*[' . cls( 'gallery-head-meta' ) . ']' )->length, q( $one, '//ul[' . cls( 'project-gallery-thumbs' ) . ']' )->length ) );
same( 'một ảnh: vẫn có nút phóng to và hộp xem ảnh lớn', array( 1, 1 ), array( q( $one, '//button[@data-gallery-open]' )->length, q( $one, '//dialog[@data-gallery-lightbox]' )->length ) );
same( 'một ảnh: hộp xem ảnh lớn không có nút trước/sau', 0, q( $one, '//dialog//button[@data-lightbox-prev or @data-lightbox-next]' )->length );
same( 'một ảnh: chú thích không có số thứ tự "01"', 0, q( $one, '//*[' . cls( 'gallery-caption-index' ) . ']' )->length );

/* ========================= Về công ty (UC-02) ========================= */

// Cài mới: chưa nhập gì — chữ mặc định đủ, ảnh và khối ghi nhận ẩn.
$a = render( 'templates/about', false, 'vi', array( 'VG_FX_ID' => '501' ) );
common_checks( $a, 'về công ty' );
check( 'về công ty: trang thuộc module "about" (data-vg-page)', 1 === q( $a, '//body[@data-vg-page="about"]' )->length );
same( 'về công ty: đường dẫn Trang chủ / Về công ty', array( 'Trang chủ', 'Về công ty' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $a, '//nav[' . cls( 'breadcrumb' ) . ']//li' ) ) ) );
same( 'về công ty: h1 có ngắt dòng và cụm nhấn', 1, q( $a, '//h1[count(br)=1 and count(em)=1]' )->length );
same( 'về công ty: nhãn đầu trang mặc định', 'Về công ty / Est. 2013', trim( q( $a, '//*[' . cls( 'about-masthead' ) . ']/p[' . cls( 'eyebrow' ) . ']' )->item( 0 )->textContent ) );
check( 'về công ty: nút cuộn xuống có tên và trỏ tới "Chúng tôi là ai"', 1 === q( $a, '//a[' . cls( 'about-scroll' ) . ' and @href="#cau-chuyen-cong-ty" and @aria-label != ""]' )->length && 1 === q( $a, '//section[@id="cau-chuyen-cong-ty"]' )->length );
same( 'về công ty: chưa chọn ảnh bìa thì ẩn khối ảnh bìa', 0, q( $a, '//figure[' . cls( 'about-cover' ) . ']' )->length );
$story_text = q( $a, '//section[' . cls( 'about-story' ) . ']' )->item( 0 )->textContent;
check( 'về công ty: nêu đúng ngày thành lập 19/08/2013 và tên cũ 3SEVEN, đổi tên 05/12/2023', str_contains( $story_text, '19/08/2013' ) && str_contains( $story_text, '3SEVEN' ) && str_contains( $story_text, '05/12/2023' ) );
same( 'về công ty: 4 chỉ số, số đầu là "13+"', array( 4, '13+' ), array( q( $a, '//section[' . cls( 'about-metrics' ) . ']/div' )->length, trim( q( $a, '//section[' . cls( 'about-metrics' ) . ']/div[1]/strong' )->item( 0 )->textContent ) ) );
same( 'về công ty: 4 mốc thời gian', 4, q( $a, '//ol[' . cls( 'timeline' ) . ']/li' )->length );
same( 'về công ty: mốc có ngày thật dùng thẻ <time datetime>', array( '2013-08-19', '2023-12-05' ), array_map( static fn( $n ) => $n->getAttribute( 'datetime' ), iterator_to_array( q( $a, '//ol[' . cls( 'timeline' ) . ']//time' ) ) ) );
same( 'về công ty: mốc có địa điểm dùng <span> (không giả làm ngày)', 2, q( $a, '//ol[' . cls( 'timeline' ) . ']//span[' . cls( 'timeline-meta' ) . ']' )->length );
check( 'về công ty: khối hành trình có nhãn (aria-labelledby trỏ tới tiêu đề)', 1 === q( $a, '//section[' . cls( 'about-timeline' ) . ' and @aria-labelledby="timeline-title"]//h2[@id="timeline-title"]' )->length && 1 === q( $a, '//section[' . cls( 'about-timeline' ) . ' and ' . cls( 'on-dark' ) . ']' )->length );
same( 'về công ty: 3 nguyên tắc, số thứ tự ẩn khỏi trình đọc màn hình', array( 3, 3 ), array( q( $a, '//div[' . cls( 'approach-list' ) . ']/article' )->length, q( $a, '//div[' . cls( 'approach-list' ) . ']/article/span[@aria-hidden="true"]' )->length ) );
same( 'về công ty: 3 quốc gia', array( 'Việt Nam', 'Lào', 'CHLB Đức' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $a, '//ul[' . cls( 'reach-countries' ) . ']/li' ) ) ) );
same( 'về công ty: chưa nhập thì ẩn khối "Sự ghi nhận" (UC-02 2c)', 0, q( $a, '//section[' . cls( 'recognition' ) . ']' )->length );
same( 'về công ty: không ảnh nào trên trang khi chưa chọn ảnh', 0, q( $a, '//main//img' )->length );

// Biên tập viên đã chọn ảnh và nhập khối ghi nhận.
$f2 = render( 'templates/about', false, 'vi', array( 'VG_FX_ID' => '501', 'VG_FX_PAGE_FULL' => '1' ) );
common_checks( $f2, 'về công ty đủ ảnh' );
$cover2 = q( $f2, '//figure[' . cls( 'about-cover' ) . ']/img' )->item( 0 );
check( 'ảnh bìa: là ảnh LCP — eager + fetchpriority=high', null !== $cover2 && 'eager' === $cover2->getAttribute( 'loading' ) && 'high' === $cover2->getAttribute( 'fetchpriority' ) );
check( 'ảnh bìa: alt là tên công trình', null !== $cover2 && 'Trường THCS chất lượng cao Bắc Từ Liêm' === $cover2->getAttribute( 'alt' ) );
same( 'ảnh bìa: năm ở góc "2013" và nhãn "Năm khởi đầu"', array( '2013', 'Năm khởi đầu' ), array( trim( q( $f2, '//*[' . cls( 'cover-year' ) . ']/strong' )->item( 0 )->textContent ), trim( q( $f2, '//*[' . cls( 'cover-year' ) . ']/span' )->item( 0 )->textContent ) ) );
same( 'ảnh bìa: chú thích 2 phần', 2, q( $f2, '//figure[' . cls( 'about-cover' ) . ']/figcaption/span' )->length );
same( 'cách làm việc: ảnh lazy có alt = chú thích', array( 'lazy', 'Niedernhausen, Đức / Phối cảnh thiết kế' ), array( q( $f2, '//section[' . cls( 'about-approach' ) . ']//img' )->item( 0 )->getAttribute( 'loading' ), q( $f2, '//section[' . cls( 'about-approach' ) . ']//img' )->item( 0 )->getAttribute( 'alt' ) ) );
same( 'ghi nhận: có khối, tiêu đề nhiều dòng', 1, q( $f2, '//section[@id="ghi-nhan"]//h2[count(br)=1]' )->length );
check( 'ghi nhận: nút "Xem tư liệu ghi nhận" dẫn tới bài viết đã chọn (UC-02 2b)', 1 === q( $f2, '//section[@id="ghi-nhan"]//a[' . cls( 'text-link' ) . ' and @href="/tin-tuc/301/"]' )->length );
check( 'ghi nhận: ảnh tư liệu có mô tả', str_contains( q( $f2, '//section[@id="ghi-nhan"]//img' )->item( 0 )->getAttribute( 'alt' ), 'Bằng khen' ) );
check( 'ghi nhận: khối gắn nhãn bằng tiêu đề của nó', 1 === q( $f2, '//section[@id="ghi-nhan" and @aria-labelledby="recognition-title"]//h2[@id="recognition-title"]' )->length );

$f3 = render( 'templates/about', false, 'vi', array( 'VG_FX_ID' => '501', 'VG_FX_PAGE_FULL' => '1', 'VG_FX_NO_AWARD_POST' => '1' ) );
same( 'ghi nhận: không chọn bài viết thì ẩn nút nhưng giữ khối', array( 1, 0 ), array( q( $f3, '//section[@id="ghi-nhan"]' )->length, q( $f3, '//section[@id="ghi-nhan"]//a' )->length ) );

// Biên tập viên ghi đè chữ: bản nhập luôn thắng bản mặc định; dòng trống bị bỏ; nội dung độc hại bị chặn.
$o = render( 'templates/about', false, 'vi', array( 'VG_FX_ID' => '501', 'VG_FX_PAGE_OVERRIDE' => '1' ) );
check( 'ghi đè: tiêu đề do biên tập viên nhập thắng bản mặc định, giữ cụm nhấn', str_contains( trim( q( $o, '//h1' )->item( 0 )->textContent ), 'Tiêu đề do biên tập viên nhập.' ) && 1 === q( $o, '//h1/em' )->length );
same( 'ghi đè: dòng lặp trống hoàn toàn bị bỏ — chỉ còn 1 mốc (không lẫn 4 mốc mặc định)', 1, q( $o, '//ol[' . cls( 'timeline' ) . ']/li' )->length );
same( 'ghi đè: danh sách quốc gia bỏ dòng trống và dòng chỉ có khoảng trắng', array( 'Việt Nam', 'Lào', 'Đức', 'Áo' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $o, '//ul[' . cls( 'reach-countries' ) . ']/li' ) ) ) );
same( 'an toàn: <img onerror> trong tiêu đề bị loại', 0, q( $o, '//img[@onerror]' )->length );
same( 'an toàn: <script> trong ô chữ bị thoát, không thành thẻ script trong trang', 0, q( $o, '//main//script' )->length );
check( 'an toàn: chữ "<b>đậm</b>" trong ô chữ hiển thị nguyên văn, không thành thẻ', 0 === q( $o, '//main//b' )->length && str_contains( q( $o, '//*[' . cls( 'masthead-bottom' ) . ']/p[1]' )->item( 0 )->textContent, '<b>đậm</b>' ) );

/* ========================= Ban lãnh đạo, Phòng ban, Lĩnh vực, Liên hệ ========================= */

// --- Ban lãnh đạo ---
$l = render( 'templates/leadership', false, 'vi', array( 'VG_FX_ID' => '502' ) );
common_checks( $l, 'ban lãnh đạo' );
same( 'lãnh đạo: h1 chỉ dành cho trình đọc màn hình ("Ban lãnh đạo")', array( 'Ban lãnh đạo', 1 ), array( trim( q( $l, '//h1' )->item( 0 )->textContent ), q( $l, '//h1[' . cls( 'sr-only' ) . ']' )->length ) );
same( 'lãnh đạo: một thành viên mặc định, tên ở h2', array( 1, 'Vũ Duy Hùng' ), array( q( $l, '//section[' . cls( 'leadership-content' ) . ']' )->length, trim( q( $l, '//section[' . cls( 'leadership-content' ) . ']//h2' )->item( 0 )->textContent ) ) );
same( 'lãnh đạo: chưa có ảnh thì hiện chữ cái đầu "VH", ẩn khỏi trình đọc màn hình', array( 'VH', 'true' ), array( substr( trim( q( $l, '//div[' . cls( 'leader-monogram' ) . ']' )->item( 0 )->textContent ), 0, 2 ), q( $l, '//div[' . cls( 'leader-monogram' ) . ']' )->item( 0 )->getAttribute( 'aria-hidden' ) ) );
check( 'lãnh đạo: đơn vị lấy từ Cấu hình (tên công ty)', 'Công ty TNHH Tư vấn Xây dựng VGARCO' === trim( q( $l, '//p[' . cls( 'leadership-company' ) . ']' )->item( 0 )->textContent ) );
same( 'lãnh đạo: có vai trò và trọng tâm điều hành', array( 1, 1 ), array( q( $l, '//p[' . cls( 'leadership-note' ) . ']' )->length, q( $l, '//div[' . cls( 'leader-statement' ) . ']' )->length ) );
same( 'lãnh đạo: 4 nguyên tắc đánh số 01–04, số ẩn khỏi trình đọc màn hình', array( '01', '02', '03', '04' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $l, '//div[' . cls( 'leadership-principle-grid' ) . ']/article/span[@aria-hidden="true"]' ) ) ) );

$lo = render( 'templates/leadership', false, 'vi', array( 'VG_FX_ID' => '502', 'VG_FX_PAGE_OVERRIDE' => '1', 'VG_FX_PAGE_FULL' => '1' ) );
common_checks( $lo, 'ban lãnh đạo nhiều thành viên' );
same( 'lãnh đạo: nhiều thành viên đúng thứ tự đã nhập (UC-02 4a)', array( 'Nguyễn Văn An', 'Trần Thị <b>Bình</b>' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $lo, '//section[' . cls( 'leadership-content' ) . ']//h2' ) ) ) );
same( 'lãnh đạo: có ảnh thì dùng ảnh (lazy, alt là chức danh + tên), không có chữ cái đầu', array( 1, 1, 'lazy' ), array( q( $lo, '//figure[' . cls( 'leader-photo' ) . ']/img' )->length, q( $lo, '//div[' . cls( 'leader-monogram' ) . ']' )->length, q( $lo, '//figure[' . cls( 'leader-photo' ) . ']/img' )->item( 0 )->getAttribute( 'loading' ) ) );
same( 'lãnh đạo: đơn vị nhập riêng thắng tên công ty', 'Đơn vị khác', trim( q( $lo, '(//p[' . cls( 'leadership-company' ) . '])[2]' )->item( 0 )->textContent ) );
same( 'lãnh đạo: thành viên không có vai trò/trọng tâm thì ẩn khối đó (không ô trống)', array( 1, 1 ), array( q( $lo, '//section[' . cls( 'leadership-content' ) . ']//p[' . cls( 'leadership-note' ) . ']' )->length, q( $lo, '//section[' . cls( 'leadership-content' ) . ']//div[' . cls( 'leader-statement' ) . ']' )->length ) );
same( 'an toàn: <b> trong họ tên hiển thị nguyên văn, không thành thẻ', 0, q( $lo, '//main//b' )->length );
same( 'lãnh đạo: nhập một nguyên tắc thì chỉ hiện một', 1, q( $lo, '//div[' . cls( 'leadership-principle-grid' ) . ']/article' )->length );

// --- Phòng ban ---
$d2 = render( 'templates/departments', false, 'vi', array( 'VG_FX_ID' => '503' ) );
common_checks( $d2, 'phòng ban' );
same( 'phòng ban: h1 có ngắt dòng và cụm nhấn', 1, q( $d2, '//h1[count(br)=1 and count(em)=1]' )->length );
same( 'phòng ban: 4 nhãn (UC-02)', array( 'Thiết kế', 'Kỹ thuật', 'Chi phí', 'Triển khai' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d2, '//ul[' . cls( 'department-tags' ) . ']/li' ) ) ) );
same( 'phòng ban: 6 bộ môn đánh số 01–06', array( 6, '01', '06' ), array( q( $d2, '//div[' . cls( 'discipline-matrix' ) . ']/article' )->length, trim( q( $d2, '(//div[' . cls( 'discipline-matrix' ) . ']/article/span)[1]' )->item( 0 )->textContent ), trim( q( $d2, '(//div[' . cls( 'discipline-matrix' ) . ']/article/span)[6]' )->item( 0 )->textContent ) ) );
check( 'phòng ban: trang thuộc module "departments" (data-vg-page)', 1 === q( $d2, '//body[@data-vg-page="departments"]' )->length );

$d3 = render( 'templates/departments', false, 'vi', array( 'VG_FX_ID' => '503', 'VG_FX_PAGE_OVERRIDE' => '1' ) );
same( 'phòng ban: bản nhập thắng — nhãn bỏ dòng trống và khoảng trắng', array( 'Thiết kế', 'Kỹ thuật' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d3, '//ul[' . cls( 'department-tags' ) . ']/li' ) ) ) );
same( 'phòng ban: dòng bộ môn trống hoàn toàn bị bỏ, số thứ tự liền nhau', array( 'Bộ môn A', '01', 'Bộ môn B', '02' ), array( trim( q( $d3, '(//div[' . cls( 'discipline-matrix' ) . ']/article/h3)[1]' )->item( 0 )->textContent ), trim( q( $d3, '(//div[' . cls( 'discipline-matrix' ) . ']/article/span)[1]' )->item( 0 )->textContent ), trim( q( $d3, '(//div[' . cls( 'discipline-matrix' ) . ']/article/h3)[2]' )->item( 0 )->textContent ), trim( q( $d3, '(//div[' . cls( 'discipline-matrix' ) . ']/article/span)[2]' )->item( 0 )->textContent ) ) );

// --- Lĩnh vực hoạt động ---
$s = render( 'templates/services', false, 'vi', array( 'VG_FX_ID' => '504' ) );
common_checks( $s, 'lĩnh vực' );
check( 'lĩnh vực: trang thuộc module "services" (data-vg-page)', 1 === q( $s, '//body[@data-vg-page="services"]' )->length );
same( 'lĩnh vực: 6 nhóm đúng thứ tự, mỗi nhóm có neo (UC-03)', array( 'thiet-ke-kien-truc', 'thiet-ke-ket-cau', 'ha-tang-canh-quan-co-dien', 'lap-du-an-quan-ly-du-an', 'giam-sat-thi-cong', 'tham-tra-khao-sat' ), array_map( static fn( $n ) => $n->getAttribute( 'id' ), iterator_to_array( q( $s, '//details[' . cls( 'service-item' ) . ']' ) ) ) );
same( 'lĩnh vực: chỉ nhóm đầu mở sẵn', array( 1, 'thiet-ke-kien-truc' ), array( q( $s, '//details[@open]' )->length, q( $s, '//details[@open]' )->item( 0 )->getAttribute( 'id' ) ) );
same( 'lĩnh vực: mọi nhóm có mô tả', 6, q( $s, '//details[' . cls( 'service-item' ) . ']/div[' . cls( 'service-body' ) . ']/p' )->length );
same( 'lĩnh vực: số thứ tự ở tiêu đề nhóm ẩn khỏi trình đọc màn hình', 6, q( $s, '//summary/span[' . cls( 'num' ) . ' and @aria-hidden="true"]' )->length );
$fig = q( $s, '//figure[' . cls( 'service-image' ) . ']' );
same( 'lĩnh vực: khung ảnh hiển thị sẵn dự án tham khảo của nhóm đầu, ghi rõ là ảnh tham khảo', array( 1, 'Phối cảnh thiết kế — Biệt thự tại Niedernhausen', 'Hình ảnh dự án tham khảo' ), array( $fig->length, q( $s, '//figure[' . cls( 'service-image' ) . ']/img' )->item( 0 )->getAttribute( 'alt' ), trim( q( $s, '//figure[' . cls( 'service-image' ) . ']//small' )->item( 0 )->textContent ) ) );
check( 'lĩnh vực: nhóm có dự án tham khảo mang địa chỉ ảnh, tên và số thứ tự cho JS', 6 === q( $s, '//details[@data-image and @data-alt and @data-ref and @data-number]' )->length );
check( 'lĩnh vực: liên kết từ trang chủ (#neo) trỏ tới đúng nhóm có mặt trên trang', 3 === count( array_filter( array( 'thiet-ke-kien-truc', 'lap-du-an-quan-ly-du-an', 'giam-sat-thi-cong' ), static fn( $a ) => 1 === q( $x, '//section[' . cls( 'service-preview-list' ) . ' or ' . cls( 'home-services' ) . ']//a[substring(@href, string-length(@href) - string-length("#' . $a . '") + 1) = "#' . $a . '"]' )->length ) ) );

$sn = render( 'templates/services', false, 'vi', array( 'VG_FX_ID' => '504', 'VG_FX_NO_REF' => '1' ) );
same( 'lĩnh vực: không nhóm nào có dự án tham khảo thì ẩn khung ảnh, danh sách vẫn đủ', array( 0, 6 ), array( q( $sn, '//figure[' . cls( 'service-image' ) . ']' )->length, q( $sn, '//details[' . cls( 'service-item' ) . ']' )->length ) );
$se = render( 'templates/services', true, 'vi', array( 'VG_FX_ID' => '504' ) );
common_checks( $se, 'lĩnh vực rỗng' );
same( 'lĩnh vực: chưa có nhóm nào thì báo đang cập nhật, không có danh sách', array( 0, 1 ), array( q( $se, '//details' )->length, q( $se, '//*[' . cls( 'services-empty' ) . ']' )->length ) );

// --- Liên hệ ---
$c2 = render( 'templates/contact', false, 'vi', array( 'VG_FX_ID' => '505' ) );
common_checks( $c2, 'liên hệ' );
check( 'liên hệ: trang thuộc module "contact" (data-vg-page)', 1 === q( $c2, '//body[@data-vg-page="contact"]' )->length );
same( 'liên hệ: h1 có 2 chỗ ngắt dòng và cụm nhấn', 1, q( $c2, '//h1[count(br)=2 and count(em)=1]' )->length );
check( 'liên hệ: số điện thoại bấm để gọi (tel:) khớp Cấu hình', 1 === q( $c2, '//a[' . cls( 'contact-phone' ) . ' and @href="tel:+84988961666" and normalize-space(.)="0988 961 666"]' )->length );
check( 'liên hệ: email là liên kết mailto: khớp Cấu hình', 1 === q( $c2, '//a[' . cls( 'contact-email' ) . ' and @href="mailto:vgarco.cc@gmail.com"]' )->length );
same( 'liên hệ: Zalo và Facebook chưa có địa chỉ thì hiện "Đang cập nhật", không phải liên kết (yêu cầu 29)', array( 2, 0, array( 'Đang cập nhật', 'Đang cập nhật' ) ), array( q( $c2, '//span[' . cls( 'contact-channel' ) . ' and ' . cls( 'is-pending' ) . ']' )->length, q( $c2, '//span[' . cls( 'contact-channel' ) . ']//a' )->length, array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $c2, '//span[' . cls( 'is-pending' ) . ']//small' ) ) ) ) );
$form = q( $c2, '//form[@id="contact-form"]' );
same( 'liên hệ: biểu mẫu KHÔNG gửi về máy chủ — action là mailto:, không có địa chỉ http(s)', array( 1, true ), array( $form->length, str_starts_with( $form->item( 0 )->getAttribute( 'action' ), 'mailto:' ) ) );
same( 'liên hệ: 3 ô bắt buộc (họ tên, email, nội dung) + đồng ý; số điện thoại không bắt buộc', array( 'name', 'email', 'message', 'consent' ), array_map( static fn( $n ) => $n->getAttribute( 'name' ), iterator_to_array( q( $c2, '//form[@id="contact-form"]//*[@required and @aria-required="true"]' ) ) ) );
check( 'liên hệ: ô đồng ý không tích sẵn', 0 === q( $c2, '//input[@name="consent" and @checked]' )->length );
$controls = iterator_to_array( q( $c2, '//form[@id="contact-form"]//*[(self::input or self::textarea) and @id and not(@readonly)]' ) );
check( 'liên hệ: 5 ô nhập, mỗi ô có nhãn (for/id hoặc bọc trong label)', 5 === count( $controls ) && 0 === count( array_filter( $controls, static fn( $n ) => 0 === q( $c2, '//label[@for="' . $n->getAttribute( 'id' ) . '"]' )->length && 0 === q( $c2, '//label//*[@id="' . $n->getAttribute( 'id' ) . '"]' )->length ) ) );
check( 'liên hệ: ô bắt buộc trỏ tới vùng báo lỗi có thật (aria-describedby)', 0 === count( array_filter( iterator_to_array( q( $c2, '//form[@id="contact-form"]//*[@required]' ) ), static fn( $n ) => 1 !== q( $c2, '//*[@id="' . $n->getAttribute( 'aria-describedby' ) . '"]' )->length ) ) );
check( 'liên hệ: nội dung giới hạn 1.500 ký tự (kiểm thử mailto UC-11)', 1 === q( $c2, '//textarea[@name="message" and @maxlength="1500"]' )->length );
check( 'liên hệ: phương án dự phòng ẩn sẵn, có ô nội dung chỉ đọc và nút sao chép', 1 === q( $c2, '//*[@data-fallback and @hidden]//textarea[@readonly]' )->length && 1 === q( $c2, '//*[@data-fallback]//button[@data-copy]' )->length );
check( 'liên hệ: mẫu tiêu đề và nội dung email có chỗ điền và dịch được (data-*)', str_contains( $form->item( 0 )->getAttribute( 'data-subject' ), '%s' ) && str_contains( $form->item( 0 )->getAttribute( 'data-body' ), '%message%' ) && '' !== $form->item( 0 )->getAttribute( 'data-msg-required' ) );
check( 'liên hệ: có vùng trạng thái cho trình đọc màn hình', 1 === q( $c2, '//*[@data-status and @role="status"]' )->length );
same( 'liên hệ: không có thẻ script nội tuyến hay tài nguyên bên ngoài trong nội dung', 0, q( $c2, '//main//script | //main//*[starts-with(@src, "http") or starts-with(@href, "http")]' )->length );

// Bài tư liệu và dự án tham khảo chưa xuất bản không được lộ ra công khai.
$ad = render( 'templates/about', false, 'vi', array( 'VG_FX_ID' => '501', 'VG_FX_PAGE_FULL' => '1', 'VG_FX_AWARD_DRAFT' => '1' ) );
same( 'ghi nhận: bài tư liệu chưa xuất bản thì ẩn nút, giữ khối', array( 1, 0 ), array( q( $ad, '//section[@id="ghi-nhan"]' )->length, q( $ad, '//section[@id="ghi-nhan"]//a' )->length ) );
$rd = render( 'templates/services', false, 'vi', array( 'VG_FX_ID' => '504', 'VG_FX_REF_DRAFT' => '1' ) );
same( 'lĩnh vực: dự án tham khảo chưa xuất bản bị bỏ — nhóm đó không có ảnh, khung ảnh dùng dự án kế tiếp', array( 0, 5, 'Phối cảnh thiết kế — Đài kiểm soát không lưu Đồng Hới' ), array( q( $rd, '//details[@id="thiet-ke-kien-truc" and @data-image]' )->length, q( $rd, '//details[@data-image]' )->length, q( $rd, '//figure[' . cls( 'service-image' ) . ']/img' )->item( 0 )->getAttribute( 'alt' ) ) );

/* ========================= Tin tức (UC-06) ========================= */

$titles = static fn( DOMXPath $d, string $expr ): array => array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d, $expr ) ) );

// --- Danh sách: tất cả ---
$n1 = render( 'archive-tin_tuc' );
common_checks( $n1, 'tin tức' );
check( 'tin tức: trang thuộc module "news" (data-vg-page)', 1 === q( $n1, '//body[@data-vg-page="news"]' )->length );
same( 'tin tức: h1 là nhãn "Tin tức & hoạt động"', 'Tin tức & hoạt động', trim( q( $n1, '//h1' )->item( 0 )->textContent ) );
same( 'tin tức: bộ lọc là liên kết thật — Tất cả + 3 chuyên mục', array( 'Tất cả', 'Tin tức công ty', 'Thông tin báo chí', 'Sự kiện' ), $titles( $n1, '//nav[' . cls( 'news-filters' ) . ']//a' ) );
same( 'tin tức: đang xem "Tất cả" (aria-current)', array( 'Tất cả' ), $titles( $n1, '//nav[' . cls( 'news-filters' ) . ']//a[@aria-current="page"]' ) );
same( 'tin tức: mới nhất trước', array( 'Chia sẻ chuyên môn cùng đội ngũ VGARCO', 'Dấu ấn hợp tác trong các dự án quốc tế', 'Từ ý tưởng đến một công trình có thể vận hành' ), $titles( $n1, '//div[' . cls( 'news-list' ) . ']//h2' ) );
same( 'tin tức: dòng có ngày <time datetime> và nhãn chủ đề', array( '2026-09-03', 'Sự kiện' ), array( q( $n1, '(//div[' . cls( 'news-list' ) . ']//time)[1]' )->item( 0 )->getAttribute( 'datetime' ), trim( q( $n1, '(//div[' . cls( 'news-row-meta' ) . ']/small)[1]' )->item( 0 )->textContent ) ) );
same( 'tin tức: ảnh nhỏ lazy và alt rỗng (tiêu đề bên cạnh đã mô tả — tránh đọc hai lần)', array( 3, 3 ), array( q( $n1, '//figure//img[@loading="lazy" and @alt=""]' )->length, q( $n1, '//article[' . cls( 'news-row' ) . ']//img' )->length ) );
same( 'tin tức: ít bài thì không có phân trang', 0, q( $n1, '//nav[' . cls( 'pagination' ) . ']' )->length );
same( 'tin tức: mọi dòng là liên kết tới bài', 3, q( $n1, '//article[' . cls( 'news-row' ) . ']/a[starts-with(@href, "/tin-tuc/")]' )->length );

// --- Phân trang: 23 bài = 3 trang, 9 bài mỗi trang ---
$p1 = render( 'archive-tin_tuc', false, 'vi', array( 'VG_FX_NEWS_MANY' => '1' ) );
same( 'phân trang: trang 1 có 9 bài', 9, q( $p1, '//article[' . cls( 'news-row' ) . ']' )->length );
same( 'phân trang: trang 1 — có 1,2,3 và "→", không có "←"; trang hiện tại là chữ, không là liên kết', array( '1', '2', '3', '→' ), $titles( $p1, '//nav[' . cls( 'pagination' ) . ']//li' ) );
same( 'phân trang: trang 1 đánh dấu aria-current và không có liên kết về chính nó', array( '1', 0 ), array( trim( q( $p1, '//nav[' . cls( 'pagination' ) . ']//span[@aria-current="page"]' )->item( 0 )->textContent ), q( $p1, '//nav[' . cls( 'pagination' ) . ']//a[.="1"]' )->length ) );
check( 'phân trang: số 2 và nút sau cùng trỏ tới trang 2; nút sau có nhãn cho trình đọc màn hình', 2 === q( $p1, '//nav[' . cls( 'pagination' ) . ']//a[@href="/tin-tuc/page/2/"]' )->length && 1 === q( $p1, '//nav[' . cls( 'pagination' ) . ']//a[@aria-label="Trang sau" and @href="/tin-tuc/page/2/"]' )->length );
$p2 = render( 'archive-tin_tuc', false, 'vi', array( 'VG_FX_NEWS_MANY' => '1', 'VG_FX_PAGED' => '2' ) );
same( 'phân trang: trang 2 — 9 bài, có cả "←" và "→"', array( 9, '←', '→' ), array( q( $p2, '//article[' . cls( 'news-row' ) . ']' )->length, $titles( $p2, '//nav[' . cls( 'pagination' ) . ']//li' )[0], $titles( $p2, '//nav[' . cls( 'pagination' ) . ']//li' )[4] ) );
$p3 = render( 'archive-tin_tuc', false, 'vi', array( 'VG_FX_NEWS_MANY' => '1', 'VG_FX_PAGED' => '3' ) );
same( 'phân trang: trang cuối — 5 bài, có "←", không có "→"', array( 5, '←', 0 ), array( q( $p3, '//article[' . cls( 'news-row' ) . ']' )->length, $titles( $p3, '//nav[' . cls( 'pagination' ) . ']//li' )[0], q( $p3, '//nav[' . cls( 'pagination' ) . ']//a[@aria-label="Trang sau"]' )->length ) );

// --- Theo chuyên mục ---
$k = render( 'taxonomy-chuyen_muc', false, 'vi', array( 'VG_FX_TERM' => 'su-kien' ) );
common_checks( $k, 'tin tức theo chuyên mục' );
same( 'chuyên mục: chỉ bài của mục "Sự kiện"', array( 'Chia sẻ chuyên môn cùng đội ngũ VGARCO' ), $titles( $k, '//div[' . cls( 'news-list' ) . ']//h2' ) );
same( 'chuyên mục: nút của mục đang xem được đánh dấu', array( 'Sự kiện' ), $titles( $k, '//nav[' . cls( 'news-filters' ) . ']//a[@aria-current="page"]' ) );
same( 'chuyên mục: đường dẫn 3 cấp', array( 'Trang chủ', 'Tin tức', 'Sự kiện' ), $titles( $k, '//nav[' . cls( 'breadcrumb' ) . ']//li' ) );
check( 'chuyên mục: bộ lọc trỏ tới địa chỉ các mục', 1 === q( $k, '//nav[' . cls( 'news-filters' ) . ']//a[@href="/tin-tuc/chuyen-muc/su-kien/"]' )->length );
$ke = render( 'taxonomy-chuyen_muc', true, 'vi', array( 'VG_FX_TERM' => 'thong-tin-bao-chi' ) );
common_checks( $ke, 'chuyên mục rỗng' );
same( 'chuyên mục rỗng: thông báo, giữ bộ lọc và mục đang chọn (UC-06 4a)', array( 1, 0, array( 'Thông tin báo chí' ) ), array( q( $ke, '//*[' . cls( 'news-empty' ) . ']' )->length, q( $ke, '//div[' . cls( 'news-list' ) . ']' )->length, $titles( $ke, '//nav[' . cls( 'news-filters' ) . ']//a[@aria-current="page"]' ) ) );

// --- Chi tiết ---
$a1 = render( 'single-tin_tuc', false, 'vi', array( 'VG_FX_ID' => '302' ) );
common_checks( $a1, 'chi tiết tin' );
check( 'chi tiết tin: trang thuộc module "article" (data-vg-page)', 1 === q( $a1, '//body[@data-vg-page="article"]' )->length );
same( 'chi tiết tin: h1 là tiêu đề, nhãn là chuyên mục', array( 'Dấu ấn hợp tác trong các dự án quốc tế', 'Thông tin báo chí' ), array( trim( q( $a1, '//h1' )->item( 0 )->textContent ), trim( q( $a1, '//header[' . cls( 'news-article-head' ) . ']/div/p[' . cls( 'eyebrow' ) . ']' )->item( 0 )->textContent ) ) );
same( 'chi tiết tin: đường dẫn Trang chủ / Tin tức / tiêu đề', array( 'Trang chủ', 'Tin tức', 'Dấu ấn hợp tác trong các dự án quốc tế' ), $titles( $a1, '//nav[' . cls( 'breadcrumb' ) . ']//li' ) );
same( 'chi tiết tin: ngày và nhãn chủ đề', array( '2026-09-02', 'Thông tin báo chí' ), array( q( $a1, '//p[' . cls( 'news-article-meta' ) . ']/time' )->item( 0 )->getAttribute( 'datetime' ), trim( q( $a1, '//p[' . cls( 'news-article-meta' ) . ']/span' )->item( 0 )->textContent ) ) );
$cov = q( $a1, '//figure[' . cls( 'news-article-cover' ) . ']/img' )->item( 0 );
check( 'chi tiết tin: ảnh đầu bài là ảnh LCP — eager + fetchpriority=high, alt là chú thích', null !== $cov && 'eager' === $cov->getAttribute( 'loading' ) && 'high' === $cov->getAttribute( 'fetchpriority' ) && 'Chú thích ảnh 302' === $cov->getAttribute( 'alt' ) );
same( 'chi tiết tin: chú thích ảnh hiển thị dưới ảnh', 'Chú thích ảnh 302', trim( q( $a1, '//figure[' . cls( 'news-article-cover' ) . ']/figcaption' )->item( 0 )->textContent ) );
same( 'chi tiết tin: đoạn dẫn, tiêu đề phụ, trích dẫn, ảnh minh họa (UC-06)', array( 1, 2, 1, 1 ), array( q( $a1, '//p[' . cls( 'article-lead' ) . ']' )->length, q( $a1, '//div[' . cls( 'news-article-body' ) . ']/h2' )->length, q( $a1, '//div[' . cls( 'news-article-body' ) . ']/blockquote' )->length, q( $a1, '//div[' . cls( 'news-article-body' ) . ']//figure' )->length ) );
$fb = q( $a1, '//a[' . cls( 'share-facebook' ) . ']' )->item( 0 );
check( 'chia sẻ: Facebook là liên kết thật mở thẻ mới, có noopener, địa chỉ bài được mã hóa (6b)', null !== $fb && str_starts_with( $fb->getAttribute( 'href' ), 'https://www.facebook.com/sharer/sharer.php?u=%2Ftin-tuc%2F302%2F' ) && '_blank' === $fb->getAttribute( 'target' ) && str_contains( $fb->getAttribute( 'rel' ), 'noopener' ) && '' !== $fb->getAttribute( 'aria-label' ) );
check( 'chia sẻ: nút sao chép liên kết ẩn cho tới khi có JS (6a), mang địa chỉ bài và nhãn dịch được', 1 === q( $a1, '//button[@data-copy-link="/tin-tuc/302/" and @hidden and @data-done != "" and @aria-label != ""]' )->length && 1 === q( $a1, '//*[@data-copy-status and @role="status"]' )->length );
same( 'bài trước / sau: bài 302 có cả hai — trước là bài mới hơn (303), sau là bài cũ hơn (301)', array( '/tin-tuc/303/', '/tin-tuc/301/' ), array( q( $a1, '//nav[' . cls( 'news-article-nav' ) . ']/a[' . cls( 'nav-prev' ) . ']' )->item( 0 )->getAttribute( 'href' ), q( $a1, '//nav[' . cls( 'news-article-nav' ) . ']/a[' . cls( 'nav-next' ) . ']' )->item( 0 )->getAttribute( 'href' ) ) );
$af = render( 'single-tin_tuc', false, 'vi', array( 'VG_FX_ID' => '303' ) );
same( 'bài đầu danh sách: không có "Bài trước", còn "Bài tiếp theo" (UC-06 7a)', array( 0, 1 ), array( q( $af, '//a[' . cls( 'nav-prev' ) . ']' )->length, q( $af, '//a[' . cls( 'nav-next' ) . ']' )->length ) );
$al = render( 'single-tin_tuc', false, 'vi', array( 'VG_FX_ID' => '301' ) );
same( 'bài cuối danh sách: không có "Bài tiếp theo", còn "Bài trước" (UC-06 7a)', array( 1, 0 ), array( q( $al, '//a[' . cls( 'nav-prev' ) . ']' )->length, q( $al, '//a[' . cls( 'nav-next' ) . ']' )->length ) );

/* ========================= Tuyển dụng (UC-07) và ứng tuyển (UC-08) ========================= */

$card_titles = static fn( DOMXPath $d ): array => array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $d, '//article[' . cls( 'job-card' ) . ' and not(@hidden)]//h3' ) ) );

// --- Danh sách ---
$j = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506' ) );
common_checks( $j, 'tuyển dụng' );
check( 'tuyển dụng: trang thuộc module "careers" (data-vg-page)', 1 === q( $j, '//body[@data-vg-page="careers"]' )->length );
same( 'tuyển dụng: h1 có ngắt dòng và cụm nhấn', 1, q( $j, '//h1[count(br)=1 and count(em)=1]' )->length );
same( 'tuyển dụng: chỉ vị trí ĐANG NHẬN — bỏ vị trí quá hạn và vị trí đã đóng; hạn nộp là hôm nay vẫn còn nhận', array( 'Kiến trúc sư thiết kế', 'Kỹ sư kết cấu', 'Kỹ sư giám sát' ), $card_titles( $j ) );
same( 'tuyển dụng: số lượng "03 vị trí"', array( '03', 'vị trí' ), array( trim( q( $j, '//p[@data-job-count]/strong' )->item( 0 )->textContent ), trim( q( $j, '//p[@data-job-count]/span' )->item( 0 )->textContent ) ) );
check( 'tuyển dụng: vùng số lượng báo cho trình đọc màn hình khi đổi (aria-live)', 1 === q( $j, '//p[@data-job-count and @aria-live="polite"]' )->length );
same( 'tuyển dụng: thẻ đánh số 01–03, số ẩn khỏi trình đọc màn hình', array( '01', '02', '03' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $j, '//article[' . cls( 'job-card' ) . ']/span[' . cls( 'job-number' ) . ' and @aria-hidden="true"]' ) ) ) );
same( 'tuyển dụng: dòng thông tin gồm nơi làm việc, phòng ban, loại hình', array( 'Hà Nội', 'Kiến trúc', 'Toàn thời gian' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $j, '(//article[' . cls( 'job-card' ) . ']//ul[' . cls( 'job-meta' ) . '])[1]/li' ) ) ) );
check( 'tuyển dụng: mỗi thẻ mang chuỗi tìm kiếm đã bỏ dấu và slug lọc cho JS', 'kien truc su thiet ke' === substr( q( $j, '(//article[' . cls( 'job-card' ) . '])[1]' )->item( 0 )->getAttribute( 'data-search' ), 0, 21 ) && 'ha-noi' === q( $j, '(//article[' . cls( 'job-card' ) . '])[1]' )->item( 0 )->getAttribute( 'data-location' ) && 'kien-truc' === q( $j, '(//article[' . cls( 'job-card' ) . '])[1]' )->item( 0 )->getAttribute( 'data-department' ) );
check( 'tuyển dụng: liên kết chi tiết có tên riêng cho từng vị trí (không 3 nút cùng chữ "Xem chi tiết")', 3 === q( $j, '//a[' . cls( 'job-detail-button' ) . ' and starts-with(@aria-label, "Xem chi tiết: ")]' )->length );
same( 'tuyển dụng: bộ lọc chỉ có nơi làm việc / phòng ban đang có vị trí', array( array( 'Tất cả địa điểm', 'Hà Nội', 'Nghệ An' ), array( 'Tất cả phòng ban', 'Kiến trúc', 'Xây dựng dân dụng', 'Giám sát thi công' ) ), array( array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $j, '//select[@name="noi-lam-viec"]/option' ) ) ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $j, '//select[@name="phong-ban"]/option' ) ) ) ) );
check( 'tuyển dụng: biểu mẫu tìm là GET, có role=search, mọi ô có nhãn (không JS vẫn lọc được)', 1 === q( $j, '//form[@role="search" and @method="get" and @data-job-form]' )->length && 3 === count( array_filter( array( 'job-keyword', 'job-location', 'job-department' ), static fn( $i ) => 1 === q( $j, '//label[@for="' . $i . '"]' )->length ) ) );
same( 'tuyển dụng: chưa lọc thì nút "Xóa bộ lọc" và thông báo rỗng đều ẩn', array( 1, 1 ), array( q( $j, '//a[@data-job-reset and ' . cls( 'job-reset' ) . ' and @hidden]' )->length, q( $j, '//div[@data-job-empty and @hidden]' )->length ) );
check( 'tuyển dụng: khối "Chưa thấy vị trí phù hợp?" có liên kết mailto tới email chung', 1 === q( $j, '//section[' . cls( 'career-connect' ) . ']//a[@href="mailto:vgarco.cc@gmail.com"]' )->length );

// --- Lọc bằng URL (không JS) ---
$jf = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_GET' => 'q=kien+truc&noi-lam-viec=ha-noi' ) );
same( 'lọc: "kien truc" (không dấu) + Hà Nội → chỉ Kiến trúc sư', array( 'Kiến trúc sư thiết kế' ), $card_titles( $jf ) );
same( 'lọc: số lượng cập nhật "01", số thứ tự tính lại, ô nhập giữ giá trị, nơi làm việc được chọn', array( '01', 'kien truc', 'ha-noi' ), array( trim( q( $jf, '//p[@data-job-count]/strong' )->item( 0 )->textContent ), q( $jf, '//input[@name="q"]' )->item( 0 )->getAttribute( 'value' ), q( $jf, '//select[@name="noi-lam-viec"]/option[@selected]' )->item( 0 )->getAttribute( 'value' ) ) );
same( 'lọc: hiện nút "Xóa bộ lọc" khi đang lọc', 0, q( $jf, '//a[' . cls( 'job-reset' ) . ' and @hidden]' )->length );
$jc = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_GET' => 'q=Ki%E1%BA%BFn+tr%C3%BAc' ) );
same( 'lọc: gõ có dấu "Kiến trúc" cho cùng kết quả', array( 'Kiến trúc sư thiết kế' ), $card_titles( $jc ) );
$jd = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_GET' => 'phong-ban=giam-sat-thi-cong' ) );
same( 'lọc: theo phòng ban', array( 'Kỹ sư giám sát' ), $card_titles( $jd ) );
same( 'lọc: số thứ tự của thẻ còn lại là "01" (đếm lại theo kết quả, không giữ số cũ "03")', '01', trim( q( $jd, '//article[' . cls( 'job-card' ) . ' and not(@hidden)]/span[' . cls( 'job-number' ) . ']' )->item( 0 )->textContent ) );
$jm = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_GET' => 'q=ky+su+ket+cau' ) );
same( 'lọc: nhiều từ khóa — mọi từ đều phải có ("ky su ket cau" chỉ khớp Kỹ sư kết cấu, không khớp Kỹ sư giám sát)', array( 'Kỹ sư kết cấu' ), $card_titles( $jm ) );
$jn = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_GET' => 'q=zzzz' ) );
same( 'lọc: không có kết quả → thông báo hiện, có liên kết "Xem tất cả vị trí", số lượng "00" (UC-07 5a/5b)', array( 0, 0, '00', 1 ), array( count( $card_titles( $jn ) ), q( $jn, '//div[@data-job-empty and @hidden]' )->length, trim( q( $jn, '//p[@data-job-count]/strong' )->item( 0 )->textContent ), q( $jn, '//div[@data-job-empty]//a[@data-job-reset]' )->length ) );
$ji = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_GET' => 'noi-lam-viec=khong-co&phong-ban=cung-khong-co' ) );
same( 'lọc: giá trị không có trong danh sách bị bỏ qua (không tin URL) — vẫn hiện đủ 3 vị trí', 3, count( $card_titles( $ji ) ) );
$jx = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_GET' => 'q=' . rawurlencode( '"><script>alert(1)</script>' ) ) );
same( 'an toàn: từ khóa độc hại trong URL không thành thẻ script', 0, q( $jx, '//main//script' )->length );
$jl = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_GET' => 'q=' . str_repeat( 'a', 300 ) ) );
same( 'lọc: từ khóa dài bị cắt ở 100 ký tự', 100, strlen( q( $jl, '//input[@name="q"]' )->item( 0 )->getAttribute( 'value' ) ) );

// --- Chưa có vị trí nào (UC-07 3a) ---
$j0 = render( 'templates/careers', false, 'vi', array( 'VG_FX_ID' => '506', 'VG_FX_NO_JOBS' => '1' ) );
common_checks( $j0, 'tuyển dụng rỗng' );
same( 'chưa có vị trí: thông báo, không có biểu mẫu tìm/thẻ, vẫn có khối "Chưa thấy vị trí phù hợp?" (3a, 3b)', array( 1, 0, 0, 1 ), array( q( $j0, '//*[' . cls( 'jobs-empty' ) . ']' )->length, q( $j0, '//form[@data-job-form]' )->length, q( $j0, '//article[' . cls( 'job-card' ) . ']' )->length, q( $j0, '//section[' . cls( 'career-connect' ) . ']' )->length ) );

// --- Chi tiết vị trí ---
$v = render( 'single-vi_tri', false, 'vi', array( 'VG_FX_ID' => '401' ) );
common_checks( $v, 'chi tiết vị trí' );
same( 'chi tiết vị trí: h1, đường dẫn 3 cấp', array( 'Kiến trúc sư thiết kế', array( 'Trang chủ', 'Tuyển dụng', 'Kiến trúc sư thiết kế' ) ), array( trim( q( $v, '//h1' )->item( 0 )->textContent ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $v, '//nav[' . cls( 'breadcrumb' ) . ']//li' ) ) ) ) );
check( 'chi tiết vị trí: nút "Ứng tuyển ngay" trỏ tới trang ứng tuyển kèm ID vị trí', 1 === q( $v, '//a[' . cls( 'job-apply-primary' ) . ' and @href="/ung-tuyen/?id=401"]' )->length && 0 === q( $v, '//*[' . cls( 'job-closed' ) . ']' )->length );
same( 'chi tiết vị trí: ba khối danh sách, dòng trống bị bỏ và cắt khoảng trắng', array( 3, 2, 1 ), array( q( $v, '//div[' . cls( 'job-detail-content' ) . ']/section[1]//li' )->length, q( $v, '//div[' . cls( 'job-detail-content' ) . ']/section[2]//li' )->length, q( $v, '//div[' . cls( 'job-detail-content' ) . ']/section[3]//li' )->length ) );
same( 'chi tiết vị trí: cột tóm tắt có địa điểm, phòng ban, loại hình, hạn nộp', array( 'Địa điểm', 'Phòng ban', 'Loại hình', 'Hạn nộp' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $v, '//aside//dt' ) ) ) );
same( 'chi tiết vị trí: hạn nộp dùng thẻ <time datetime> đúng ngày', array( date( 'Y-m-d', strtotime( '+30 days' ) ), date( 'd/m/Y', strtotime( '+30 days' ) ) ), array( q( $v, '//aside//time' )->item( 0 )->getAttribute( 'datetime' ), trim( q( $v, '//aside//time' )->item( 0 )->textContent ) ) );
$v2 = render( 'single-vi_tri', false, 'vi', array( 'VG_FX_ID' => '402' ) );
same( 'chi tiết vị trí: không có hạn nộp thì ẩn dòng "Hạn nộp"', array( 'Địa điểm', 'Phòng ban', 'Loại hình' ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $v2, '//aside//dt' ) ) ) );
$v3 = render( 'single-vi_tri', false, 'vi', array( 'VG_FX_ID' => '403' ) );
same( 'chi tiết vị trí: hạn nộp là hôm nay vẫn nhận (có nút ứng tuyển)', 1, q( $v3, '//a[' . cls( 'job-apply-primary' ) . ']' )->length );
$v4 = render( 'single-vi_tri', false, 'vi', array( 'VG_FX_ID' => '404' ) );
same( 'chi tiết vị trí: quá hạn → hiện "Đã ngừng nhận hồ sơ", ẩn nút ứng tuyển (UC-07 7a)', array( 0, 1, 'Đã ngừng nhận hồ sơ' ), array( q( $v4, '//a[' . cls( 'job-apply-primary' ) . ']' )->length, q( $v4, '//p[' . cls( 'job-closed' ) . ' and @role="status"]' )->length, trim( q( $v4, '//p[' . cls( 'job-closed' ) . ']' )->item( 0 )->textContent ) ) );
$v5 = render( 'single-vi_tri', false, 'vi', array( 'VG_FX_ID' => '405' ) );
same( 'chi tiết vị trí: đóng thủ công → cũng ngừng nhận dù không có hạn', array( 0, 1 ), array( q( $v5, '//a[' . cls( 'job-apply-primary' ) . ']' )->length, q( $v5, '//p[' . cls( 'job-closed' ) . ']' )->length ) );

// --- Ứng tuyển ---
$a = render( 'templates/apply', false, 'vi', array( 'VG_FX_ID' => '507', 'VG_FX_GET' => 'id=401' ) );
common_checks( $a, 'ứng tuyển' );
check( 'ứng tuyển: trang thuộc module "apply" (data-vg-page)', 1 === q( $a, '//body[@data-vg-page="apply"]' )->length );
same( 'ứng tuyển: h1 là tên vị trí; đường dẫn 4 cấp', array( 'Kiến trúc sư thiết kế', array( 'Trang chủ', 'Tuyển dụng', 'Kiến trúc sư thiết kế', 'Ứng tuyển' ) ), array( trim( q( $a, '//h1' )->item( 0 )->textContent ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $a, '//nav[' . cls( 'breadcrumb' ) . ']//li' ) ) ) ) );
$af = q( $a, '//form[@id="job-apply-form"]' );
check( 'ứng tuyển: biểu mẫu ẩn cho tới khi JS sẵn sàng, gửi tới REST API, không phải trang thường', 1 === $af->length && $af->item( 0 )->hasAttribute( 'hidden' ) && '/wp-json/vgarco/v1/apply' === $af->item( 0 )->getAttribute( 'data-endpoint' ) && '/wp-json/vgarco/v1/apply/token' === $af->item( 0 )->getAttribute( 'data-token-endpoint' ) && '' === $af->item( 0 )->getAttribute( 'action' ) );
same( 'ứng tuyển: gửi kèm ID vị trí và ngôn ngữ; mã token để trống chờ JS cấp', array( '401', 'vi', '' ), array( q( $a, '//input[@name="job_id"]' )->item( 0 )->getAttribute( 'value' ), q( $a, '//input[@name="lang"]' )->item( 0 )->getAttribute( 'value' ), q( $a, '//input[@name="token"]' )->item( 0 )->getAttribute( 'value' ) ) );
same( 'ứng tuyển: bắt buộc họ tên, email, điện thoại, CV, mã bảo mật, đồng ý', array( 'name', 'email', 'phone', 'cv', 'captcha', 'consent' ), array_map( static fn( $n ) => $n->getAttribute( 'name' ), iterator_to_array( q( $a, '//form[@id="job-apply-form"]//*[@required and @aria-required="true"]' ) ) ) );
check( 'ứng tuyển: ô đồng ý không tích sẵn', 0 === q( $a, '//input[@name="consent" and @checked]' )->length );
$cv = q( $a, '//input[@name="cv"]' )->item( 0 );
check( 'ứng tuyển: ô CV chỉ nhận .pdf .doc .docx và có mô tả giới hạn 10 MB', '.pdf,.doc,.docx' === $cv->getAttribute( 'accept' ) && str_contains( trim( q( $a, '//small[@id="apply-cv-hint"]' )->item( 0 )->textContent ), '10 MB' ) && '10485760' === $af->item( 0 )->getAttribute( 'data-max-size' ) );
$hp = q( $a, '//div[' . cls( 'field-trap' ) . ']' );
check( 'ứng tuyển: trường bẫy ẩn khỏi người dùng (aria-hidden, tabindex=-1, không autocomplete) nhưng vẫn là ô nhập thật', 1 === $hp->length && 'true' === $hp->item( 0 )->getAttribute( 'aria-hidden' ) && 1 === q( $a, '//div[' . cls( 'field-trap' ) . ']/input[@name="website" and @tabindex="-1" and @autocomplete="off"]' )->length );
check( 'ứng tuyển: mọi ô có nhãn, ô bắt buộc trỏ tới vùng báo lỗi có thật', 0 === count( array_filter( iterator_to_array( q( $a, '//form[@id="job-apply-form"]//*[(self::input or self::textarea) and @id and not(@type="hidden") and not(ancestor::div[' . cls( 'field-trap' ) . '])]' ) ), static fn( $n ) => 0 === q( $a, '//label[@for="' . $n->getAttribute( 'id' ) . '"]' )->length && 0 === q( $a, '//label//*[@id="' . $n->getAttribute( 'id' ) . '"]' )->length ) ) && 0 === count( array_filter( iterator_to_array( q( $a, '//form[@id="job-apply-form"]//*[@required]' ) ), static fn( $n ) => 1 !== q( $a, '//*[@id="' . ( explode( ' ', $n->getAttribute( 'aria-describedby' ) )[ count( explode( ' ', $n->getAttribute( 'aria-describedby' ) ) ) - 1 ] ) . '"]' )->length ) ) );
check( 'ứng tuyển: ảnh mã bảo mật có mô tả, nút tạo mã mới có tên', 1 === q( $a, '//img[@data-captcha-image and @alt != ""]' )->length && 1 === q( $a, '//button[@data-captcha-refresh and @aria-label != ""]' )->length );
check( 'ứng tuyển: vùng báo lỗi chung có role=alert; mọi thông báo lỗi/thành công dịch được qua data-*', 1 === q( $a, '//p[@data-feedback and @role="alert"]' )->length && $af->item( 0 )->hasAttribute( 'data-msg-captcha' ) && $af->item( 0 )->hasAttribute( 'data-msg-success' ) && $af->item( 0 )->hasAttribute( 'data-msg-rate' ) && $af->item( 0 )->hasAttribute( 'data-msg-closed' ) );
check( 'ứng tuyển: có <noscript> chỉ đường thay thế (email tuyển dụng) vì thiếu JS thì không nộp được', str_contains( (string) file_get_contents( ROOT . '/tests/out/templates-apply-507.html' ), '<noscript>' ) && str_contains( (string) file_get_contents( ROOT . '/tests/out/templates-apply-507.html' ), 'mailto:vgarco.cc@gmail.com' ) );
check( 'ứng tuyển: ô đồng ý có liên kết tới chính sách dữ liệu, mở thẻ mới có noopener', 1 === q( $a, '//label[' . cls( 'consent-label' ) . ']//a[@href="/chinh-sach-bao-ve-du-lieu/" and @target="_blank" and contains(@rel, "noopener")]' )->length );
same( 'ứng tuyển: cột tóm tắt có tên vị trí, địa điểm, phòng ban và liên kết xem lại mô tả', array( 'Kiến trúc sư thiết kế', array( 'Địa điểm', 'Phòng ban' ), '/tuyen-dung/401/' ), array( trim( q( $a, '//aside[' . cls( 'job-apply-summary' ) . ']/h2' )->item( 0 )->textContent ), array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( q( $a, '//aside//dt' ) ) ), q( $a, '//aside[' . cls( 'job-apply-summary' ) . ']/a' )->item( 0 )->getAttribute( 'href' ) ) );

foreach ( array( '999' => 'không tồn tại', '404' => 'quá hạn', '405' => 'đã đóng', '506' => 'không phải vị trí (một trang)', '' => 'thiếu id' ) as $bad => $why ) {
	$ab = render( 'templates/apply', false, 'vi', array( 'VG_FX_ID' => '507', 'VG_FX_GET' => '' === $bad ? '' : 'id=' . $bad ) );
	same( "ứng tuyển ({$why}): không hiện biểu mẫu, có đường về danh sách vị trí", array( 0, 1 ), array( q( $ab, '//form[@id="job-apply-form"]' )->length, q( $ab, '//section[' . cls( 'job-apply-head' ) . ']//a[@href="/tuyen-dung/"]' )->length ) );
}
$ac = render( 'templates/apply', false, 'vi', array( 'VG_FX_ID' => '507', 'VG_FX_GET' => 'id=404' ) );
same( 'ứng tuyển (quá hạn): nói rõ đã ngừng nhận hồ sơ và nêu tên vị trí', array( 'Vị trí này đã ngừng nhận hồ sơ', 'Thực tập sinh kiến trúc' ), array( trim( q( $ac, '//h1' )->item( 0 )->textContent ), trim( q( $ac, '//section[' . cls( 'job-apply-head' ) . ']/p[2]' )->item( 0 )->textContent ) ) );

/* ========================= Trang 404 theo ngữ cảnh (yêu cầu 35) ========================= */

$contexts = array(
	'/du-an/khong-co/'        => array( '/du-an/', 'Xem tất cả dự án', 'dự án' ),
	'/du-an/'                 => array( '/du-an/', 'Xem tất cả dự án', 'dự án' ),
	'/tin-tuc/bai-cu/'        => array( '/tin-tuc/', 'Xem tin tức', 'bài viết' ),
	'/tuyen-dung/vi-tri-cu/'  => array( '/tuyen-dung/', 'Xem vị trí đang tuyển', 'vị trí' ),
	'/gioi-thieu/khong-co/'   => null,
	'/du-an-khac/x/'          => null,
	'/'                       => null,
);
foreach ( $contexts as $uri => $expect ) {
	$n = render( '404', false, 'vi', array( 'VG_FX_URI' => $uri ) );
	common_checks( $n, "404 {$uri}" );
	same( "404 {$uri}: h1 và nhãn 404", array( 'Không tìm thấy trang này', '404' ), array( trim( q( $n, '//h1' )->item( 0 )->textContent ), trim( q( $n, '//section[' . cls( 'error-page' ) . ']/p[' . cls( 'eyebrow' ) . ']' )->item( 0 )->textContent ) ) );
	$primary = q( $n, '//*[' . cls( 'error-actions' ) . ']/a[' . cls( 'button-primary' ) . ']' );
	$lead    = trim( q( $n, '//p[' . cls( 'error-lead' ) . ']' )->item( 0 )->textContent );
	if ( $expect ) {
		same( "404 {$uri}: nút chính dẫn về mục tương ứng", array( $expect[0], $expect[1] ), array( $primary->item( 0 )->getAttribute( 'href' ), trim( $primary->item( 0 )->textContent ) ) );
		check( "404 {$uri}: lời nhắn nói đúng ngữ cảnh ({$expect[2]})", str_contains( $lead, $expect[2] ) );
		same( "404 {$uri}: vẫn có liên kết phụ về trang chủ", 1, q( $n, '//*[' . cls( 'error-actions' ) . ']/a[' . cls( 'text-link' ) . ']' )->length );
	} else {
		same( "404 {$uri}: không đoán được mục → nút chính là Về trang chủ", array( 1, 'Về trang chủ' ), array( $primary->length, trim( $primary->item( 0 )->textContent ) ) );
		check( "404 {$uri}: lời nhắn chung, không nhắc dự án/tin/vị trí", ! preg_match( '/dự án này|bài viết này|vị trí này/u', $lead ) );
	}
	same( "404 {$uri}: 4 liên kết mục chính (dự án, tin, tuyển dụng, liên hệ)", array( '/du-an/', '/tin-tuc/', '/tuyen-dung/', '/lien-he/' ), array_map( static fn( $a ) => $a->getAttribute( 'href' ), iterator_to_array( q( $n, '//nav[' . cls( 'error-links' ) . ']//a' ) ) ) );
	check( "404 {$uri}: đường dẫn bị lỗi không bị in ra trang (chống XSS phản chiếu)", ! str_contains( $n->document->saveHTML(), 'khong-co' ) );
}
// Đường dẫn chứa mã độc: không xuất hiện trong HTML.
$xss = render( '404', false, 'vi', array( 'VG_FX_URI' => '/du-an/"><script>alert(1)</script>/' ) );
same( '404: đường dẫn chứa <script> không lọt vào HTML', 0, q( $xss, '//script[contains(., "alert(1)")]' )->length );
$de404 = render( '404', false, 'de', array( 'VG_FX_URI' => '/du-an/x/' ) );
common_checks( $de404, '404 tiếng Đức' );

/* ========================= Quy tắc SCSS tĩnh (lỗi axe đã gặp) ========================= */

$contact_scss = (string) file_get_contents( ROOT . '/wp-content/themes/vgarco/src/scss/pages/_contact.scss' );
$careers_scss = (string) file_get_contents( ROOT . '/wp-content/themes/vgarco/src/scss/pages/_careers.scss' );
check( 'SCSS: chữ trắng của ô đồng ý chỉ áp dụng trong biểu mẫu liên hệ (nền tối), không toàn cục — nếu không ô đồng ý ở biểu mẫu ứng tuyển (nền sáng) thành chữ trắng trên nền trắng', 1 === preg_match( '/^\.contact-form \.consent-label \{/m', $contact_scss ) && 0 === preg_match( '/^\.consent-label \{/m', $contact_scss ) );
check( 'SCSS: ô đồng ý ở biểu mẫu ứng tuyển có màu chữ tối tường minh', 1 === preg_match( '/\.job-apply-form \.consent-label \{[^}]*color: var\(--c-ink\);/s', $careers_scss ) );

/* ========================= Bộ chuyển ngôn ngữ (UC-10) ========================= */

$de_link = static function ( DOMXPath $n ): array {
	$a = q( $n, '//div[' . cls( 'language-switch' ) . ']/a[@hreflang="de"]' );
	return $a->length ? array( $a->item( 0 )->getAttribute( 'href' ), $a->item( 0 )->hasAttribute( 'data-lang-missing' ) ) : array();
};
$switch_cases = array(
	'dự án'                  => array( 'single-du_an', array( 'VG_FX_ID' => '104' ), '/de/du-an/' ),
	'loại công trình'        => array( 'taxonomy-loai_cong_trinh', array( 'VG_FX_TERM' => 'giao-duc' ), '/de/du-an/' ),
	'tin'                    => array( 'single-tin_tuc', array( 'VG_FX_ID' => '301' ), '/de/tin-tuc/' ),
	'chuyên mục'             => array( 'taxonomy-chuyen_muc', array( 'VG_FX_TERM' => 'su-kien' ), '/de/tin-tuc/' ),
	'vị trí tuyển dụng'      => array( 'single-vi_tri', array( 'VG_FX_ID' => '401' ), '/de/tuyen-dung/' ),
	'ứng tuyển'              => array( 'templates/apply', array( 'VG_FX_ID' => '507', 'VG_FX_GET' => 'id=401' ), '/de/tuyen-dung/' ),
	'trang Về công ty'       => array( 'templates/about', array( 'VG_FX_ID' => '501' ), '/de/' ),
	'trang chủ'              => array( 'front-page', array(), '/de/' ),
);
foreach ( $switch_cases as $label => [ $template, $env, $fallback ] ) {
	$miss = render( $template, false, 'vi', array_merge( $env, array( 'VG_FX_MISSING' => 'de' ) ) );
	same( "chuyển ngôn ngữ ({$label}) chưa có bản dịch: DE trỏ tới {$fallback} và đánh dấu missing", array( $fallback, true ), $de_link( $miss ) );
	same( "chuyển ngôn ngữ ({$label}): ngôn ngữ đang xem (VI) không bị đánh dấu missing", 0, q( $miss, '//div[' . cls( 'language-switch' ) . ']/a[@hreflang="vi" and @data-lang-missing]' )->length );
	$have = render( $template, false, 'vi', $env );
	same( "chuyển ngôn ngữ ({$label}) đã có bản dịch: liên kết thẳng của WPML, không đánh dấu", array( '/de/', false ), $de_link( $have ) );
}
$de_miss = render( 'front-page', false, 'de', array( 'VG_FX_MISSING' => 'vi' ) );
same( 'chuyển ngôn ngữ: từ trang DE sang VI chưa có bản dịch cũng đánh dấu missing (đối xứng)', array( '/', true ), array( q( $de_miss, '//div[' . cls( 'language-switch' ) . ']/a[@hreflang="vi"]' )->item( 0 )->getAttribute( 'href' ), q( $de_miss, '//div[' . cls( 'language-switch' ) . ']/a[@hreflang="vi"]' )->item( 0 )->hasAttribute( 'data-lang-missing' ) ) );
same( 'chuyển ngôn ngữ: ngôn ngữ đang xem mang aria-current', 1, q( $de_miss, '//div[' . cls( 'language-switch' ) . ']/a[@hreflang="de" and @aria-current="true"]' )->length );

/* ========================= SEO cơ bản (mục 8.9) ========================= */

$abs = array( 'VG_FX_ABS' => '1' );
$meta = static fn( DOMXPath $n, string $q ): string => $n->query( $q )->length ? ( $n->query( $q )->item( 0 )->getAttribute( 'content' ) ?: $n->query( $q )->item( 0 )->getAttribute( 'href' ) ) : '';
$ld   = static function ( DOMXPath $n, string $type ): array {
	foreach ( $n->query( '//script[@type="application/ld+json"]' ) as $s ) {
		$d = json_decode( $s->textContent, true );
		if ( is_array( $d ) && $type === ( $d['@type'] ?? '' ) ) {
			return $d;
		}
	}
	return array();
};

$h = render( 'front-page', false, 'vi', $abs );
same( 'SEO trang chủ: tiêu đề "VGARCO — khẩu hiệu"', 'VGARCO — Tư vấn thiết kế, giám sát và quản lý dự án xây dựng', trim( q( $h, '//title' )->item( 0 )->textContent ) );
check( 'SEO trang chủ: có mô tả mặc định, không dài quá 160 ký tự', '' !== $meta( $h, '//meta[@name="description"]' ) && mb_strlen( $meta( $h, '//meta[@name="description"]' ) ) <= 160 );
same( 'SEO trang chủ: canonical là địa chỉ gốc website', 'https://vgarco.test/', $meta( $h, '//link[@rel="canonical"]' ) );
same( 'SEO trang chủ: og:title và og:url', array( 'VGARCO — Tư vấn thiết kế, giám sát và quản lý dự án xây dựng', 'https://vgarco.test/' ), array( $meta( $h, '//meta[@property="og:title"]' ), $meta( $h, '//meta[@property="og:url"]' ) ) );
same( 'SEO trang chủ: robots cho phép lập chỉ mục (production)', 'max-image-preview:large', $meta( $h, '//meta[@name="robots"]' ) );
$org = $ld( $h, 'Organization' );
same( 'SEO trang chủ: JSON-LD Organization có tên, điện thoại, địa chỉ, logo', array( 'Công ty TNHH Tư vấn Xây dựng VGARCO', '+84988961666', 'PostalAddress', 'https://vgarco.test/assets/images/logo.svg' ), array( $org['name'] ?? '', $org['telephone'] ?? '', $org['address']['@type'] ?? '', $org['logo'] ?? '' ) );
same( 'SEO trang chủ: không có BreadcrumbList', array(), $ld( $h, 'BreadcrumbList' ) );

$pd = render( 'single-du_an', false, 'vi', array_merge( $abs, array( 'VG_FX_ID' => '104' ) ) );
same( 'SEO dự án: tiêu đề "Tên — Dự án — VGARCO"', 'Đài kiểm soát không lưu Đồng Hới — Dự án — VGARCO', trim( q( $pd, '//title' )->item( 0 )->textContent ) );
check( 'SEO dự án: không có mo_ta_seo thì mô tả lấy từ nội dung', str_starts_with( $meta( $pd, '//meta[@name="description"]' ), 'Dự án đài kiểm soát không lưu' ) );
same( 'SEO dự án: canonical là địa chỉ dự án', 'https://vgarco.test/du-an/104/', $meta( $pd, '//link[@rel="canonical"]' ) );
check( 'SEO dự án: og:image tuyệt đối kèm kích thước và mô tả ảnh', str_starts_with( $meta( $pd, '//meta[@property="og:image"]' ), 'https://' ) && '1200' === $meta( $pd, '//meta[@property="og:image:width"]' ) && 'Đài kiểm soát không lưu Đồng Hới' === $meta( $pd, '//meta[@property="og:image:alt"]' ) );
same( 'SEO dự án: og:type website, locale vi_VN với bản thay thế de_DE', array( 'website', 'vi_VN', 'de_DE' ), array( $meta( $pd, '//meta[@property="og:type"]' ), $meta( $pd, '//meta[@property="og:locale"]' ), $meta( $pd, '//meta[@property="og:locale:alternate"]' ) ) );
$bc = $ld( $pd, 'BreadcrumbList' );
same( 'SEO dự án: BreadcrumbList 3 mục, địa chỉ tuyệt đối', array( 3, 'https://vgarco.test/', 'https://vgarco.test/du-an/104/' ), array( count( $bc['itemListElement'] ?? array() ), $bc['itemListElement'][0]['item'] ?? '', $bc['itemListElement'][2]['item'] ?? '' ) );
same( 'SEO dự án: không có JSON-LD Organization (chỉ trang chủ)', array(), $ld( $pd, 'Organization' ) );
$pd2 = render( 'single-du_an', false, 'vi', array_merge( $abs, array( 'VG_FX_ID' => '106' ) ) );
same( 'SEO dự án: có mo_ta_seo thì dùng mo_ta_seo', 'Mô tả SEO riêng của Bảo tàng Lịch sử Quân đội nhân dân Lào.', $meta( $pd2, '//meta[@name="description"]' ) );

$cat = render( 'archive-du_an', false, 'vi', $abs );
same( 'SEO danh mục: tiêu đề không lặp "Dự án — Dự án"', 'Dự án — VGARCO', trim( q( $cat, '//title' )->item( 0 )->textContent ) );
same( 'SEO danh mục: canonical là trang danh mục', 'https://vgarco.test/du-an/', $meta( $cat, '//link[@rel="canonical"]' ) );
$tax = render( 'taxonomy-loai_cong_trinh', false, 'vi', array_merge( $abs, array( 'VG_FX_TERM' => 'giao-duc' ) ) );
same( 'SEO loại công trình: tiêu đề "Loại — Dự án — VGARCO"', 'giao-duc — Dự án — VGARCO', trim( q( $tax, '//title' )->item( 0 )->textContent ) );

$nw = render( 'single-tin_tuc', false, 'vi', array_merge( $abs, array( 'VG_FX_ID' => '301' ) ) );
check( 'SEO tin: og:type=article, có article:published_time', 'article' === $meta( $nw, '//meta[@property="og:type"]' ) && '' !== $meta( $nw, '//meta[@property="article:published_time"]' ) );
check( 'SEO tin: tiêu đề có mục "Tin tức"', str_ends_with( trim( q( $nw, '//title' )->item( 0 )->textContent ), '— Tin tức — VGARCO' ) );

$jo = render( 'single-vi_tri', false, 'vi', array_merge( $abs, array( 'VG_FX_ID' => '401' ) ) );
$jp = $ld( $jo, 'JobPosting' );
same( 'SEO vị trí đang mở: có JobPosting (tiêu đề, loại hình, tổ chức)', array( 'Kiến trúc sư thiết kế', 'FULL_TIME', 'Công ty TNHH Tư vấn Xây dựng VGARCO' ), array( $jp['title'] ?? '', $jp['employmentType'] ?? '', $jp['hiringOrganization']['name'] ?? '' ) );
check( 'SEO vị trí đang mở: mô tả HTML có phần công việc, yêu cầu', str_contains( $jp['description'] ?? '', '<ul><li>' ) );
foreach ( array( '404' => 'quá hạn', '405' => 'đã đóng' ) as $closed => $why ) {
	$jc = render( 'single-vi_tri', false, 'vi', array_merge( $abs, array( 'VG_FX_ID' => $closed ) ) );
	same( "SEO vị trí {$why}: KHÔNG có JobPosting", array(), $ld( $jc, 'JobPosting' ) );
}
$cr = render( 'templates/careers', false, 'vi', array_merge( $abs, array( 'VG_FX_ID' => '506' ) ) );
same( 'SEO trang Tuyển dụng: không có JobPosting (chỉ trang chi tiết mới có)', array(), $ld( $cr, 'JobPosting' ) );

$ap = render( 'templates/apply', false, 'vi', array_merge( $abs, array( 'VG_FX_ID' => '507', 'VG_FX_GET' => 'id=401' ) ) );
check( 'SEO trang Ứng tuyển: noindex (nhưng vẫn cho theo liên kết)', str_contains( $meta( $ap, '//meta[@name="robots"]' ), 'noindex' ) && ! str_contains( $meta( $ap, '//meta[@name="robots"]' ), 'nofollow' ) );
$nf = render( '404', false, 'vi', array_merge( $abs, array( 'VG_FX_URI' => '/x/' ) ) );
check( 'SEO 404: noindex và không có canonical', str_contains( $meta( $nf, '//meta[@name="robots"]' ), 'noindex' ) && 0 === q( $nf, '//link[@rel="canonical"]' )->length );
check( 'SEO 404: tiêu đề tiếng Việt, không phải "Page not found"', str_starts_with( trim( q( $nf, '//title' )->item( 0 )->textContent ), 'Không tìm thấy trang' ) );
$st = render( 'front-page', false, 'vi', array_merge( $abs, array( 'VG_FX_ENV' => 'staging' ) ) );
check( 'SEO staging: noindex VÀ nofollow (toàn bộ môi trường không phải production)', str_contains( $meta( $st, '//meta[@name="robots"]' ), 'noindex' ) && str_contains( $meta( $st, '//meta[@name="robots"]' ), 'nofollow' ) );
$dh = render( 'front-page', false, 'de', $abs );
same( 'SEO tiếng Đức: og:locale de_DE, bản thay thế vi_VN', array( 'de_DE', 'vi_VN' ), array( $meta( $dh, '//meta[@property="og:locale"]' ), $meta( $dh, '//meta[@property="og:locale:alternate"]' ) ) );

/* ========================= Trang nội dung chung (trang pháp lý) ========================= */

$p = render( 'page' );
common_checks( $p, 'trang chung' );
same( 'trang chung: h1 là tiêu đề trang', 'Bài thử', trim( q( $p, '//h1' )->item( 0 )->textContent ) );
check( 'trang chung: nội dung nằm trong .entry-content của bài viết .page-basic', 1 === q( $p, '//article[' . cls( 'page-basic' ) . ']/div[' . cls( 'entry-content' ) . ']/p[contains(., "Nội dung thử")]' )->length );
check( 'trang chung: đường dẫn vị trí kết thúc bằng tiêu đề, mục cuối là aria-current', 1 === q( $p, '//nav[' . cls( 'breadcrumb' ) . ']//li[@aria-current="page" and contains(., "Bài thử")]' )->length );

/* ---------- Kết quả ---------- */

echo "\n";
if ( $fail ) {
	echo 'THẤT BẠI ' . count( $fail ) . ' / ' . ( $pass + count( $fail ) ) . "\n";
	foreach ( $fail as $f ) {
		echo "  ✗ {$f}\n";
	}
	exit( 1 );
}
echo "ĐẠT {$pass} / {$pass} phép kiểm\n";

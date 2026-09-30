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
	$file   = ROOT . '/tests/out/' . ( $names[ $template ] ?? $template ) . $suffix . '.html';
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

	$bad_img = 0;
	foreach ( q( $x, '//img' ) as $img ) {
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

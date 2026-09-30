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
check( 'thư viện: hộp xem ảnh lớn là <dialog> có nhãn; mọi nút trong đó có tên (đóng, trước, sau)', 1 === q( $d, '//dialog[@data-gallery-lightbox and @aria-label != ""]' )->length && 3 === q( $d, '//dialog//button[@aria-label != ""]' )->length && 0 === q( $d, '//dialog//button[not(@aria-label) or @aria-label = ""]' )->length );
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

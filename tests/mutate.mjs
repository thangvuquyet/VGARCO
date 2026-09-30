// Kiểm thử đột biến: cố ý phá mã trong BẢN SAO, xác nhận bộ kiểm thử smoke.php chuyển sang đỏ.
// Kiểm thử không bao giờ đỏ thì chưa chắc đã kiểm được gì — đây là cách biết chúng có thật sự bắt lỗi.
// Chạy qua: node tests/run.mjs --mutate  (hoặc trực tiếp: node tests/mutate.mjs)
import { cpSync, mkdirSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';

const php = process.env.PHP ?? 'php';
const ext = process.env.PHP_EXT ?? '';
const src = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const work = join( tmpdir(), 'vgarco-mutation' );

const M = [
  ['mail: cho phép mọi địa chỉ', 'wp-content/mu-plugins/vgarco-loader.php',
    '$allowed = in_array( $address, $allow, true ) || in_array( $domain, $allow, true );', '$allowed = true;'],
  ['mail: bỏ kiểm tra Cc/Bcc', 'wp-content/mu-plugins/vgarco-loader.php', '(cc|bcc)', '(zzz)'],
  ['mail: so khớp tên miền lỏng (chứa thay vì bằng)', 'wp-content/mu-plugins/vgarco-loader.php',
    'in_array( $domain, $allow, true )', 'str_contains( $address, ltrim( $allow[1] ?? "", "@" ) )'],
  ['mail: bỏ kiểm tra môi trường staging', 'wp-content/mu-plugins/vgarco-loader.php',
    "'staging' !== wp_get_environment_type()", 'false'],
  ['menu: mất aria-current của nút cha', 'wp-content/themes/vgarco/inc/menus.php', 'aria-current="true"', 'data-x="true"'],
  ['menu: không thoát HTML nhãn nút', 'wp-content/themes/vgarco/inc/menus.php',
    "\t\t\tesc_html( $node['title'] )\n\t\t);\n\t\tprintf( '<div class=\"nav-panel\"", "\t\t\t$node['title']\n\t\t);\n\t\tprintf( '<div class=\"nav-panel\""],
  ['menu: bảng không ẩn mặc định', 'wp-content/themes/vgarco/inc/menus.php', 'id="%s" hidden>', 'id="%s">'],
  ['option: bỏ mặc định trong mã', 'wp-content/plugins/vgarco-core/inc/helpers.php',
    '$default = vgarco_dot_get( vgarco_option_defaults(), $path );', '$default = null;'],
  ['option: coi chuỗi rỗng là giá trị hợp lệ', 'wp-content/plugins/vgarco-core/inc/helpers.php',
    "null !== $value && '' !== $value", 'null !== $value'],
  ['seed: sai slug "y-te"', 'wp-content/plugins/vgarco-core/inc/seed.php', "'y-te'                  => 'Y tế',", "'Y Te'                  => 'Y tế',"],
  ['fields: hai trường cùng khóa (noi_bat đổi tên thành tieu_bieu)', 'wp-content/plugins/vgarco-core/inc/fields/du-an.php',
    "$f(\n\t\t\t\t'noi_bat',", "$f(\n\t\t\t\t'tieu_bieu',"],
  ['fields: tên trường viết hoa (vi phạm snake_case)', 'wp-content/plugins/vgarco-core/inc/fields/vi-tri.php',
    "'yeu_cau', 'Yêu cầu'", "'Yeu_Cau', 'Yêu cầu'"],
  ['icon: bỏ hẳn lớp chặn (trả về mọi tệp)', 'wp-content/themes/vgarco/inc/template-tags.php',
    "if ( ! preg_match( '/^[a-z0-9-]+$/', $name ) ) {\n\t\treturn '';\n\t}", ''],
  ['fields: mặc định công bố chủ đầu tư = bật', 'wp-content/plugins/vgarco-core/inc/fields/du-an.php',
    "'ui'            => 1,\n\t\t\t\t\t'default_value' => 0,\n\t\t\t\t\t'instructions'  => 'Chỉ bật khi chủ đầu tư", "'ui'            => 1,\n\t\t\t\t\t'default_value' => 1,\n\t\t\t\t\t'instructions'  => 'Chỉ bật khi chủ đầu tư"],
  ['fields: chú thích ảnh không dịch', 'wp-content/plugins/vgarco-core/inc/fields/du-an.php',
    "'chu_thich',\n\t\t\t\t\t\t\t'Chú thích',\n\t\t\t\t\t\t\t'text',\n\t\t\t\t\t\t\t'translate',", "'chu_thich',\n\t\t\t\t\t\t\t'Chú thích',\n\t\t\t\t\t\t\t'text',\n\t\t\t\t\t\t\t'copy',"],
  ['lines: không thoát HTML', 'wp-content/themes/vgarco/inc/template-tags.php',
    "esc_html( trim( $text ) ) );\n}", "trim( $text ) );\n}"],
  ['directions: không nối địa chỉ nhiều dòng', 'wp-content/plugins/vgarco-core/inc/helpers.php',
    "trim( (string) preg_replace( '/\\s+/u', ' ', (string) vgarco_option( 'phap_nhan.dia_chi_van_phong' ) ) )",
    "(string) vgarco_option( 'phap_nhan.dia_chi_van_phong' )"],
  ['icon: bỏ chặn tên nguy hiểm', 'wp-content/themes/vgarco/inc/template-tags.php', "'/^[a-z0-9-]+$/'", "'/./'"],
  ['validate slug: cho phép chữ hoa', 'wp-content/plugins/vgarco-core/inc/fields.php',
    "'/^[a-z0-9]+(?:-[a-z0-9]+)*$/'", "'/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'"],

  // --- Mẫu trang (bộ kiểm thử pages.php) ---
  ['danh mục: trang loại không lọc theo loại', 'wp-content/themes/vgarco/inc/models.php',
    "$args['tax_query'] = array(", "$args['zz_bo_loc'] = array(", 'pages'],
  ['danh mục: bỏ số 0 đứng đầu ở số lượng', 'wp-content/themes/vgarco/template-parts/catalogue.php',
    "vgarco_pad( $catalogue['count'] )", "$catalogue['count']", 'pages'],
  ['danh mục: loại rỗng mất liên kết về danh mục', 'wp-content/themes/vgarco/template-parts/catalogue.php',
    "<?php if ( $term ) : ?>\n\t\t\t\t<a class=\"text-link\"", "<?php if ( false ) : ?>\n\t\t\t\t<a class=\"text-link\"", 'pages'],
  ['danh mục: hiện "0 dự án" khi rỗng', 'wp-content/themes/vgarco/template-parts/catalogue.php',
    "<?php if ( $catalogue['count'] > 0 ) : ?>", "<?php if ( true ) : ?>", 'pages'],
  ['câu chuyện: hiện nút chuyển dù thiếu ảnh công trình', 'wp-content/themes/vgarco/template-parts/catalogue.php',
    "<?php if ( $has_built ) : //", "<?php if ( true ) : //", 'pages'],
  ['câu chuyện: ảnh ẩn vẫn lộ cho trình đọc màn hình', 'wp-content/themes/vgarco/template-parts/catalogue.php',
    "array( 'aria-hidden' => 'true' )", "array()", 'pages'],
  ['câu chuyện: tiêu đề không qua bộ lọc <em>', 'wp-content/themes/vgarco/template-parts/catalogue.php',
    "echo vgarco_heading( $story['title'] );", "echo esc_html( $story['title'] );", 'pages'],
  ['câu chuyện: không kiểm tra dự án đã xuất bản', 'wp-content/themes/vgarco/inc/models.php',
    " || 'publish' !== get_post_status( $project_id )", "", 'pages'],
  ['đường dẫn: mục cuối mất aria-current', 'wp-content/themes/vgarco/inc/template-tags.php',
    "<li aria-current=\"page\"><span>%s</span></li>", "<li><span>%s</span></li>", 'pages'],
  ['chi tiết: hiện chủ đầu tư dù chưa được công bố', 'wp-content/themes/vgarco/inc/models.php',
    "'owner'       => $published_parties ? $text( 'chu_dau_tu' ) : '',", "'owner'       => $text( 'chu_dau_tu' ),", 'pages'],
  ['chi tiết: "tiếp theo" không quay vòng ở dự án cuối', 'wp-content/themes/vgarco/inc/models.php',
    "$ids[ ( $pos + 1 ) % $total ]", "$ids[ min( $pos + 1, $total - 1 ) ]", 'pages'],
  ['chi tiết: hiện dòng thông tin trống', 'wp-content/themes/vgarco/inc/models.php',
    "'' !== $row[1]\n\t);", "true\n\t);", 'pages'],
  ['chi tiết: ảnh sau của thư viện không ẩn', 'wp-content/themes/vgarco/template-parts/project-gallery.php',
    "<?php echo 0 === $i ? '' : ' hidden'; ?>", "<?php echo ''; ?>", 'pages'],
  ['chi tiết: nút chuyển hiện dù chưa có JS', 'wp-content/themes/vgarco/template-parts/project-gallery.php',
    '<div class="project-gallery-controls" hidden data-gallery-controls>', '<div class="project-gallery-controls" data-gallery-controls>', 'pages'],
  ['chi tiết: ảnh cuối trang tải sớm', 'wp-content/themes/vgarco/inc/models.php',
    "'loading'  => 'lazy',", "'loading'  => 'eager',", 'pages'],
  ['chi tiết: ảnh bìa không ưu tiên (LCP)', 'wp-content/themes/vgarco/single-du_an.php',
    "'priority' => true,", "'priority' => false,", 'pages'],
  ['chi tiết: thư viện một ảnh vẫn hiện ảnh nhỏ', 'wp-content/themes/vgarco/template-parts/project-gallery.php',
    "$many  = $total > 1;", "$many  = true;", 'pages'],
  ['về công ty: tiêu đề không lọc thẻ độc hại', 'wp-content/themes/vgarco/inc/models.php',
    "$safe = wp_kses( trim( $text ), array( 'em' => array() ) );", "$safe = trim( $text );", 'pages'],
  ['về công ty: ô chữ không thoát HTML', 'wp-content/themes/vgarco/templates/about.php',
    "<p><?php echo esc_html( $t( 'hero_mo_ta_1',", "<p><?php echo ( $t( 'hero_mo_ta_1',", 'pages'],
  ['về công ty: dòng lặp trống không bị bỏ', 'wp-content/themes/vgarco/inc/content.php',
    "if ( array_filter( $clean, static fn( $v ) => '' !== $v && null !== $v && 0 !== $v ) ) {", "if ( true ) {", 'pages'],
  ['về công ty: ghi đè lặp mất bản mặc định khi trống', 'wp-content/themes/vgarco/inc/content.php',
    "return $rows ?: $defaults;", "return $rows;", 'pages'],
  ['về công ty: hiện khối ghi nhận dù trống', 'wp-content/themes/vgarco/templates/about.php',
    "<?php if ( $award_image > 0 || '' !== $award_title ) : ?>", "<?php if ( true ) : ?>", 'pages'],
  ['về công ty: nút tư liệu hiện dù bài chưa xuất bản', 'wp-content/themes/vgarco/templates/about.php',
    "( $award_post > 0 && 'publish' === get_post_status( $award_post ) )", "( $award_post > 0 )", 'pages'],
  ['về công ty: ảnh bìa không ưu tiên (LCP)', 'wp-content/themes/vgarco/templates/about.php',
    "'priority' => true,", "'priority' => false,", 'pages'],
  ['về công ty: tách dòng không bỏ dòng trống', 'wp-content/themes/vgarco/inc/content.php',
    "static fn( $l ) => '' !== $l ) );", "static fn( $l ) => true ) );", 'pages'],
  ['lãnh đạo: chữ cái đầu lấy hai từ cuối thay vì họ và tên', 'wp-content/themes/vgarco/templates/leadership.php',
    "array( $words[0], end( $words ) )", "array_slice( $words, -2 )", 'pages'],
  ['lãnh đạo: dòng thành viên trống không bị bỏ', 'wp-content/themes/vgarco/inc/content.php',
    "if ( array_filter( $clean, static fn( $v ) => '' !== $v && null !== $v && 0 !== $v ) ) {", "if ( true ) {", 'pages'],
  ['lãnh đạo: họ tên không thoát HTML', 'wp-content/themes/vgarco/templates/leadership.php',
    "<h2><?php echo esc_html( $member['ho_ten'] ); ?></h2>", "<h2><?php echo $member['ho_ten']; ?></h2>", 'pages'],
  ['lãnh đạo: vai trò trống vẫn in ô trống', 'wp-content/themes/vgarco/templates/leadership.php',
    "<?php if ( '' !== $member['vai_tro'] ) : ?>", "<?php if ( true ) : ?>", 'pages'],
  ['phòng ban: nhãn không bỏ dòng trống', 'wp-content/themes/vgarco/inc/content.php',
    "static fn( $l ) => '' !== $l ) );", "static fn( $l ) => true ) );", 'pages'],
  ['lĩnh vực: mọi nhóm đều mở sẵn', 'wp-content/themes/vgarco/templates/services.php',
    "<?php echo 0 === $i ? ' open' : ''; ?>", "<?php echo ' open'; ?>", 'pages'],
  ['lĩnh vực: bỏ neo của nhóm', 'wp-content/themes/vgarco/templates/services.php',
    'id="<?php echo esc_attr( $service[\'anchor\'] ); ?>"', 'id="x"', 'pages'],
  ['lĩnh vực: hiện dự án tham khảo chưa xuất bản', 'wp-content/themes/vgarco/inc/models.php',
    "( $ref_id > 0 && 'publish' === get_post_status( $ref_id ) )", "( $ref_id > 0 )", 'pages'],
  ['liên hệ: ô đồng ý tích sẵn', 'wp-content/themes/vgarco/templates/contact.php',
    '<input id="cf-consent" type="checkbox" name="consent" required', '<input id="cf-consent" type="checkbox" name="consent" checked required', 'pages'],
  ['liên hệ: ô email không bắt buộc', 'wp-content/themes/vgarco/templates/contact.php',
    'autocomplete="email" required aria-required="true"', 'autocomplete="email"', 'pages'],
  ['liên hệ: biểu mẫu gửi về máy chủ', 'wp-content/themes/vgarco/templates/contact.php',
    'action="<?php echo esc_url( \'mailto:\' . $email ); ?>"', 'action="/gui-lien-he/"', 'pages'],
  ['liên hệ: số điện thoại không bấm để gọi', 'wp-content/themes/vgarco/templates/contact.php',
    '<a class="contact-phone" href="<?php echo esc_url( \'tel:\' . $tel ); ?>">', '<a class="contact-phone" href="#">', 'pages'],
  ['liên hệ: phương án dự phòng hiện sẵn', 'wp-content/themes/vgarco/templates/contact.php',
    '<div class="form-fallback" data-fallback hidden>', '<div class="form-fallback" data-fallback>', 'pages'],
  ['liên hệ: bỏ giới hạn 1.500 ký tự', 'wp-content/themes/vgarco/templates/contact.php',
    'maxlength="1500"', 'maxlength="9999"', 'pages'],
  ['tin tức: phân trang hiện dù chỉ một trang', 'wp-content/themes/vgarco/inc/template-tags.php',
    "if ( $total < 2 ) {", "if ( $total < 1 ) {", 'pages'],
  ['tin tức: trang hiện tại vẫn là liên kết', 'wp-content/themes/vgarco/inc/template-tags.php',
    "if ( $is_current ) {", "if ( false ) {", 'pages'],
  ['tin tức: trang đầu có nút "trang trước"', 'wp-content/themes/vgarco/inc/template-tags.php',
    "if ( $current > 1 ) {", "if ( $current > 0 ) {", 'pages'],
  ['tin tức: trang cuối có nút "trang sau"', 'wp-content/themes/vgarco/inc/template-tags.php',
    "if ( $current < $total ) {", "if ( $current <= $total ) {", 'pages'],
  ['tin tức: mục đang xem không được đánh dấu', 'wp-content/themes/vgarco/template-parts/news-archive.php',
    "<?php echo $category['current'] ? ' aria-current=\"page\"' : ''; ?>", "", 'pages'],
  ['tin tức: bài trước/sau đảo ngược', 'wp-content/themes/vgarco/inc/models.php',
    "'prev'    => ( false !== $pos && $pos > 0 ) ? vgarco_news_item( $ids[ $pos - 1 ] ) : null,", "'prev'    => ( false !== $pos && $pos < count( $ids ) - 1 ) ? vgarco_news_item( $ids[ $pos + 1 ] ) : null,", 'pages'],
  ['tin tức: Facebook không có noopener', 'wp-content/themes/vgarco/single-tin_tuc.php',
    'target="_blank" rel="noopener noreferrer"', 'target="_blank"', 'pages'],
  ['tin tức: nút sao chép hiện dù chưa có JS', 'wp-content/themes/vgarco/single-tin_tuc.php',
    '<button type="button" data-copy-link="<?php echo esc_url( $url ); ?>" hidden', '<button type="button" data-copy-link="<?php echo esc_url( $url ); ?>"', 'pages'],
  ['tin tức: ảnh đầu bài không ưu tiên (LCP)', 'wp-content/themes/vgarco/single-tin_tuc.php',
    "'priority' => true,", "'priority' => false,", 'pages'],
  ['tin tức: chuyên mục rỗng không có thông báo', 'wp-content/themes/vgarco/template-parts/news-archive.php',
    '<p class="news-empty">', '<p class="news-none">', 'pages'],
  ['tuyển dụng: hiện cả vị trí đã đóng/quá hạn', 'wp-content/plugins/vgarco-core/inc/careers/jobs.php',
    "return '' === $deadline || $deadline >= wp_date( 'Ymd' );", "return true;", 'pages'],
  ['tuyển dụng: hạn nộp hôm nay bị coi là quá hạn', 'wp-content/plugins/vgarco-core/inc/careers/jobs.php',
    "$deadline >= wp_date( 'Ymd' )", "$deadline > wp_date( 'Ymd' )", 'pages'],
  ['tuyển dụng: vị trí đóng thủ công vẫn nhận', 'wp-content/plugins/vgarco-core/inc/careers/jobs.php',
    "if ( 'da_dong' === (string) get_field( 'trang_thai', $post_id ) ) {", "if ( false ) {", 'pages'],
  ['tuyển dụng: tìm kiếm phân biệt dấu', 'wp-content/plugins/vgarco-core/inc/careers/jobs.php',
    "return mb_strtolower( remove_accents( $text ) );", "return mb_strtolower( $text );", 'pages'],
  ['tuyển dụng: lọc không đòi mọi từ khóa', 'wp-content/themes/vgarco/inc/models.php',
    "if ( ! str_contains( $job['search'], $word ) ) {\n\t\t\t\t\t\treturn false;\n\t\t\t\t\t}\n\t\t\t\t}\n\t\t\t\treturn true;", "if ( str_contains( $job['search'], $word ) ) {\n\t\t\t\t\t\treturn true;\n\t\t\t\t\t}\n\t\t\t\t}\n\t\t\t\treturn ! $words;", 'pages'],
  ['tuyển dụng: tin giá trị lọc từ URL', 'wp-content/themes/vgarco/templates/careers.php',
    "$loc   = isset( $filters['locations'][ $loc ] ) ? $loc : '';", "", 'pages'],
  ['tuyển dụng: không cắt từ khóa 100 ký tự', 'wp-content/themes/vgarco/templates/careers.php',
    "$query = mb_substr( $q_raw, 0, 100 );", "$query = $q_raw;", 'pages'],
  ['tuyển dụng: số thứ tự không tính lại khi lọc', 'wp-content/themes/vgarco/templates/careers.php',
    "$number += $shown ? 1 : 0;", "$number += 1;", 'pages'],
  ['tuyển dụng: biểu mẫu tìm dùng POST', 'wp-content/themes/vgarco/templates/careers.php',
    'role="search" method="get"', 'role="search" method="post"', 'pages'],
  ['tuyển dụng: chi tiết quá hạn vẫn có nút ứng tuyển', 'wp-content/themes/vgarco/single-vi_tri.php',
    "<?php if ( $job['open'] ) : ?>", "<?php if ( true ) : ?>", 'pages'],
  ['ứng tuyển: biểu mẫu hiện cả khi vị trí đã đóng', 'wp-content/themes/vgarco/templates/apply.php',
    "<?php if ( ! $job || ! $job['open'] ) : ?>", "<?php if ( ! $job ) : ?>", 'pages'],
  ['ứng tuyển: trường bẫy nhận focus bàn phím', 'wp-content/themes/vgarco/templates/apply.php',
    'name="website" tabindex="-1" autocomplete="off"', 'name="website"', 'pages'],
  ['ứng tuyển: ô đồng ý tích sẵn', 'wp-content/themes/vgarco/templates/apply.php',
    '<input id="apply-consent" type="checkbox" name="consent" value="1" required', '<input id="apply-consent" type="checkbox" name="consent" value="1" checked required', 'pages'],
  ['ứng tuyển: ô CV nhận mọi loại tệp', 'wp-content/themes/vgarco/templates/apply.php',
    'accept=".pdf,.doc,.docx"', 'accept="*/*"', 'pages'],
  ['ứng tuyển: biểu mẫu hiện dù chưa có JS', 'wp-content/themes/vgarco/templates/apply.php',
    'enctype="multipart/form-data" novalidate hidden', 'enctype="multipart/form-data" novalidate', 'pages'],
  ['ứng tuyển: không có noscript chỉ đường', 'wp-content/themes/vgarco/templates/apply.php',
    '<noscript>', '<div>', 'pages'],
  ['thẻ dự án: vòng tròn mũi tên không ẩn khỏi trình đọc màn hình', 'wp-content/themes/vgarco/template-parts/cards/project.php',
    '<span class="circle-arrow" aria-hidden="true">', '<span class="circle-arrow">', 'pages'],
];

let caught = 0, missed = 0, skipped = 0;
for (const [name, file, from, to, suite = 'smoke'] of M) {
  rmSync(work, { recursive: true, force: true });
  mkdirSync(join(work, 'tests'), { recursive: true });
  cpSync(join(src, 'wp-content'), join(work, 'wp-content'), { recursive: true });
  cpSync(join(src, 'tests', 'php'), join(work, 'tests', 'php'), { recursive: true });

  const path = join(work, file);
  const text = readFileSync(path, 'utf8').replace(/\r\n/g, '\n');
  const n = text.split(from).length - 1;
  if (n !== 1) { skipped++; console.log(`${name.padEnd(50)} => BỎ QUA: đoạn gốc xuất hiện ${n} lần (cần đúng 1)`); continue; }
  writeFileSync(path, text.replace(from, () => to));

  const r = spawnSync(php, ['-d', `extension_dir=${ext}`, '-d', 'extension=mbstring', join(work, 'tests', 'php', `${suite}.php`)], { encoding: 'utf8' });
  const out = (r.stdout || '') + (r.stderr || '');
  const red = out.match(/THẤT BẠI (\d+)/);
  if (red) { caught++; console.log(`${name.padEnd(50)} => bắt được (${red[1]} phép kiểm đỏ)`); }
  else if (/ĐẠT/.test(out)) { missed++; console.log(`${name.padEnd(50)} => !!! KHÔNG BẮT ĐƯỢC`); }
  else { console.log(`${name.padEnd(50)} => lỗi chạy: ${out.trim().split('\n')[0]}`); }
}
rmSync(work, { recursive: true, force: true });
console.log(`\nBắt được ${caught}, bỏ sót ${missed}, bỏ qua ${skipped}`);
process.exit(missed > 0 ? 1 : 0);

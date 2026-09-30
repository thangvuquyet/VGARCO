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

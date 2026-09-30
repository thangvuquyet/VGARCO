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
];

let caught = 0, missed = 0, skipped = 0;
for (const [name, file, from, to] of M) {
  rmSync(work, { recursive: true, force: true });
  mkdirSync(join(work, 'tests'), { recursive: true });
  cpSync(join(src, 'wp-content'), join(work, 'wp-content'), { recursive: true });
  cpSync(join(src, 'tests', 'php'), join(work, 'tests', 'php'), { recursive: true });

  const path = join(work, file);
  const text = readFileSync(path, 'utf8').replace(/\r\n/g, '\n');
  const n = text.split(from).length - 1;
  if (n !== 1) { skipped++; console.log(`${name.padEnd(50)} => BỎ QUA: đoạn gốc xuất hiện ${n} lần (cần đúng 1)`); continue; }
  writeFileSync(path, text.replace(from, () => to));

  const r = spawnSync(php, ['-d', `extension_dir=${ext}`, '-d', 'extension=mbstring', join(work, 'tests', 'php', 'smoke.php')], { encoding: 'utf8' });
  const out = (r.stdout || '') + (r.stderr || '');
  const red = out.match(/THẤT BẠI (\d+)/);
  if (red) { caught++; console.log(`${name.padEnd(50)} => bắt được (${red[1]} phép kiểm đỏ)`); }
  else if (/ĐẠT/.test(out)) { missed++; console.log(`${name.padEnd(50)} => !!! KHÔNG BẮT ĐƯỢC`); }
  else { console.log(`${name.padEnd(50)} => lỗi chạy: ${out.trim().split('\n')[0]}`); }
}
rmSync(work, { recursive: true, force: true });
console.log(`\nBắt được ${caught}, bỏ sót ${missed}, bỏ qua ${skipped}`);

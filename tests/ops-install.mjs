import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const script = join(root, 'scripts', 'ops', 'install-wordpress.sh');

const probe = spawnSync('bash', ['--version'], { encoding: 'utf8' });
if (probe.error || !/GNU bash/.test(probe.stdout ?? '')) {
  console.log('BỎ QUA: không có GNU bash để chạy thử script cài WordPress');
  process.exit(0);
}

const posix = (p) => p.replace(/\\/g, '/').replace(/^([A-Za-z]):/, (_, d) => `/${d.toLowerCase()}`);
const source = readFileSync(script, 'utf8').replace(/\r\n/g, '\n');
let pass = 0;
const fail = [];
const check = (label, ok) => (ok ? pass++ : fail.push(label));
const same = (label, expected, actual) => check(`${label} — mong đợi ${JSON.stringify(expected)}, nhận ${JSON.stringify(actual)}`, JSON.stringify(expected) === JSON.stringify(actual));
const bash = (args, opts = {}) => spawnSync('bash', args, { encoding: 'utf8', cwd: root, ...opts });
const at = (needle) => source.indexOf(needle);

check('cú pháp bash hợp lệ (bash -n)', bash(['-n', posix(script)]).status === 0);

const base = ['--env=production', '--domain=vgarco.com.vn', '--admin-email=van-hanh@vgarco.com.vn'];
const exit = (args) => bash([posix(script), ...args, '--dry-run']).status;
same('thiếu mọi tham số thì từ chối', 2, exit([]));
same('--env lạ thì từ chối', 2, exit(['--env=prod', '--domain=x.vn', '--admin-email=a@b.vn']));
same('tên miền có chữ hoa thì từ chối', 2, exit(['--env=production', '--domain=X.vn', '--admin-email=a@b.vn']));
same('hộp thư không hợp lệ thì từ chối', 2, exit(['--env=production', '--domain=x.vn', '--admin-email=khong-hop-le']));
same('tài khoản tên admin thì từ chối', 2, exit([...base, '--admin-user=admin']));
same('tài khoản quá ngắn thì từ chối', 2, exit([...base, '--admin-user=ab']));
same('--wp-env lạ thì từ chối', 2, exit([...base, '--wp-env=local']));
same('tham số lạ thì từ chối', 2, exit([...base, '--zzz']));
same('tiêu đề rỗng thì từ chối', 2, exit([...base, '--title=']));
same('tham số đúng thì --dry-run thành công', 0, exit(base));

const prod = bash([posix(script), ...base, '--dry-run']).stdout;
check('--dry-run production: thư mục, CSDL và người dùng PHP của production', prod.includes('/var/www/vgarco/public') && prod.includes('/etc/vgarco/production-db.php') && prod.includes('deploy:vgarco)'));
check('--dry-run: không đổi gì và không đòi root', prod.includes('không kiểm quyền root, không đổi gì'));
check('--dry-run: nêu mật khẩu cất 0600 và không in ra', prod.includes('/home/deploy/.wp-admin-initial-password (0600), không in ra'));
const stg = bash([posix(script), '--env=staging', '--domain=staging.vgarco.test', '--admin-email=a@b.vn', '--dry-run']).stdout;
check('--dry-run staging: thư mục và người dùng PHP của staging', stg.includes('/var/www/vgarco-staging/public') && stg.includes('/etc/vgarco/staging-db.php') && stg.includes('deploy:vgarco-staging)'));
same('WP_ENVIRONMENT_TYPE mặc định bằng --env', true, stg.includes('WP_ENVIRONMENT_TYPE: staging') && prod.includes('WP_ENVIRONMENT_TYPE: production'));
check('--wp-env=staging khi dựng production được nêu trong kế hoạch', bash([posix(script), ...base, '--wp-env=staging', '--dry-run']).stdout.includes('WP_ENVIRONMENT_TYPE: staging'));

const real = bash([posix(script), ...base]);
same('chạy thật khi không phải root thì từ chối (mã 1), không đổi gì', [1, true], [real.status, /Cần chạy bằng root/.test(real.stderr)]);

const lock = JSON.parse(readFileSync(join(root, 'plugins.lock.json'), 'utf8'));
same('phiên bản WordPress trong script khớp plugins.lock.json', lock.wordpress.version, source.match(/^WP_VERSION="([^"]+)"/m)?.[1]);
same('bản vi, ghim phiên bản, bỏ giao diện và plugin mặc định', true, source.includes('core download --version="$WP_VERSION" --locale="$WP_LOCALE" --skip-content'));
check('chặn khi đã có WordPress, đứng trước bước tải lõi', at('đã có WordPress ở') > 0 && at('đã có WordPress ở') < at('wpd core download'));
check('chốt "đã có WordPress" kiểm cả wp-config.php lẫn lõi và thoát mã 1', /if \[\[ -e "\$CONFIG" \|\| -e "\$WEB_ROOT\/wp-includes\/version\.php" \]\]; then\n  echo "đã có WordPress ở [^\n]+" >&2\n  exit 1\nfi/.test(source));
check('kiểm root đứng trước mọi lệnh wp', at('Cần chạy bằng root') > 0 && at('Cần chạy bằng root') < at('wpd core download'));
check('muối khóa đổi bằng shuffle-salts sau khi kiểm cú pháp tệp', at('php -l "$CONFIG"') > 0 && at('php -l "$CONFIG"') < at('wpd config shuffle-salts'));
check('wp-config.php 0640, chủ deploy, nhóm người dùng PHP', source.includes('chown "deploy:${PHP_USER}" "$CONFIG"') && source.includes('chmod 0640 "$CONFIG"'));
check('mật khẩu ban đầu cất ở tệp 0600 và không bao giờ in ra', source.includes('install -m 0600 -o deploy -g deploy /dev/null "$PW_FILE"') && source.includes("sed -n 's/^Admin password: //p' \"$OUT\" > \"$PW_FILE\"") && (source.match(/grep -v 'Admin password' "\$OUT"/g) ?? []).length === 2 && !/cat "\$OUT"|cat "\$PW_FILE"/.test(source));
check('cài tiếng Việt, đường dẫn /%postname%/, múi giờ Việt Nam, xóa bộ nhớ đường dẫn', source.includes('language core install "$WP_LOCALE" --activate') && source.includes("permalink_structure '/%postname%/'") && source.includes("timezone_string 'Asia/Ho_Chi_Minh'") && source.includes('wpd rewrite flush'));
check('tài khoản quản trị không được là admin', source.includes('"$ADMIN_USER" != "admin"'));
check('không có tên miền hay hộp thư cố định trong script', !/vgarco\.com\.vn|vgarco\.vn|staging\.vgarco|@gmail\.com/.test(source.replace(/^\s*(?:usage|--).*$/gm, '')));

const startAt = source.indexOf('{\n  cat <<PHP\n<?php');
const endAt = source.indexOf('} > "$CONFIG"') + '} > "$CONFIG"'.length;
check('tìm thấy khối sinh wp-config.php', startAt > 0 && endAt > startAt);

const cauHinh = readFileSync(join(root, 'docs', 'CAU-HINH.md'), 'utf8').replace(/\r\n/g, '\n');
const sample = cauHinh.slice(cauHinh.indexOf('Mẫu rút gọn:'));
const wanted = [...sample.slice(0, sample.indexOf('```', sample.indexOf('```php') + 6)).matchAll(/^define\( '(\w+)', (true|false) \);$/gm)].map((m) => m[0]);
check('mẫu trong CAU-HINH.md có các hằng số cứng hóa để đối chiếu', wanted.length >= 7);

const prefix = source.match(/^TABLE_PREFIX="([^"]+)"/m)?.[1] ?? '';
check('tiền tố bảng của script khác wp_', /^[a-z0-9]+_$/.test(prefix) && prefix !== 'wp_');

const tmp = mkdtempSync(join(tmpdir(), 'vg-install-'));
const generate = (wpEnv, site, envName) => {
  const config = join(tmp, `${site}.php`);
  const code = `set -euo pipefail
WP_ENV='${wpEnv}'
DB_FILE='/etc/vgarco/${envName}-db.php'
PRIVATE_DIR='/srv/${site}/private'
SECRETS_DIR='/etc/vgarco'
TABLE_PREFIX='${prefix}'
CONFIG='${posix(config)}'
${source.slice(startAt, endAt)}
`;
  const r = bash(['-c', code]);
  return { status: r.status, err: r.stderr, text: r.status === 0 ? readFileSync(config, 'utf8') : '', config };
};

const p = generate('production', 'vgarco', 'production');
same('khối sinh wp-config.php chạy được (production)', 0, p.status);
check('tệp bắt đầu bằng <?php', p.text.startsWith('<?php\n'));
check('WP_ENVIRONMENT_TYPE theo --wp-env', p.text.includes("define( 'WP_ENVIRONMENT_TYPE', 'production' );"));
check('nạp tệp CSDL của đúng môi trường', p.text.includes("require '/etc/vgarco/production-db.php';"));
same('đủ 8 muối khóa tạm để shuffle-salts thay', 8, (p.text.match(/^define\( '[A-Z_]+(?:KEY|SALT)', 'doi-bang-shuffle-salts' \);$/gm) ?? []).length);
check('tiền tố bảng trong wp-config.php là tiền tố của script', p.text.includes(`$table_prefix = '${prefix}';`));
check('mọi hằng cứng hóa trong CAU-HINH.md đều có mặt', wanted.every((line) => p.text.includes(line)));
check('thư mục CV production ngoài thư mục web, khớp CAU-HINH.md', p.text.includes("define( 'VGARCO_PRIVATE_DIR', '/srv/vgarco/private' );") && cauHinh.includes("define( 'VGARCO_PRIVATE_DIR', '/srv/vgarco/private' );"));
check('nạp Redis rồi khóa bí mật, sau đó mới tới wp-settings.php', p.text.indexOf("require '/etc/vgarco/redis.php';") > p.text.indexOf('VGARCO_PRIVATE_DIR') && p.text.indexOf("require '/etc/vgarco/secrets.php';") > p.text.indexOf("require '/etc/vgarco/redis.php';") && p.text.indexOf("require_once ABSPATH . 'wp-settings.php';") > p.text.indexOf("require '/etc/vgarco/secrets.php';"));
check('không còn biến chưa thay trong tệp sinh ra', !/\$\{|\$PHP|\$\(/.test(p.text));

const s = generate('staging', 'vgarco-staging', 'staging');
check('staging: môi trường, tệp CSDL và thư mục CV của staging', s.text.includes("'WP_ENVIRONMENT_TYPE', 'staging'") && s.text.includes("require '/etc/vgarco/staging-db.php';") && s.text.includes("'/srv/vgarco-staging/private'"));
const w = generate('staging', 'vgarco', 'production');
check('dựng production nhưng giữ WP_ENVIRONMENT_TYPE staging được (trước ngày mở)', w.text.includes("'WP_ENVIRONMENT_TYPE', 'staging'") && w.text.includes("require '/etc/vgarco/production-db.php';"));

if (process.env.PHP) {
  const ext = process.env.PHP_EXT ? ['-d', `extension_dir=${process.env.PHP_EXT}`] : [];
  for (const [name, g] of [['production', p], ['staging', s]]) {
    const r = spawnSync(process.env.PHP, [...ext, '-l', g.config], { encoding: 'utf8' });
    check(`wp-config.php sinh ra hợp lệ cú pháp PHP (${name})`, r.status === 0);
  }
}
rmSync(tmp, { recursive: true, force: true });

if (fail.length) {
  for (const line of fail) console.log(`  ✗ ${line}`);
  console.log(`THẤT BẠI ${fail.length} / ${pass + fail.length}`);
  process.exit(1);
}
console.log(`ĐẠT ${pass}/${pass}`);

// Dựng môi trường thử WordPress thật (SQLite) vào tests/wp-lab/.lab. Chạy lại được; --reset để dựng lại từ đầu.
//
//   PHP=<php.exe> PHP_EXT=<thư mục ext> node tests/wp-lab/bootstrap.mjs <wordpress.zip> <sqlite-database-integration.zip>
//
// Hai tệp zip tải tay từ wordpress.org (README.md). Không thêm gì vào Git: .lab/ nằm trong .gitignore.
import { existsSync, mkdirSync, readFileSync, writeFileSync, rmSync, symlinkSync, cpSync, readdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const repo = resolve(here, '..', '..');
const lab = join(here, '.lab');
const wp = join(lab, 'wordpress');
const php = process.env.PHP ?? 'php';
const ext = process.env.PHP_EXT ?? '';
const slash = (p) => p.replace(/\\/g, '/');
const die = (msg) => { console.error('LỖI: ' + msg); process.exit(1); };

// Báo rõ khi không tìm thấy PHP (lỗi hay gặp nhất: chưa đặt biến PHP).
if (spawnSync(php, ['-v']).error) {
  console.error([
    `Không chạy được PHP ("${php}"). Đặt biến PHP (và PHP_EXT = thư mục ext) trỏ tới php, ví dụ:`,
    '  cmd:        set PHP=C:\\đường\\dẫn\\php.exe',
    '  PowerShell: $env:PHP="C:\\đường\\dẫn\\php.exe"',
    '  bash:       export PHP=/đường/dẫn/php',
  ].join('\n'));
  process.exit(1);
}

const args = process.argv.slice(2).filter((a) => !a.startsWith('--'));
if (process.argv.includes('--reset')) { rmSync(lab, { recursive: true, force: true }); console.log('Đã xóa .lab'); }
mkdirSync(lab, { recursive: true });

function unzip(zip, to) {
  mkdirSync(to, { recursive: true });
  const r = process.platform === 'win32'
    ? spawnSync('powershell', ['-NoProfile', '-Command', `Expand-Archive -LiteralPath '${zip}' -DestinationPath '${to}' -Force`], { encoding: 'utf8' })
    : spawnSync('unzip', ['-q', '-o', zip, '-d', to], { encoding: 'utf8' });
  if (r.status !== 0) die(`không giải nén được ${zip}: ${(r.stderr || r.stdout || '').trim()}`);
}

// 1. Lõi WordPress và plugin SQLite
if (!existsSync(join(wp, 'wp-load.php'))) {
  const [wpZip, sqliteZip] = args.map((a) => resolve(a));
  if (!wpZip || !sqliteZip || !existsSync(wpZip) || !existsSync(sqliteZip)) die('cần đủ hai tệp: <wordpress.zip> <sqlite-database-integration.zip> (xem README.md)');
  unzip(wpZip, lab);
  unzip(sqliteZip, join(wp, 'wp-content', 'plugins'));
  if (!existsSync(join(wp, 'wp-load.php'))) die('wordpress.zip không có thư mục "wordpress/"');
  if (!existsSync(join(wp, 'wp-content', 'plugins', 'sqlite-database-integration', 'db.copy'))) die('sqlite-database-integration.zip không đúng cấu trúc (thiếu db.copy)');
  console.log('Đã giải nén WordPress và plugin SQLite');
}

// 2. Drop-in CSDL, cấu hình, php.ini
const sqlitePlugin = join(wp, 'wp-content', 'plugins', 'sqlite-database-integration');
const dropin = readFileSync(join(sqlitePlugin, 'db.copy'), 'utf8')
  .replaceAll('{SQLITE_IMPLEMENTATION_FOLDER_PATH}', slash(sqlitePlugin))
  .replaceAll('{SQLITE_PLUGIN}', 'sqlite-database-integration/load.php');
writeFileSync(join(wp, 'wp-content', 'db.php'), dropin);

mkdirSync(join(lab, 'data'), { recursive: true });
mkdirSync(join(lab, 'private'), { recursive: true });
const salt = () => randomBytes(32).toString('hex');
if (!existsSync(join(wp, 'wp-config.php'))) {
  writeFileSync(join(wp, 'wp-config.php'), `<?php
// Môi trường THỬ cục bộ (SQLite), sinh bởi tests/wp-lab/bootstrap.mjs. Không phải cấu hình thật; không dùng ở staging/production.
define( 'DB_NAME', 'vgarco_lab' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_DIR', '${slash(join(lab, 'data'))}/' );
define( 'DB_FILE', 'vgarco.sqlite' );
${['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'].map((k) => `define( '${k}', '${salt()}' );`).join('\n')}
$table_prefix = 'wp_';
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '${slash(join(lab, 'debug.log'))}' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_HOME', 'http://localhost:8080' );
define( 'WP_SITEURL', 'http://localhost:8080' );
define( 'DISABLE_WP_CRON', true );
define( 'FS_METHOD', 'direct' );
define( 'VGARCO_CF_ZONE', 'a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1' );
define( 'VGARCO_CF_TOKEN', 'lab-token-khong-co-gia-tri' );
define( 'VGARCO_PRIVATE_DIR', '${slash(join(lab, 'private'))}' );
define( 'LAB_DIR', '${slash(lab)}' );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
`);
}
const exts = ['mbstring', 'gd', 'fileinfo', 'zip', 'intl', 'exif', 'curl', 'openssl', 'pdo_sqlite', 'sqlite3', 'gettext', 'sodium'];
const ini = join(lab, 'php.ini');
writeFileSync(ini, [
  ext ? `extension_dir = "${slash(ext)}"` : '',
  ...exts.map((e) => `extension=${e}`),
  'memory_limit = 512M', 'upload_max_filesize = 20M', 'post_max_size = 25M', 'max_execution_time = 120',
  'display_errors = On', 'error_reporting = E_ALL', 'date.timezone = Asia/Ho_Chi_Minh', 'default_charset = "UTF-8"',
].join('\n') + '\n');

// 3. Nối mã của dự án (liên kết, không sao chép: sửa mã là thấy ngay) và plugin giả của môi trường thử
function link(target, path) {
  rmSync(path, { recursive: true, force: true });
  symlinkSync(target, path, 'junction');
}
if (!existsSync(join(repo, 'wp-content', 'themes', 'vgarco', 'dist', 'assets'))) die('theme chưa build: chạy "npm ci && npm run build" trong wp-content/themes/vgarco');
link(join(repo, 'wp-content', 'mu-plugins'), join(wp, 'wp-content', 'mu-plugins'));
link(join(repo, 'wp-content', 'plugins', 'vgarco-core'), join(wp, 'wp-content', 'plugins', 'vgarco-core'));
link(join(repo, 'wp-content', 'themes', 'vgarco'), join(wp, 'wp-content', 'themes', 'vgarco'));
for (const p of readdirSync(join(here, 'plugins'))) link(join(here, 'plugins', p), join(wp, 'wp-content', 'plugins', p));

// 4. Cài đặt và kích hoạt
for (const script of ['install.php', 'setup.php']) {
  const r = spawnSync(php, ['-c', ini, join(here, script)], { encoding: 'utf8' });
  process.stdout.write(r.stdout || '');
  if (r.status !== 0) die(`${script} thất bại:\n${r.stderr || r.stdout}`);
}
console.log('\nXong. Chạy bộ kiểm thử: node tests/wp-lab/run.mjs');

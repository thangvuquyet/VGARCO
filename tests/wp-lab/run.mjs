// Chạy bộ kiểm thử tích hợp trên WordPress thật: bật máy chủ PHP tích hợp, chạy lần lượt các kịch bản, tắt máy chủ.
//
//   PHP=<php.exe> node tests/wp-lab/run.mjs [tên kịch bản...]     ví dụ: node tests/wp-lab/run.mjs site security
//
// Cần dựng môi trường trước: node tests/wp-lab/bootstrap.mjs (README.md).
import { existsSync } from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const lab = join(here, '.lab');
const php = process.env.PHP ?? 'php';
const ini = join(lab, 'php.ini');
// Báo rõ khi không tìm thấy PHP (lỗi hay gặp nhất: chưa đặt biến PHP).
if (spawnSync(php, ['-v']).error) {
  console.error([
    `Không chạy được PHP ("${php}"). Đặt biến PHP trỏ tới php.exe, ví dụ:`,
    '  cmd:        set PHP=C:\\đường\\dẫn\\php.exe',
    '  PowerShell: $env:PHP="C:\\đường\\dẫn\\php.exe"',
    '  bash:       export PHP=/đường/dẫn/php',
  ].join('\n'));
  process.exit(1);
}
if (!existsSync(join(lab, 'wordpress', 'wp-load.php'))) { console.error('Chưa dựng môi trường: chạy node tests/wp-lab/bootstrap.mjs trước'); process.exit(1); }

// Thứ tự có ý nghĩa: nhập dự án → dữ liệu tin/vị trí → các kịch bản dùng dữ liệu đó.
const order = ['env', 'import', 'media', 'seed', 'apply', 'admin', 'site', 'security', 'crawl', 'acffree'];
const only = process.argv.slice(2);
const todo = only.length ? order.filter((n) => only.includes(n)) : order;

const server = spawn(php, ['-c', ini, '-S', 'localhost:8080', '-t', join(lab, 'wordpress'), join(here, 'router.php')], { stdio: 'ignore' });
async function ready() {
  for (let i = 0; i < 50; i++) {
    try { const r = await fetch('http://localhost:8080/wp-login.php'); if (r.status < 500) return true; } catch { /* chưa lên */ }
    await new Promise((r) => setTimeout(r, 200));
  }
  return false;
}

let failed = 0;
try {
  if (!(await ready())) throw new Error('máy chủ PHP không lên được ở localhost:8080 (cổng đang bận?)');
  for (const name of todo) {
    const r = spawnSync(php, ['-c', ini, join(here, `it_${name}.php`)], { encoding: 'utf8', timeout: 600000 });
    const out = ((r.stdout || '') + (r.stderr || '')).trim();
    const tail = out.split('\n').filter((l) => /^(ĐẠT|THẤT BẠI|  ✗)|PHP (Fatal|Warning|Notice)|Fatal error/.test(l)).join('\n');
    const ok = r.status === 0 && (name === 'seed' || /ĐẠT|BỎ QUA/.test(out));
    console.log(`${ok ? 'ĐẠT ' : 'LỖI '} ${name.padEnd(9)} ${ok ? (tail || out.split('\n').pop()) : '\n' + (tail || out).slice(0, 3000)}`);
    if (!ok) failed++;
  }
} finally {
  server.kill();
}
console.log(failed ? `\n${failed} kịch bản lỗi` : '\nTất cả đạt');
process.exit(failed ? 1 : 0);

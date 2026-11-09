import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, utimesSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const script = join(root, 'scripts', 'ops', 'deploy.sh');

const probe = spawnSync('bash', ['--version'], { encoding: 'utf8' });
if (probe.error || !/GNU bash/.test(probe.stdout ?? '')) {
  console.log('BỎ QUA: không có GNU bash để chạy thử script triển khai');
  process.exit(0);
}

const posix = (p) => p.replace(/\\/g, '/').replace(/^([A-Za-z]):/, (_, d) => `/${d.toLowerCase()}`);
const source = readFileSync(script, 'utf8').replace(/\r\n/g, '\n');
let pass = 0;
const fail = [];
const check = (label, ok) => (ok ? pass++ : fail.push(label));
const same = (label, expected, actual) => check(`${label} — mong đợi ${JSON.stringify(expected)}, nhận ${JSON.stringify(actual)}`, JSON.stringify(expected) === JSON.stringify(actual));

check('cú pháp bash hợp lệ (bash -n)', spawnSync('bash', ['-n', posix(script)], { encoding: 'utf8' }).status === 0);

const line = source.match(/^remote "(set -euo pipefail; echo '\$\{COMMIT\}'[^\n]*)"$/m)?.[1];
check('tìm thấy lệnh bước 8 (ghi mã commit và dọn gói hoàn tác)', Boolean(line));

const finish = (count) => {
  const tmp = mkdtempSync(join(tmpdir(), 'vg-deploy-'));
  const site = join(tmp, 'site');
  const rollback = join(site, 'rollback');
  mkdirSync(rollback, { recursive: true });
  for (let i = 0; i < count; i++) {
    const file = join(rollback, `b${i}.tar.zst`);
    writeFileSync(file, 'x');
    const at = new Date(Date.UTC(2026, 10, 1, 0, i));
    utimesSync(file, at, at);
  }
  writeFileSync(join(rollback, 'khac.txt'), 'giữ nguyên');
  const cmd = (line ?? 'false').replaceAll('${COMMIT}', 'abc1234def56').replaceAll('${SITE_DIR}', posix(site)).replaceAll('${ROLLBACK_DIR}', posix(rollback));
  const r = spawnSync('bash', ['-c', cmd], { encoding: 'utf8' });
  const left = readdirSync(rollback).filter((n) => n.endsWith('.tar.zst')).sort();
  const other = existsSync(join(rollback, 'khac.txt'));
  const ref = existsSync(join(site, '.deployed-ref')) ? readFileSync(join(site, '.deployed-ref'), 'utf8') : null;
  rmSync(tmp, { recursive: true, force: true });
  return { status: r.status, left, ref, other };
};

const none = finish(0);
same('máy mới chưa có gói hoàn tác nào: thoát mã 0 (ls không khớp tệp trả mã 2, pipefail làm cả lệnh hỏng)', 0, none.status);
same('máy mới: mã commit vẫn được ghi', 'abc1234def56\n', none.ref);

const three = finish(3);
same('3 gói: thoát mã 0 và giữ cả 3', [0, ['b0.tar.zst', 'b1.tar.zst', 'b2.tar.zst']], [three.status, three.left]);

const five = finish(5);
same('đúng 5 gói: giữ cả 5', [0, 5], [five.status, five.left.length]);

const seven = finish(7);
same('7 gói: giữ 5 gói mới nhất, xóa 2 gói cũ nhất', [0, ['b2.tar.zst', 'b3.tar.zst', 'b4.tar.zst', 'b5.tar.zst', 'b6.tar.zst']], [seven.status, seven.left]);
check('tệp không phải gói hoàn tác không bị xóa', seven.other && none.other);
same('mã commit ghi đúng (có xuống dòng cuối)', 'abc1234def56\n', seven.ref);

if (fail.length) {
  for (const f of fail) console.log(`  ✗ ${f}`);
  console.log(`THẤT BẠI ${fail.length} / ${pass + fail.length}`);
  process.exit(1);
}
console.log(`ĐẠT ${pass}/${pass}`);

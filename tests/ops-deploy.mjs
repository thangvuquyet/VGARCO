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

const run = (args) => {
  const r = spawnSync('bash', [posix(script), ...args], { encoding: 'utf8' });
  return { status: r.status, out: `${r.stdout ?? ''}${r.stderr ?? ''}` };
};
const rb = ['--host=deploy@203.0.113.10', '--domain=vgarco.com.vn', '--yes-production', '--dry-run', '--rollback-to=20261109-143725-fcee06d9e9fe.tar.zst'];

const prodDefault = run(['--env=production', ...rb]);
check('không đặt --smoke-env: kiểm khói theo --env (production)', prodDefault.status === 0 && prodDefault.out.includes('smoke.mjs https://vgarco.com.vn --env=production'));
const stagingDefault = run(['--env=staging', ...rb]);
check('không đặt --smoke-env, --env=staging: kiểm khói theo staging', stagingDefault.status === 0 && stagingDefault.out.includes('smoke.mjs https://vgarco.com.vn --env=staging') && !stagingDefault.out.includes('--env=production'));
const prodAsStaging = run(['--env=production', '--smoke-env=staging', ...rb]);
check('--env=production --smoke-env=staging: kiểm khói theo staging, thư mục đích vẫn là production', prodAsStaging.status === 0 && prodAsStaging.out.includes('smoke.mjs https://vgarco.com.vn --env=staging') && !prodAsStaging.out.includes('smoke.mjs https://vgarco.com.vn --env=production') && prodAsStaging.out.includes('/var/www/vgarco/rollback/'));
const stagingAsProd = run(['--env=staging', '--smoke-env=production', ...rb]);
check('--env=staging --smoke-env=production: kiểm khói theo production', stagingAsProd.status === 0 && stagingAsProd.out.includes('smoke.mjs https://vgarco.com.vn --env=production'));
const bad = run(['--env=production', '--smoke-env=local', ...rb]);
check('--smoke-env=local bị từ chối (mã 2, nói rõ tham số)', bad.status === 2 && bad.out.includes('--smoke-env'));
const empty = run(['--env=production', '--smoke-env=', ...rb]);
check('--smoke-env để trống bị từ chối (mã 2), không lặng lẽ lấy mặc định', empty.status === 2 && empty.out.includes('--smoke-env'));
check('--help có nêu --smoke-env', run(['--help']).out.includes('--smoke-env'));
check('mọi lệnh gọi smoke.mjs trong script (hoàn tác tay, kiểm khói chính, sau hoàn tác tự động) dùng SMOKE_ENV', 3 === (source.match(/smoke\.mjs" "https:\/\/\$\{DOMAIN\}" "--env=\$\{SMOKE_ENV\}"/g) ?? []).length);
check('không còn dòng gọi smoke.mjs nào dùng --env của thư mục đích', !/smoke\.mjs.*--env=\$\{ENV_NAME\}/.test(source));

if (fail.length) {
  for (const f of fail) console.log(`  ✗ ${f}`);
  console.log(`THẤT BẠI ${fail.length} / ${pass + fail.length}`);
  process.exit(1);
}
console.log(`ĐẠT ${pass}/${pass}`);

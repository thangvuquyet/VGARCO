import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, readdirSync, chmodSync, utimesSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const script = join(root, 'scripts', 'ops', 'backup.sh');

const probe = spawnSync('bash', ['--version'], { encoding: 'utf8' });
if (probe.error || !/GNU bash/.test(probe.stdout ?? '')) {
  console.log('BỎ QUA: không có GNU bash để chạy thử script sao lưu');
  process.exit(0);
}

const posix = (p) => p.replace(/\\/g, '/').replace(/^([A-Za-z]):/, (_, d) => `/${d.toLowerCase()}`);
let pass = 0;
const fail = [];
const check = (label, ok) => (ok ? pass++ : fail.push(label));
const same = (label, expected, actual) => check(`${label} — mong đợi ${JSON.stringify(expected)}, nhận ${JSON.stringify(actual)}`, JSON.stringify(expected) === JSON.stringify(actual));

const stubs = {
  restic: `#!/usr/bin/env bash
echo "restic $*" >>"$CALLS"
[[ "\${FAIL_RESTIC:-}" == "$1" ]] && exit 1
exit 0
`,
  mysqldump: `#!/usr/bin/env bash
echo "mysqldump $*" >>"$CALLS"
[[ -n "\${FAIL_DUMP:-}" ]] && exit 1
echo "CREATE TABLE t (id int);"
[[ -z "\${TRUNCATED_DUMP:-}" ]] && echo "-- Dump completed on 2026-10-30"
exit 0
`,
  curl: `#!/usr/bin/env bash
echo "curl $*" >>"$CALLS"
exit 0
`,
  flock: `#!/usr/bin/env bash
[[ -n "\${LOCK_HELD:-}" ]] && exit 1
exit 0
`,
  logger: `#!/usr/bin/env bash
echo "logger $*" >>"$CALLS"
exit 0
`,
};

const make = (opts = {}) => {
  const dir = mkdtempSync(join(tmpdir(), 'vg-backup-'));
  const bin = join(dir, 'bin');
  mkdirSync(bin);
  for (const [name, body] of Object.entries(stubs)) {
    writeFileSync(join(bin, name), body);
    chmodSync(join(bin, name), 0o755);
  }
  const web = join(dir, 'www', 'vgarco', 'public', 'wp-content', 'uploads');
  mkdirSync(web, { recursive: true });
  if (opts.configAbove) writeFileSync(join(dir, 'www', 'vgarco', 'wp-config.php'), '<?php');
  else if (!opts.noConfig) writeFileSync(join(dir, 'www', 'vgarco', 'public', 'wp-config.php'), '<?php');
  if (!opts.noPrivate) mkdirSync(join(dir, 'srv', 'vgarco', 'private'), { recursive: true });
  mkdirSync(join(dir, 'etc-vgarco'));
  mkdirSync(join(dir, 'etc-nginx'));
  const conf = join(dir, 'backup.env');
  if (!opts.noConf) {
    writeFileSync(conf, [
      'RESTIC_REPOSITORY=s3:https://s3.example.test/kho',
      'RESTIC_PASSWORD=mat-khau-thu',
      'AWS_ACCESS_KEY_ID=a',
      'AWS_SECRET_ACCESS_KEY=b',
      'BACKUP_SITES="vgarco"',
      'BACKUP_DBS="vgarco"',
      opts.noHeartbeat ? '' : 'HEARTBEAT_URL=https://hc.example.test/ping/abc',
    ].join('\n'));
  }
  const env = {
    ...process.env,
    PATH: `${posix(bin)}:${process.env.PATH}`,
    CALLS: posix(join(dir, 'calls.txt')),
    VGARCO_BACKUP_CONF: posix(conf),
    VGARCO_BACKUP_LOG: posix(join(dir, 'backup.log')),
    VGARCO_BACKUP_DUMPS: posix(join(dir, 'dumps')),
    VGARCO_BACKUP_LOCK: posix(join(dir, 'lock')),
    VGARCO_WEB_BASE: posix(join(dir, 'www')),
    VGARCO_PRIV_BASE: posix(join(dir, 'srv')),
    VGARCO_ETC: posix(join(dir, 'etc-vgarco')),
    VGARCO_NGINX_ETC: posix(join(dir, 'etc-nginx')),
    VGARCO_BACKUP_DOW: '3',
    ...(opts.env ?? {}),
  };
  const run = (...args) => spawnSync('bash', [posix(script), ...args], { env, encoding: 'utf8' });
  const calls = () => (existsSync(join(dir, 'calls.txt')) ? readFileSync(join(dir, 'calls.txt'), 'utf8').trim().split('\n') : []);
  const log = () => (existsSync(join(dir, 'backup.log')) ? readFileSync(join(dir, 'backup.log'), 'utf8') : '');
  return { dir, run, calls, log };
};

{
  const t = make({ noConf: true });
  const r = t.run();
  same('thiếu tệp cấu hình: thoát mã 2, không chạy gì', [2, []], [r.status, t.calls()]);
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make();
  const r = t.run();
  const c = t.calls();
  same('chạy thường: thoát mã 0', 0, r.status);
  check('mysqldump dùng --single-transaction và --databases đúng CSDL', c.some((l) => /^mysqldump .*--single-transaction .*--databases vgarco$/.test(l)));
  const backup = c.find((l) => l.startsWith('restic backup')) ?? '';
  check('restic backup gắn thẻ, bỏ cache, một hệ tệp', /--tag vgarco --exclude-caches --one-file-system/.test(backup));
  for (const part of ['wp-content/uploads', 'srv/vgarco/private', 'vgarco/public/wp-config.php', 'etc-vgarco', 'etc-nginx', '/dumps']) {
    check(`restic backup gồm ${part}`, backup.includes(part));
  }
  same('lưu giữ 7 ngày, 4 tuần, 3 tháng và dọn kho', true, /^restic forget --tag vgarco --group-by host,tags --keep-daily 7 --keep-weekly 4 --keep-monthly 3 --prune$/.test(c.find((l) => l.startsWith('restic forget')) ?? ''));
  check('thứ tự: dump → backup → forget → heartbeat', c.findIndex((l) => l.startsWith('mysqldump')) < c.findIndex((l) => l.startsWith('restic backup')) && c.findIndex((l) => l.startsWith('restic backup')) < c.findIndex((l) => l.startsWith('restic forget')) && c.findIndex((l) => l.startsWith('restic forget')) < c.findIndex((l) => l.startsWith('curl')));
  check('heartbeat gọi đúng địa chỉ thành công (không có /fail)', c.some((l) => /curl .* https:\/\/hc\.example\.test\/ping\/abc$/.test(l)) && !c.some((l) => l.includes('/fail')));
  check('không kiểm kho ngày thường (thứ Tư)', !c.some((l) => l.startsWith('restic check')));
  const dumps = existsSync(join(t.dir, 'dumps')) ? readdirSync(join(t.dir, 'dumps')) : [];
  check('có tệp CSDL nén và không còn tệp tạm', dumps.some((f) => /^vgarco-\d{4}-\d{2}-\d{2}\.sql\.gz$/.test(f)) && !dumps.some((f) => f.endsWith('.part')));
  check('nhật ký ghi "xong"', t.log().includes('xong'));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ env: { VGARCO_BACKUP_DOW: '7' } });
  t.run();
  check('Chủ nhật: kiểm 5% dữ liệu kho', t.calls().includes('restic check --read-data-subset=5%'));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ env: { FAIL_RESTIC: 'backup' } });
  const r = t.run();
  const c = t.calls();
  check('restic backup hỏng: thoát khác 0', r.status !== 0);
  check('restic backup hỏng: gọi /fail, không gọi heartbeat thành công, không forget', c.some((l) => /curl .*\/ping\/abc\/fail$/.test(l)) && !c.some((l) => /curl .*\/ping\/abc$/.test(l)) && !c.some((l) => l.startsWith('restic forget')));
  check('restic backup hỏng: ghi nhật ký hệ thống và nhật ký riêng', c.some((l) => l.startsWith('logger')) && t.log().includes('LỖI'));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ env: { FAIL_DUMP: '1' } });
  const r = t.run();
  const c = t.calls();
  check('mysqldump hỏng: dừng trước khi sao lưu, báo /fail', r.status !== 0 && !c.some((l) => l.startsWith('restic backup')) && c.some((l) => l.endsWith('/fail')));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ env: { TRUNCATED_DUMP: '1' } });
  const r = t.run();
  const c = t.calls();
  check('bản sao CSDL cụt (thiếu "Dump completed"): bị từ chối, không đưa lên kho, báo /fail', r.status !== 0 && !c.some((l) => l.startsWith('restic backup')) && c.some((l) => l.endsWith('/fail')) && t.log().includes('không đầy đủ'));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ env: { LOCK_HELD: '1' } });
  const r = t.run();
  same('đang có lần chạy khác: thoát 0, không làm gì', [0, []], [r.status, t.calls()]);
  check('đang có lần chạy khác: ghi nhật ký', t.log().includes('đang có một lần sao lưu khác'));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ noHeartbeat: true });
  const r = t.run();
  check('không cấu hình heartbeat: vẫn sao lưu, không gọi curl', r.status === 0 && !t.calls().some((l) => l.startsWith('curl')) && t.calls().some((l) => l.startsWith('restic backup')));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ configAbove: true });
  t.run();
  const backup = t.calls().find((l) => l.startsWith('restic backup')) ?? '';
  check('wp-config.php nằm ngoài public (thư mục cha) vẫn được đưa vào và không có cảnh báo', backup.includes('www/vgarco/wp-config.php') && !t.log().includes('không thấy wp-config.php'));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ noConfig: true });
  const r = t.run();
  check('không có wp-config.php ở đâu cả: cảnh báo trong nhật ký, vẫn sao lưu phần còn lại', r.status === 0 && t.log().includes('không thấy wp-config.php của vgarco') && t.calls().some((l) => l.startsWith('restic backup')));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make({ noPrivate: true });
  const r = t.run();
  const backup = t.calls().find((l) => l.startsWith('restic backup')) ?? '';
  check('thiếu thư mục CV: bỏ qua có ghi nhật ký, vẫn sao lưu phần còn lại', r.status === 0 && !backup.includes('srv/vgarco/private') && t.log().includes('bỏ qua, không thấy'));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make();
  mkdirSync(join(t.dir, 'dumps'));
  const old = join(t.dir, 'dumps', 'vgarco-2020-01-01.sql.gz');
  writeFileSync(old, 'x');
  const past = new Date(Date.now() - 10 * 86400 * 1000);
  utimesSync(old, past, past);
  t.run();
  check('bản sao CSDL cũ hơn 3 ngày bị xóa, bản mới giữ', !existsSync(old) && readdirSync(join(t.dir, 'dumps')).some((f) => f.endsWith('.sql.gz')));
  rmSync(t.dir, { recursive: true, force: true });
}

{
  const t = make();
  t.run('--init');
  t.run('--status');
  same('--init và --status gọi restic init, restic snapshots', ['restic init', 'restic snapshots --compact --latest 5'], t.calls());
  const bad = t.run('--la');
  same('tham số lạ: thoát 2', 2, bad.status);
  rmSync(t.dir, { recursive: true, force: true });
}

if (fail.length) {
  for (const f of fail) console.log(`  ✗ ${f}`);
  console.log(`THẤT BẠI ${fail.length} / ${pass + fail.length}`);
  process.exit(1);
}
console.log(`ĐẠT ${pass}/${pass}`);

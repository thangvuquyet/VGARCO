import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const script = join(root, 'scripts', 'ops', 'provision.sh');

const probe = spawnSync('bash', ['--version'], { encoding: 'utf8' });
if (probe.error || !/GNU bash/.test(probe.stdout ?? '')) {
  console.log('BỎ QUA: không có GNU bash để chạy thử bước ssh của provision.sh');
  process.exit(0);
}

const posix = (p) => p.replace(/\\/g, '/').replace(/^([A-Za-z]):/, (_, d) => `/${d.toLowerCase()}`);
const source = readFileSync(script, 'utf8').replace(/\r\n/g, '\n');
let pass = 0;
const fail = [];
const check = (label, ok) => (ok ? pass++ : fail.push(label));
const same = (label, expected, actual) => check(`${label} — mong đợi ${JSON.stringify(expected)}, nhận ${JSON.stringify(actual)}`, JSON.stringify(expected) === JSON.stringify(actual));

check('cú pháp bash hợp lệ (bash -n)', spawnSync('bash', ['-n', posix(script)], { encoding: 'utf8' }).status === 0);

const block = source.match(/^ssh_listener_pids\(\)[\s\S]*?^ssh_verify_listeners\(\) \{[\s\S]*?^\}$/m)?.[0];
check('tìm thấy bốn hàm kiểm sshd cũ trong provision.sh', Boolean(block));

const tmp = mkdtempSync(join(tmpdir(), 'vg-ssh-'));
const fns = join(tmp, 'fns.sh');
const counter = join(tmp, 'counter');
writeFileSync(fns, block ?? 'false', 'utf8');

const harness = `
set -euo pipefail
SSH_PORT=22
DRY=0
note() { printf '   %s\\n' "$*"; }
run() { "$@"; }
kill() { echo "KILL $*"; }
pgrep() { printf '%s\\n' \${FAKE_PIDS:-}; }
systemctl() { echo "\${FAKE_MAIN:-0}"; }
ssh() {
  local n list m
  n=$(cat "$COUNTER" 2>/dev/null || echo 0)
  echo $((n + 1)) > "$COUNTER"
  IFS=';' read -r -a list <<< "$FAKE_METHODS" || true
  if (( \${#list[@]} )); then m="\${list[$((n % \${#list[@]}))]}"; else m=""; fi
  [[ -n "$m" ]] && echo "debug1: Authentications that can continue: $m" >&2
  echo "Permission denied ($m)." >&2
  return 255
}
source "$FNS"
"$@"
`;

const exec = (call, env = {}) => {
  writeFileSync(counter, '0');
  const r = spawnSync('bash', ['-c', harness, 'x', call], {
    encoding: 'utf8',
    env: { ...process.env, FNS: posix(fns), COUNTER: posix(counter), FAKE_PIDS: '', FAKE_MAIN: '0', FAKE_METHODS: 'publickey', ...env },
  });
  return { status: r.status, out: `${r.stdout ?? ''}${r.stderr ?? ''}` };
};

const kills = (out) => out.split('\n').filter((l) => l.startsWith('KILL ')).map((l) => l.trim());

const a = exec('ssh_refresh_listeners', { FAKE_PIDS: '694\n88161', FAKE_MAIN: '88161' });
same('hai sshd, 88161 là của dịch vụ ssh: chỉ gửi SIGHUP cho 694', [0, ['KILL -HUP 694']], [a.status, kills(a.out)]);
check('thông báo nêu PID tiến trình cũ', a.out.includes('PID 694'));

const b = exec('ssh_refresh_listeners', { FAKE_PIDS: '88161', FAKE_MAIN: '88161' });
same('chỉ một sshd và là của dịch vụ ssh: không gửi tín hiệu nào', [0, []], [b.status, kills(b.out)]);

const c = exec('ssh_refresh_listeners', { FAKE_PIDS: '694\n88161', FAKE_MAIN: '0' });
same('dịch vụ ssh không chạy (MainPID 0): mọi sshd đang nghe đều nhận SIGHUP', [0, ['KILL -HUP 694', 'KILL -HUP 88161']], [c.status, kills(c.out)]);

const d = exec('ssh_refresh_listeners', { FAKE_PIDS: '' });
same('không có sshd nào đang nghe: không làm gì, không lỗi', [0, []], [d.status, kills(d.out)]);

const e = exec('ssh_verify_listeners', { FAKE_METHODS: 'publickey' });
check('10 lần thăm dò đều chỉ có publickey: đạt', e.status === 0 && e.out.includes('10/10'));

const f = exec('ssh_verify_listeners', { FAKE_METHODS: 'publickey;publickey;publickey;publickey,password' });
check('lần thăm dò thứ 4 có password (nửa số kết nối rơi vào sshd cũ): dừng với mã 1, nêu lần thứ mấy', f.status === 1 && f.out.includes('DỪNG') && f.out.includes('4/10'));

const g = exec('ssh_verify_listeners', { FAKE_METHODS: 'publickey,keyboard-interactive' });
check('có keyboard-interactive cũng bị coi là còn cấu hình cũ', g.status === 1 && g.out.includes('DỪNG'));

const h = exec('ssh_verify_listeners', { FAKE_METHODS: '' });
check('không thăm dò được lần nào (ssh không kết nối): không dừng nhưng nói rõ chưa xác nhận được', h.status === 0 && h.out.includes('KHÔNG thăm dò được'));

const i = exec('ssh_verify_listeners', { FAKE_METHODS: ';publickey' });
check('một nửa số lần không kết nối được, nửa còn lại chỉ publickey: đạt với 5/10', i.status === 0 && i.out.includes('5/10'));

check('pgrep tìm đúng tiêu đề tiến trình lắng nghe của sshd', source.includes("pgrep -f '^sshd: .*\\[listener\\]'"));
check('thăm dò bằng phương thức none, bỏ khóa, đúng cổng, không bị fail2ban tính lỗi', /PreferredAuthentications=none -o PubkeyAuthentication=no -o BatchMode=yes[^\n]*\n[^\n]*-p "\$SSH_PORT" deploy@127\.0\.0\.1 true/.test(source));
check('bước ssh gọi ssh_refresh_listeners ngay sau khối nạp lại, trước khi ghi cấu hình fail2ban (cả khi chạy lại không đổi cấu hình)', /\n  fi\n  ssh_refresh_listeners\n  ghi_tep \/etc\/fail2ban\/jail\.d\/vgarco\.local/.test(source));
check('bước ssh kết thúc bằng thăm dò và dừng với mã 1 nếu hỏng; --dry-run chỉ in kế hoạch', /\nEOF\n  if \(\( DRY \)\); then\n    note "\(--dry-run\)[^\n]*\n  else\n    ssh_verify_listeners \|\| exit 1\n  fi\n\}/.test(source));

for (const [label, line] of [
  ['tắt đăng nhập root', 'echo "PermitRootLogin no"'],
  ['tắt mật khẩu', 'echo "PasswordAuthentication no"'],
  ['tắt keyboard-interactive', 'echo "KbdInteractiveAuthentication no"'],
  ['chỉ bật khóa công khai', 'echo "PubkeyAuthentication yes"'],
  ['AllowUsers theo danh sách tính được', 'echo "AllowUsers ${ALLOWED}"'],
  ['tối đa 3 lần thử', 'echo "MaxAuthTries 3"'],
  ['chờ đăng nhập 30 giây', 'echo "LoginGraceTime 30"'],
]) {
  check(`cấu hình sshd do provision.sh sinh: ${label}`, source.includes(line));
}

const dry = spawnSync('bash', [posix(script), '--env=production', '--domain=vgarco.com.vn', '--phase=ssh', '--dry-run'], { encoding: 'utf8' });
const dryOut = `${dry.stdout ?? ''}${dry.stderr ?? ''}`;
check('provision.sh --phase=ssh --dry-run: thoát 0, in kế hoạch thăm dò, không chạy thật', dry.status === 0 && dryOut.includes('--dry-run) sẽ thăm dò sshd ở 127.0.0.1:22 10 lần') && !dryOut.includes('KHÔNG thăm dò được'));

rmSync(tmp, { recursive: true, force: true });

if (fail.length) {
  for (const f1 of fail) console.log(`  ✗ ${f1}`);
  console.log(`THẤT BẠI ${fail.length} / ${pass + fail.length}`);
  process.exit(1);
}
console.log(`ĐẠT ${pass}/${pass}`);

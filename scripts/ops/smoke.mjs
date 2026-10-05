// Kiểm tra nhanh một website VGARCO đang chạy (sau triển khai, sau cập nhật, trước nghiệm thu).
//
//   node scripts/ops/smoke.mjs <địa-chỉ-gốc> [--env=local|staging|production]
//   node scripts/ops/smoke.mjs https://vgarco.vn --env=production
//   node scripts/ops/smoke.mjs http://localhost:8080 --env=local        (phòng thử tests/wp-lab)
//
// Chỉ ĐỌC (GET/HEAD), không gửi hồ sơ, không đăng nhập. Mỗi lần chạy xin MỘT mã bảo mật (tính vào giới hạn 30 lượt/giờ/IP).
// Mã thoát: 0 = mọi phép kiểm đạt; 1 = có phép kiểm hỏng; 2 = dùng sai lệnh.
//
// --env=production thêm: HTTPS, header bảo mật của Nginx, cho lập chỉ mục, http:// chuyển sang https://.
// --env=staging và local: phải có noindex (staging không bao giờ được xuất hiện trên Google).

const args = process.argv.slice(2);
const base = args.find((a) => !a.startsWith('--'))?.replace(/\/$/, '');
const env = (args.find((a) => a.startsWith('--env='))?.slice(6)) ?? 'local';
if (!base || !/^https?:\/\//.test(base) || !['local', 'staging', 'production'].includes(env)) {
  console.error('Cách dùng: node scripts/ops/smoke.mjs <địa-chỉ-gốc> [--env=local|staging|production]');
  process.exit(2);
}

const results = [];
const check = (label, ok, detail = '') => results.push({ label, ok: Boolean(ok), detail });
const get = async (path, opts = {}) => {
  try {
    const r = await fetch(base + path, { redirect: 'manual', signal: AbortSignal.timeout(20000), ...opts });
    return { status: r.status, headers: r.headers, text: opts.method === 'HEAD' ? '' : await r.text() };
  } catch (e) {
    return { status: 0, headers: new Headers(), text: '', error: String(e.cause?.code ?? e.message) };
  }
};
const json = (s) => { try { return JSON.parse(s); } catch { return null; } };

// 1. Trang công khai
const pages = { '/': 'VGARCO', '/gioi-thieu/': '', '/linh-vuc-hoat-dong/': '', '/du-an/': '', '/tin-tuc/': '', '/tuyen-dung/': '', '/lien-he/': '', '/chinh-sach-bao-ve-du-lieu/': '' };
for (const [path, needle] of Object.entries(pages)) {
  const r = await get(path);
  check(`trang ${path} trả 200`, r.status === 200 && r.text.includes(needle) && /<h1[\s>]/.test(r.text), r.status ? `HTTP ${r.status}` : r.error);
}

// 2. 404 thật (không phải 200 giả)
const nf = await get('/trang-khong-ton-tai-' + Date.now() + '/');
check('trang không có trả 404 thật', nf.status === 404, `HTTP ${nf.status}`);
check('trang 404 có noindex', /<meta name=["']robots["'][^>]*noindex/i.test(nf.text));

// 3. REST của VGARCO
const s = await get('/wp-json/vgarco/v1/search?q=' + encodeURIComponent('dự án') + '&lang=vi');
check('tìm kiếm REST trả JSON có mảng results', s.status === 200 && Array.isArray(json(s.text)?.results), `HTTP ${s.status}`);
const sBad = await get('/wp-json/vgarco/v1/search?q=abc');
check('tìm kiếm thiếu lang bị từ chối (400)', sBad.status === 400, `HTTP ${sBad.status}`);
const t = await get('/wp-json/vgarco/v1/apply/token');
const tj = json(t.text);
check('xin mã bảo mật trả token và ảnh', t.status === 200 && typeof tj?.token === 'string' && /^data:image\/png;base64,/.test(tj?.image ?? ''), `HTTP ${t.status}`);
check('mã bảo mật không được cache', /no-store|no-cache|private/i.test(t.headers.get('cache-control') ?? ''), t.headers.get('cache-control') ?? '(không có header)');

// 4. Bề mặt tấn công đã đóng
const x = await get('/xmlrpc.php', { method: 'POST', body: '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>' });
check('xmlrpc.php không trả danh sách phương thức', x.status !== 200 && !x.text.includes('methodResponse'), `HTTP ${x.status}`);
const a = await get('/?author=1');
check('/?author=1 không chuyển hướng sang trang tác giả', a.status === 404 || (a.status >= 300 && a.status < 400 && !/\/author\//.test(a.headers.get('location') ?? '')), `HTTP ${a.status} ${a.headers.get('location') ?? ''}`);
const u = await get('/wp-json/wp/v2/users');
check('danh sách người dùng REST không lộ cho khách', !(u.status === 200 && Array.isArray(json(u.text)) && json(u.text).length), `HTTP ${u.status}`);
// readme.html và license.txt có sẵn trong gói WordPress (lộ phiên bản): Nginx phải chặn ở staging/production; phòng thử local không có Nginx nên bỏ qua.
const exposed = ['/wp-config.php', '/.git/config', '/.env', '/wp-content/debug.log', '/wp-content/uploads/', ...(env === 'local' ? [] : ['/readme.html', '/license.txt'])];
for (const path of exposed) {
  const r = await get(path);
  const leaked = r.status === 200 && (path === '/wp-content/uploads/' ? /Index of/i.test(r.text) : path === '/readme.html' || path === '/license.txt' ? /WordPress/.test(r.text) : r.text.length > 0);
  check(`${path} không lộ nội dung`, !leaked, `HTTP ${r.status}`);
}

// 5. Theo môi trường
const home = await get('/');
const noindex = /<meta name=["']robots["'][^>]*noindex/i.test(home.text);
if (env === 'production') {
  check('production: trang chủ cho lập chỉ mục (không noindex)', !noindex);
  check('production: dùng HTTPS', base.startsWith('https://'));
  const h = home.headers;
  check('production: HSTS', /max-age=\d{6,}/.test(h.get('strict-transport-security') ?? ''), h.get('strict-transport-security') ?? '(thiếu)');
  check('production: X-Content-Type-Options: nosniff', /nosniff/i.test(h.get('x-content-type-options') ?? ''));
  check('production: chống nhúng khung (X-Frame-Options hoặc CSP frame-ancestors)', Boolean(h.get('x-frame-options')) || /frame-ancestors/.test(h.get('content-security-policy') ?? ''));
  check('production: Referrer-Policy', Boolean(h.get('referrer-policy')));
  check('production: không lộ phiên bản PHP/WordPress', !/php\//i.test(h.get('x-powered-by') ?? '') && !/generator.*WordPress/i.test(home.text));
  if (base.startsWith('https://')) {
    const plain = await get('/', { method: 'GET' }).catch(() => null);
    const http = await fetch(base.replace('https://', 'http://') + '/', { redirect: 'manual', signal: AbortSignal.timeout(15000) }).catch(() => null);
    check('production: http:// chuyển sang https://', !http || (http.status >= 300 && http.status < 400 && /^https:/.test(http.headers.get('location') ?? '')) || http.status === 0, http ? `HTTP ${http.status}` : 'không kết nối được cổng 80 (chấp nhận)');
    void plain;
  }
} else {
  check(`${env}: trang chủ có noindex (không lên Google)`, noindex);
}
check('không tải phông/script từ miền ngoài (Google Fonts...)', !/(fonts\.googleapis|fonts\.gstatic|googletagmanager|google-analytics)/.test(home.text));

// Kết quả
const width = Math.max(...results.map((r) => r.label.length));
for (const r of results) console.log(`${r.ok ? 'ĐẠT ' : 'HỎNG'}  ${r.label.padEnd(width)}  ${r.ok ? '' : r.detail}`);
const bad = results.filter((r) => !r.ok).length;
console.log(`\n${bad ? `${bad} phép kiểm hỏng` : 'Tất cả đạt'} (${results.length} phép kiểm, ${base}, môi trường ${env})`);
process.exit(bad ? 1 : 0);

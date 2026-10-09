# Kiểm tra trên trình duyệt (tay hoặc bằng cửa sổ trình duyệt nhúng)

Những gì `tests/php` và `tests/wp-lab` **không** kiểm được vì cần một trình duyệt thật: bố cục ở nhiều độ rộng (kể cả tiếng Đức) và thao tác JS (nút dừng,
trình chiếu ảnh, xem phóng to, sao chép liên kết, lọc tuyển dụng, hộp thoại tìm kiếm, mã bảo mật). Cách làm: bật máy chủ thử (`node tests/wp-lab/run.mjs` bật rồi tắt
ngay; để giữ lại, chạy tay `php -c tests/wp-lab/.lab/php.ini -S localhost:8080 -t tests/wp-lab/.lab/wordpress tests/wp-lab/router.php`), mở `http://localhost:8080`,
dán nội dung tệp vào Console (F12) rồi gọi hàm.

## Cách nạp và gọi từng tệp

Các tệp `.js` ở đây không có chú thích đầu tệp; cách dùng nằm ở đây. Mỗi tệp gắn hàm vào `window`: dán nội dung tệp vào Console (F12) của một trang ở http://localhost:8080, hoặc nạp qua máy chủ công cụ
(`node tests/wp-lab/compare/collector.mjs`, cổng 9100): `await fetch('http://127.0.0.1:9100/b/<tên-tệp>.js').then(r => r.text()).then(t => (0, eval)(t));`.
Mỗi trang được nạp trong một iframe rộng đúng W px (media query theo độ rộng của iframe). Mỗi lệnh nên dưới ~45 giây (cửa sổ trình duyệt nhúng giới hạn thời gian một lệnh).

**`axe-check.js`** — axe-core trên trang thật. Cần `node tests/serve.mjs 4174` (phục vụ `/axe/axe.min.js`). Bộ quy tắc WCAG 2.0/2.1/2.2 A và AA + best-practice như `tests/a11y.md`; `.gallery-watermark` (chữ trang trí `aria-hidden`) được loại ra.

```js
await vgAxe( [ '/', '/gioi-thieu/', '/du-an/', '/tin-tuc/', '/tuyen-dung/' ], [ 1440, 768, 390 ] );   // mảng { url, w, n (số vi phạm), v (mô tả) }; n === 0 ở mọi dòng là đạt
await vgAxe( [ '/?lab_lang=de', '/tuyen-dung/?lab_lang=de' ], [ 1440, 390 ] );                       // giao diện tiếng Đức
await vgAxeState( 'hộp thoại tìm kiếm', '/', 1440, async ( d, w ) => { /* thao tác trên d (document của iframe), w (window) */ } );
```

**Phép đối chứng bắt buộc** khi đổi cách chạy: chèn lỗi cố ý rồi chắc chắn bộ chạy báo được. Một lần chạy "0 vi phạm" mà chưa từng thấy nó báo lỗi thì chưa chứng minh được gì.

```js
await vgAxeState( 'đối chứng', '/', 1440, ( d ) => d.querySelector( 'main' ).insertAdjacentHTML( 'beforeend', '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" width="40" height="40">' ) );   // phải ra image-alt(critical)
```

**`layout-check.js`** — bố cục (xem mục "Bố cục" bên dưới): `vgLayout( W, urls, lang )` và `vgLayoutDiff( de, vi )`. Bỏ qua chữ `.sr-only`, phần ẩn và dải đối tác chạy ngang (tràn theo thiết kế).

**`structure-check.js`** — số cột thật của mọi khối lưới/flex ở nhiều độ rộng; chạy ở cả theme (http://localhost:8080) và bản thiết kế HTML (`node server.cjs` → http://localhost:4173) rồi so hai bên.

```js
const W = [ 600, 601, 700, 701, 800, 801, 900, 901, 1000, 1001, 1180, 1181 ];
await vgSweep( [ [ 'tin-tuc', '/tin-tuc/' ] ], W );              // mỗi độ rộng một dòng: các khối lưới/flex và số cột (g150/298/210 = lưới 3 cột); "!!SCROLL" = cả trang cuộn ngang được
await vgSweep( [ [ 'news', '/news.html' ] ], W, 700 );           // cùng trang ở bản thiết kế (trang nạp bằng JS cần chờ lâu hơn: 700 ms)
await vgCramped( [ [ 'tin-tuc', '/tin-tuc/?lab_lang=de' ] ], [ 601, 701, 768, 801 ] );   // đoạn chữ từ 3 dòng mà dưới 24 ký tự/dòng
```

Cách đọc: tìm độ rộng mà hai bên đổi số cột khác nhau; khác biệt có chủ đích thì ghi lại, còn lại sửa theme theo bản thiết kế (biến `$bp-*` trong `src/scss/_mixins.scss`).
Chữ trên khối `a.nav-next` ở trang tin hiện ra dưới 24 ký tự/dòng nhưng là nhãn + tiêu đề trên hai dòng riêng: dương tính giả.

**`reduced-motion-check.js`** — mô phỏng "giảm chuyển động" (viết lại truy vấn media trong CSS + `matchMedia` giả, ghi trang vào iframe cùng nguồn). Chỉ là mô phỏng: không thay cho thiết lập thật của hệ điều hành.

```js
await vgReduced( '/' );                                                 // `loi` rỗng là đạt
await vgReduced( '/du-an/bao-tang-lich-su-quan-doi-nhan-dan-lao/' );
await vgReduced( '/', 1440, false );                                    // đối chứng: KHÔNG giảm chuyển động; phải thấy chuyenCanh và hoatAnh khác không
```

**`tab-walk.js`** — duyệt bằng phím Tab THẬT và ghi từng điểm dừng (tên, vòng tiêu điểm, có nằm trong khung nhìn không). Phím phải là phím thật (`computer { action: 'key', text: 'Tab', repeat: N }`), không phải `dispatchEvent`: `:focus-visible` chỉ đúng với tiêu điểm do bàn phím.

```js
document.documentElement.style.setProperty( 'scroll-behavior', 'auto', 'important' );   // cửa sổ ẩn không chạy cuộn mượt: không đặt thì điểm dừng sau lượt cuộn bị báo "ngoài khung nhìn" giả
vgTabStart();      // bắt đầu ghi; rồi bấm Tab N lần bằng phím thật
vgTabReport();     // { soDiemDung, dung, loi }; loi rỗng là đạt
```

Báo lỗi khi: điểm dừng không có tên truy cập; có `:focus-visible` mà không có vòng tiêu điểm; phần tử ẩn/0 kích thước nhận tiêu điểm; tiêu điểm đứng lại ở một phần tử (bẫy bàn phím); điểm dừng nằm ngoài khung nhìn mà trang không cuộn theo.

## Bố cục: `layout-check.js`

```js
const urls = ['/', '/gioi-thieu/', '/du-an/', '/tuyen-dung/vt-kts/', '/lien-he/'];
const de = await vgLayout(390, urls, 'de');   // giao diện tiếng Đức (plugin lab-lang, tham số ?lab_lang=de)
const vi = await vgLayout(390, urls, '');
vgLayoutDiff(de, vi);                         // chỉ lỗi xuất hiện riêng ở tiếng Đức
```

Chạy ở 360, 390, 768, 1024, 1440, 1920, 2560 px (đặc tả 5.2 #2). Báo "TRÀN NGANG" (cả trang cuộn ngang được) và chữ rộng hơn hộp chứa (chữ tràn hay đè lên chữ khác).
Mỗi lần dán một độ rộng rồi chạy trong vòng ~45 giây (cửa sổ trình duyệt nhúng giới hạn thời gian một lệnh).

Hai kết quả "lỗi" được chấp nhận: mục cuối đường dẫn vị trí của trang chi tiết dự án (cắt bằng dấu "…" theo thiết kế, cả hai ngôn ngữ) và mũi tên menu xoay 45° (hình học, vượt 3 px ở 1440+).

## Cấu trúc bố cục so với bản thiết kế: `structure-check.js`

`layout-check.js` chỉ bắt chữ tràn; nó **không** bắt được cột chữ bị bóp hẹp hay trang đổi số cột sớm/muộn hơn bản thiết kế. `structure-check.js` làm việc đó: `vgSweep` in số cột thật của mọi khối lưới/flex
ở nhiều độ rộng (chạy ở cả theme và bản thiết kế HTML `node server.cjs` → http://localhost:4173, rồi so hai bên), `vgCramped` liệt kê đoạn chữ dưới 24 ký tự/dòng. Cách dùng ở mục "Cách nạp và gọi từng tệp" bên dưới.

Bản thiết kế **không** đổi bố cục cùng một độ rộng ở mọi trang (Tin tức 800 px, chi tiết vị trí 900 px, Tuyển dụng 600 px, Dự án 1000 px); theme dùng các biến `$bp-*` trong `src/scss/_mixins.scss` cho từng trang.
Kiểm ngày 05/10/2026 ở 600–1181 px (cả VI và DE):

| Trang | Phát hiện | Xử lý |
|---|---|---|
| Tin tức, danh sách | Từ 701 px theme chia 3 cột (ảnh 150, chữ, ngày 210) trong khi bản thiết kế chỉ chia ở 801 px: ở 701 px cột chữ chỉ còn **198 px (15 ký tự/dòng)**, ở 768 px 265 px | Sửa: 601–800 px dùng hàng gọn như bản thiết kế (ảnh 96 × 74 bên trái, ngày dưới đoạn chữ), 3 cột từ 801 px (`$bp-news`) |
| Tin tức, chi tiết | Đầu bài chia 2 cột từ 701 px → tiêu đề còn 419 px (3 dòng, 15 ký tự/dòng) | Sửa: 1 cột tới 800 px (`$bp-news`) |
| Chi tiết vị trí | Nút "Ứng tuyển" nằm cạnh tiêu đề từ 701 px; tiêu đề tiếng Đức dài (60 ký tự) thành 4 dòng ở 701–800 px | Sửa: xếp dưới tiêu đề tới 900 px (`$bp-job`), như bản thiết kế |
| Tuyển dụng, danh sách | Ở 601–700 px theme xếp 1 cột, bản thiết kế đã chia (số thứ tự + nội dung; ô tìm kiếm 2 cột) | Giữ nguyên: không có chữ bị bóp, chỉ là dạng điện thoại thêm 100 px |
| Tin tức, chi tiết (khung thân bài) | Bản thiết kế chia 2 cột (cột chia sẻ 120 px) từ 801 px nhưng **tự nó cuộn ngang 29 px** ở 801–1050 px | Giữ nguyên của theme (1 cột tới 1000 px, không cuộn ngang) |
| Tin tức, danh sách ở 801–900 px | Tiêu đề tiếng Đức rất dài (90+ ký tự) ra 5 dòng ở cột chữ 298 px | Giữ nguyên (đúng bản thiết kế; tiêu đề thực tế ngắn hơn, tách từ tự động đang bật) |

## Trợ năng trên trang thật: `axe-check.js`

axe-core chạy trên trang thật của website thử ở nhiều độ rộng, cả tiếng Đức và các trạng thái động (hộp thoại tìm kiếm, menu di động, biểu mẫu báo lỗi, hộp xem ảnh).
Cần thêm `node tests/serve.mjs 4174` (phục vụ `/axe/axe.min.js`). Cách dùng và **phép đối chứng bắt buộc** ở mục "Cách nạp và gọi từng tệp" bên dưới; kết quả ngày 05/10/2026 ở `tests/a11y.md`.

## Thao tác JS

Đã kiểm ngày 05/10/2026 bằng cách gọi trực tiếp trong trang (chọn phần tử theo thuộc tính `data-*` trong `src/js/modules/`):

| Trang | Thao tác | Kỳ vọng |
|---|---|---|
| `/` | bấm nút "Tạm dừng chuyển động" hai lần | nhãn đổi "Tiếp tục chuyển động" rồi trở lại; `aria-pressed` đổi; `body.is-paused` bật/tắt |
| chi tiết dự án | `[data-gallery-next]`, chấm, phím Home/End/←/→ trên `[data-gallery-stage]`, vuốt (`pointerType: 'touch'` kéo ≥ 50 px) | số ảnh "Ảnh n / m: chú thích" đổi; vuốt ngắn (< 50 px) không đổi |
| chi tiết dự án | bấm `[data-gallery-open]`, rồi phím **Esc thật** | hộp xem ảnh mở có chú thích; Esc đóng; tiêu điểm trở về nút mở |
| chi tiết tin | bấm `[data-copy-link]` (thay `navigator.clipboard`) | chép đúng địa chỉ chuẩn của bài (không kèm `?v=`); thông báo "Đã sao chép liên kết" rồi tự xóa |
| `/tuyen-dung/` | gõ từ khóa vào ô tìm; từ khóa không khớp; "Xóa bộ lọc" | số vị trí đổi tại chỗ không tải lại trang; hiện "Chưa tìm thấy vị trí phù hợp" + "Xóa bộ lọc" + "Xem tất cả vị trí" |
| mọi trang | mở hộp thoại tìm kiếm (`[data-vg-search-open]`), gõ **chữ rồi bấm Esc thật một lần** | tiêu điểm vào ô nhập; < 3 ký tự giữ hướng dẫn; không khớp báo "Chưa có dự án phù hợp"; Esc, nút đóng, bấm nền đều đóng và trả tiêu điểm về nút mở |
| `/ung-tuyen/?id=…` | bấm `[data-captcha-refresh]`; gửi trống | token và ảnh mã mới, ô mã được xóa; mọi trường bắt buộc đánh dấu lỗi, tiêu điểm vào trường đầu, ô đồng ý không tích sẵn |
| `/lien-he/` | gửi trống; email sai; thiếu đồng ý | lỗi đúng trường; ô đồng ý không tích sẵn |

Phím **Esc phải là phím thật** (không dùng `dispatchEvent`): chính vì thế mới tìm ra lỗi hộp thoại tìm kiếm — ô nhập kiểu `search` của Chrome/Edge dùng Esc để *xóa chữ* trước,
nên khi đang có chữ thì Esc đầu tiên không đóng hộp thoại (UC-09 2a). Đã sửa trong `search.js`.

## Chuyển động (theo bản đầu của theme), kiểm ngày 06/10/2026

Trình duyệt nhúng **không vẽ khi cửa sổ ẩn** nên không có sự kiện `scroll` thật, chuyển cảnh CSS không chạy tiếp và `document.hidden` là `true` (trình chiếu hero tự dừng). Cách làm: đọc trạng thái cuối
sau `document.getAnimations().forEach( ( a ) => a.finish() )`; đặt `Object.defineProperty( document, 'hidden', { get: () => false } )` rồi bắn `visibilitychange`; bắn `scroll`/`PointerEvent` bằng tay;
riêng phím Tab dùng phím thật (`:focus-visible` chỉ đúng với tiêu điểm do bàn phím).

| Trang | Thao tác | Kỳ vọng (đã thấy) |
|---|---|---|
| mọi trang | cuộn qua 12 px / về 0 | lớp `is-stuck` bật ở 13 px, tắt khi về đầu; bóng `0 2px 14px` ; chiều cao thanh không đổi |
| mọi trang, ≥ 1181 px | mở một mục menu có con | bảng con có hoạt ảnh `nav-panel-in` 0,16 s (mờ dần, nhích lên 6 px) rồi `opacity: 1`, `transform: none`; đóng lại thì `hidden` |
| mọi trang, ≤ 1180 px | bấm nút menu rồi bấm lần nữa | mở: menu `transform: none`, `visibility: visible`, hai vạch thành X (±45°, ±3,5 px); đóng: còn thấy menu trượt đi, sau 0,25 s `visibility: hidden`, `translateX(100%)`; **không có liên kết nào còn trong thứ tự Tab**; trang không cuộn ngang ở 390, 768, 1180 px |
| mọi trang, ≥ 1181 px | đọc kiểu của `.nav` | `position: static`, `visible`, không dịch chuyển |
| `/` có từ 2 dự án nổi bật | đọc chấm chọn ảnh | mỗi ảnh một chấm (nút 40 × 44 px, góc trên trái khung ảnh, không đè chú thích hay chữ VG), chấm đầu `aria-current` |
| `/` | bấm chấm 3 rồi chấm 1 | ảnh, chú thích và chấm đổi cùng nhau |
| `/` | tự chạy → rê **chuột** vào khung → rời đi | 5,6 s đổi ảnh; rê chuột thì đứng (6,3 s không đổi); rời đi chạy lại |
| `/` | `pointerenter` kiểu **cảm ứng** | KHÔNG dừng (chạm vào ảnh không được làm trình chiếu đứng mãi) |
| `/` | nút dừng chuyển động; bấm chấm khi đang dừng; bấm lại nút | dừng thật; bấm chấm đổi ảnh nhưng không tự chạy lại; tiếp tục thì chạy lại |
| `/` | Tab thật từ liên kết "Từ bản vẽ" tới chấm đầu | tiêu điểm vào chấm (`:focus-visible`), trình chiếu đứng; rời khung thì chạy lại |
| trang có thẻ dự án | đọc kiểu `.project-photo img` | `transform 0.5s cubic-bezier(.2,.7,.3,1)`; quy tắc rê chuột `scale(1.055)`, không phủ màu |
| mọi trang | đọc `scroll-behavior` của `html` | `smooth` (và `auto` khi bật giảm chuyển động) |

### Đợt hai (07/10/2026): thư viện ảnh tự chạy, hero vào, vòng CTA, hàng lĩnh vực

Vì cửa sổ ẩn, **thư viện** được nạp vào một iframe bằng `document.write` sau khi gắn sẵn: `IntersectionObserver` giả (gọi `callback([{ isIntersecting: true }])` bằng tay), `document.hidden` giả (đọc `window.__hidden`),
và với giảm chuyển động thì `matchMedia` giả. Trang thử: `single-du_an-106.html` (2 ảnh) từ `node tests/render-all.mjs` + `node tests/serve.mjs 4174`. Lưu ý: sự kiện `close` của `<dialog>` **không bắn** khi cửa sổ ẩn
(kể cả `<dialog>` mới tạo) — kiểm bằng `dialog.dispatchEvent( new Event( 'close' ) )`; trình duyệt thật vẫn bắn bình thường (kể cả khi nhấn Esc).

| Trang | Thao tác | Kỳ vọng (đã thấy) |
|---|---|---|
| chi tiết dự án có ≥ 2 ảnh | tải; chưa vào khung nhìn → vào khung nhìn | nút tạm dừng hiện (44 px, góc trên trái, không đè nút phóng to, ở 1440/768/390 px); chưa thấy thì không đếm; thấy thì `is-counting` |
| như trên | chờ 6,7 s | tự sang ảnh sau; `data-gallery-status` vẫn rỗng (**không đọc to**); thanh tiến độ `gallery-progress 6.5s`, phần đã trôi trắng đặc trên nền mờ |
| như trên | rê chuột thật vào / ra; chạm kiểu cảm ứng | chuột: đứng 6,7 s không đổi, ra thì chạy lại; chạm: KHÔNG dừng |
| như trên | nút tạm dừng bấm hai lần | `aria-pressed` và nhãn đổi ("Tiếp tục chuyển động" ↔ "Tạm dừng chuyển động"); dừng thì 6,7 s không đổi ảnh |
| như trên | bấm "ảnh tiếp theo" / phím → | đổi ngay, **có** đọc to ("Ảnh 2 / 2: …"), thời gian đếm lại |
| như trên | mở ảnh lớn → đóng (+ rời tiêu điểm) | mở thì dừng; đóng và hết tiêu điểm thì chạy lại. Tiêu điểm bàn phím còn trong thư viện (ví dụ nút phóng to vừa nhận lại tiêu điểm) thì **vẫn dừng** — đúng thiết kế |
| như trên | ra khỏi khung nhìn; thẻ trình duyệt ẩn | dừng; vào lại / hiện lại thì chạy |
| như trên, giả lập giảm chuyển động | tải | nút ở trạng thái "Tiếp tục chuyển động" (`aria-pressed=true`), 6,7 s không đổi; người dùng bấm thì chạy |
| `/` | đọc kiểu `.hero-content h1` và `.hero-frame` | H1 `hero-arrive 0.8s both` (mờ + trượt); khung `hero-arrive-frame 1s 0.1s both` (**chỉ trượt**, không đổi độ trong suốt) |
| `/` | đọc `.cta-circle::after`; thêm lớp `is-paused` vào `<body>` | `cta-ring 2.7s infinite`, viền trắng 50%; có lớp `is-paused` thì `display: none` |
| `/` | đọc `.service-preview-list a::after` | `scaleX(0)` lúc thường, `transition 0.3s`, kẻ đỏ 1 px đè lên đường kẻ dưới; không đổi bố cục |
| chi tiết dự án, trang chủ | axe-core 1440/768/390 | 0 vi phạm |

### Đợt ba (07/10/2026): hiệu ứng nút và liên kết — **đối chiếu từng phần tử với bản thiết kế HTML**

Cách làm (không cần chuột thật, chính xác theo thứ tự ưu tiên của CSS): chép mọi luật CSS của trang vào một thẻ `<style>` phụ với `:hover` đổi thành `.__h` (và `:focus-visible` thành `.__fv`),
rồi gắn lớp `__h` cho phần tử **và mọi tổ tiên** (rê chuột vào con là rê vào cả cha), ép tính kiểu, kết thúc mọi chuyển cảnh bằng `document.getAnimations()` (chỉ loại có `transitionProperty`), rồi so
màu chữ, nền, viền, `transform`, `filter` (cả `::before`/`::after`) trước và sau. Chạy y hệt trên trang mẫu (`node server.cjs` ở thư mục `vgarco-preview`, http://localhost:4173) và trên theme
(trang tĩnh http://127.0.0.1:4174), rồi so hai bản. Hai bản liệt kê luật `:hover`/`transition`/`animation` còn khớp ít nhất một phần tử của trang bằng `document.styleSheets`.

Lỗi lớn nhất tìm ra: **theme thiếu chuyển cảnh chung** — bản thiết kế có `button, a, input, textarea, summary { transition: color .22s, background .22s, border-color .22s }`, theme gần như không có,
nên mọi liên kết và nút đổi màu **giật** thay vì mềm. Sau khi thêm, các phần tử dưới đây khớp bản thiết kế (thời lượng và giá trị cuối):

| Phần tử | Bản thiết kế | Theme sau sửa |
|---|---|---|
| liên kết, nút, ô nhập nói chung | `color/background/border-color 0.22s` | như vậy |
| vòng "Khám phá công trình" | đen → đỏ, xoay 45°, 0,25 s | như vậy |
| mũi tên "Từ bản vẽ" | `rotate(90deg) translateX(6px)`, 0,32 s | như vậy |
| vòng CTA | nền trắng, chữ `#963a34`, `rotate(45deg) scale(1.06)`, 0,28 s | như vậy |
| ảnh hero | `scale(1.018)`, `saturate(1) contrast(1.035)`, 1,1 s / 0,4 s | như vậy (chỉ thiết bị có chuột) |
| ảnh thẻ dự án | `scale(1.035)` 0,6 s | `scale(1.055)` 0,5 s (theo bản theme đầu, đã duyệt) |
| ô đối tác | nền `#f2f3ef`, chữ `#a23b35`, 0,2 s; rê vào dải thì dừng | như vậy |
| nút dừng chuyển động | nền đen, chữ trắng | như vậy |
| hàng lĩnh vực | chữ `#a23b35` | chữ `#a23b35` + đường kẻ đỏ quét (thêm) |
| biểu tượng chân trang | đỏ, nhấc 2 px | như vậy |
| nút tìm kiếm, nút menu, ngôn ngữ, text-link | đỏ / nền đỏ | như vậy |
| vòng mũi tên ở thẻ dự án | nền đỏ, mũi tên trắng, 0,2 s | như vậy |
| nút cuộn xuống (Về công ty), breadcrumb | đỏ | như vậy |
| nút "Xem chi tiết" ở vị trí tuyển dụng | vòng 45 px, rê chuột đổ đỏ | **theme chưa có vòng** — đã thêm (`.job-detail-arrow`), 0,22 s (bản thiết kế đổi tức thì) |
| nút gửi ở Liên hệ | nền `#202321`, chữ trắng | như vậy (trước là hồng nhạt) |
| gạch chân menu | liên kết thường gạch đỏ chạy ra khi rê chuột | như vậy |

Khác bản thiết kế (có chủ ý): liên kết thường ở menu ngang vẫn đổi đỏ khi rê chuột (bản thiết kế chỉ gạch chân); chữ chú thích hero vẫn gạch chân (bản thiết kế đổi đỏ — chữ đỏ trên ảnh tối khó đọc);
nút "Xem chi tiết" có chuyển cảnh 0,22 s; màu đỏ khi rê chuột của một số nút là `#a30000` thay vì `#960000/#9b0000` (chênh vài đơn vị, không nhìn thấy).

Chưa kiểm được: thiết lập "giảm chuyển động" thật của hệ điều hành, màn hình cảm ứng thật, Safari/Firefox/Cốc Cốc, trình đọc màn hình. Chưa nhìn tận mắt chuyển động đang chạy (cửa sổ ẩn); chỉ đo trạng thái đầu/cuối và quy tắc đã biên dịch.

### Đợt bốn (08/10/2026): website thử đã có ảnh thật (`run.mjs demo`), giảm chuyển động (mô phỏng), duyệt phím Tab

Chuẩn bị: `node tests/wp-lab/run.mjs demo` (xem `tests/wp-lab/README.md`), `node tests/wp-lab/compare/collector.mjs` (cổng 9100). Tất cả mã trình duyệt nạp một dòng:
`await fetch('http://127.0.0.1:9100/b/<tên>.js').then(r => r.text()).then(t => (0, eval)(t));` (collector phục vụ mọi tệp `.js` trong thư mục này ở đường dẫn `/b/`).

| Việc | Công cụ | Kết quả |
|---|---|---|
| axe-core trên trang có ảnh thật | `axe-check.js` | 0 vi phạm ở `/`, `/du-an/`, chi tiết dự án có ảnh, chi tiết dự án **không ảnh**, `/linh-vuc-hoat-dong/`, `/tin-tuc/`, `/tuyen-dung/`, `/gioi-thieu/` (mỗi trang ở 2–3 độ rộng trong 390/768/1440 px), cả `/?lab_lang=de` và `/du-an/?lab_lang=de` ở 390 px. **Đối chứng** (chèn `<img>` thiếu alt) phải ra `image-alt(critical)`: đã thấy |
| Bố cục, không ảnh → có ảnh | `layout-check.js` | Không có trang nào cuộn ngang ở 390/768/1440 px. Ba kết quả "lỗi" được chấp nhận: (1) mục cuối đường dẫn vị trí của chi tiết dự án, cắt bằng "…" theo thiết kế; (2) mũi tên menu xoay 45° vượt 3 px ở 1440; (3) **mới:** dấu "+" của hàng lĩnh vực đang mở (`summary` ở `/linh-vuc-hoat-dong/`) vượt 7–9 px — là khung bao của phần tử xoay 45°, chữ không tràn |
| Giảm chuyển động (mô phỏng) | `reduced-motion-check.js` | Trang chủ và chi tiết dự án: `scroll-behavior: auto`, 0 chuyển cảnh, 0 hoạt ảnh, vòng CTA ẩn, dải đối tác đứng, H1 không có hoạt ảnh vào, nút dừng ở trạng thái "đã dừng" (`aria-pressed=true`, `body.is-paused` ở trang chủ). **Đối chứng** (không giảm chuyển động): 88 chuyển cảnh + 4 hoạt ảnh ở trang chủ, 67 + 1 ở chi tiết dự án, cuộn mượt, vòng CTA hiện, nút dừng `aria-pressed=false` |
| Duyệt phím Tab (phím thật) | `tab-walk.js` | Trang chủ 40 điểm dừng, chi tiết dự án 45: thứ tự hợp lý (liên kết bỏ qua → logo → menu → tìm kiếm → ngôn ngữ → nội dung → chân trang → "Về đầu trang" → quay lại liên kết bỏ qua), **mọi điểm dừng có tên truy cập và vòng tiêu điểm nhìn thấy**, không bẫy bàn phím, không phần tử ẩn nhận tiêu điểm; trang tự cuộn theo tiêu điểm. Thư viện ảnh: vùng trình chiếu (có nhãn) → phóng to → tạm dừng → trước → các chấm → sau → danh sách ảnh nhỏ → dự án kế tiếp |
| So kiểu hiển thị website thử ↔ trang tĩnh (1440 px) | `compare/cmpstyles.mjs` | Trang chủ 0/107 phần tử khác, danh mục dự án 0/63, tin tức 0/54: xem `compare/README.md` |

**Mô phỏng giảm chuyển động không thay cho thiết lập thật** của hệ điều hành (Windows: Cài đặt → Trợ năng → Hiệu ứng hình ảnh → tắt "Hiệu ứng hoạt hình"): phần CSS (viết lại truy vấn media) và phần JS (`matchMedia` giả) được đo, nhưng việc trình duyệt thật báo `prefers-reduced-motion: reduce` thì chỉ người dùng thử được.
Khi chạy `tab-walk.js` trong cửa sổ ẩn, đặt `scroll-behavior: auto` trước (xem mục "Cách nạp và gọi từng tệp"), nếu không các điểm dừng sau lượt cuộn mượt bị báo "ngoài khung nhìn" giả.

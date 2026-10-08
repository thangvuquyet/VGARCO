# So website thử (WordPress thật) với trang tĩnh

Hai bên dùng **cùng một theme**; khác nhau ở dữ liệu và ở người dựng trang:

| | Trang tĩnh `127.0.0.1:4174` | Website thử `localhost:8080` |
|---|---|---|
| Dựng bởi | `node tests/render-all.mjs` (PHP + hàm giả trong `tests/php/fixtures.php`) | WordPress thật (SQLite) + plugin `vgarco-core` |
| Dữ liệu | dữ liệu mẫu cố định (`tests/php/data/projects.json`, ảnh thiết kế) | CSDL của phòng thử; muốn giống trang tĩnh thì chạy `node tests/wp-lab/run.mjs demo` |

Khi hai trang trông khác nhau thì nguyên nhân gần như luôn là **dữ liệu** (chưa có ảnh, lĩnh vực, đối tác…), không phải CSS. Hai công cụ dưới đây xác nhận điều đó bằng số liệu.

## Chuẩn bị

```bash
node tests/wp-lab/run.mjs demo          # nạp dữ liệu minh họa (ảnh thật) vào website thử, chạy SAU CÙNG
node tests/render-all.mjs               # dựng lại trang tĩnh (mã băm của CSS đổi sau mỗi lần build theme: trang cũ sẽ mất toàn bộ CSS)
node tests/serve.mjs 4174               # phục vụ trang tĩnh (giữ chạy)
php -c tests/wp-lab/.lab/php.ini -S localhost:8080 -t tests/wp-lab/.lab/wordpress tests/wp-lab/router.php   # website thử (giữ chạy)
```

## 1. Cấu trúc: `structure.php`

```bash
php -d extension=mbstring -d allow_url_fopen=1 tests/wp-lab/compare/structure.php [--verbose]
```

Với 14 cặp trang, so tập "thẻ.lớp" có trong `<main>` (không so số lượng vì số lượng theo dữ liệu; bỏ qua `<img>` vì WordPress tự thêm lớp `attachment-*`).
Thoát 1 nếu còn khác biệt chưa được chấp nhận. Khác biệt do dữ liệu nằm trong hằng `ALLOWED` ở đầu tệp, **mỗi dòng kèm lý do**; thêm vào đó chỉ khi có lý do thật.

Kết quả ngày 08/10/2026 (dữ liệu minh họa): 14/14 cặp khớp, 3 khác biệt được chấp nhận (phạm vi công việc của dự án, chiều bài trước/sau của tin, lớp `wp-block-image` do trình soạn thảo sinh).

## 2. Kiểu hiển thị: `collector.mjs` + `fn.js` + `cmpstyles.mjs`

Đo kiểu tính toán (`getComputedStyle`: phông, cỡ chữ, màu, nền, viền, khoảng cách, lưới…) của từng phần tử có mặt ở cả hai trang rồi in chỗ khác. Cần một trình duyệt thật (cửa sổ nhúng của ứng dụng hoặc Chrome).

```bash
node tests/wp-lab/compare/collector.mjs          # máy chủ nhỏ :9100, ghi kết quả vào tests/wp-lab/compare/out/ (không vào Git)
```

Ở mỗi trang (website thử rồi trang tĩnh), cùng một độ rộng cửa sổ (ví dụ 1440), dán vào Console:

```js
await fetch('http://127.0.0.1:9100/fn.js').then(r => r.text()).then(t => (0, eval)(t));
await collectStyles('lab-home');   // ở website thử: tiền tố lab-; ở trang tĩnh: st-
```

```bash
node tests/wp-lab/compare/cmpstyles.mjs home projects services   # so các cặp lab-<tên> và st-<tên>
```

Kết quả ngày 08/10/2026 ở 1440 px: trang chủ 0/107 phần tử khác, danh mục dự án 0/63, tin tức 0/54; **không có khác biệt kiểu nào do theme**. Hai nhóm còn lại đều do dữ liệu hoặc công cụ dựng trang:

- **Mục menu "hiện hành"** (`button.nav-trigger`, `a(nav.nav)`… đỏ ở trang tĩnh, đen ở website thử): hàm giả trong `tests/php/fixtures.php` đánh dấu *mọi* liên kết menu là trang hiện hành; WordPress thật chỉ đánh dấu đúng một mục. Website thử đúng, trang tĩnh sai.
- **Chi tiết dự án** (`project-detail-head`, `project-gallery-controls`…): rộng khác vì số ảnh thư viện (6 so với 2) và chữ số thứ tự ("n / 19") khác.

## Giới hạn

Hai công cụ chỉ so **trạng thái tĩnh**. Chuyển động (hover, trình chiếu, cuộn) kiểm bằng cách đọc quy tắc đã biên dịch, xem `tests/wp-lab/browser/README.md`; nhìn tận mắt do người dùng thực hiện.

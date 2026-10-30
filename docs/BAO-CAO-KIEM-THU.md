# Báo cáo kiểm thử

Ngày chạy: 30/10/2026. Mã nguồn: nhánh `main` của kho Git (commit ghi ở cuối mục 1). Báo cáo này ghi **kết quả đo được**, không phải cam kết; mục 5 nêu rõ những gì chưa kiểm.

## 1. Tóm tắt

| Nhóm | Số phép kiểm | Kết quả |
|---|---|---|
| Kiểm thử tự động không cần WordPress (PHP, Node) | 4.130 | Đạt hết |
| Kiểm thử trên WordPress 7.1.2 thật, có Secure Custom Fields 6.9.7 và Polylang 3.9.1 (SQLite) | 519 | Đạt hết |
| Kiểm thử đột biến (cố ý phá mã, kiểm thử phải chuyển đỏ) | 473 đột biến được định nghĩa | Xem mục 3 |

Cùng với việc quét toàn bộ lịch sử Git tìm khóa bí mật và địa chỉ nội bộ (sạch, kiểm ngày 30/10) và `npm audit` (0 lỗ hổng).

## 2. Chi tiết theo bộ

**Không cần WordPress** (`node tests/run.mjs`):

| Bộ | Phép kiểm | Kiểm gì |
|---|---|---|
| `smoke.php` | 1.291 | Hàm thuần, cấu trúc 14 nhóm trường, chặn thư ở staging, menu, bộ chuyển ngôn ngữ, trang 404 |
| `pages.php` | 1.381 | Chạy mẫu trang thật rồi phân tích DOM: một H1, ảnh LCP, alt, thoát HTML, SEO, JSON-LD, hộp thoại tìm kiếm |
| `apply.php` | 241 | Nộp hồ sơ: mã bảo mật, tệp CV thật (exe đổi đuôi, DOCX có macro, quá 10 MB), thứ tự mã lỗi, xuất .xlsx, hạn 12 tháng |
| `media.php` | 122 | Gỡ GPS/EXIF/XMP khỏi ảnh, giữ hồ sơ màu, hướng xoay, từ chối tệp hỏng |
| `export-images.php` | 127 | Công cụ xuất ảnh master |
| `import.php` | 143 | Nhập dự án từ bảng tính: kế hoạch, lỗi, chạy lại không đổi |
| `security.php`, `seo.php`, `search.php` | 81, 91, 47 | Khóa đăng nhập, thẻ SEO, chỉ mục tìm kiếm |
| `languages.php` | 110 | Sao chép trường theo ưu tiên dịch, gộp cấu hình bản Đức, nối bản dịch Polylang |
| `serverconf.php` | 47 | Cấu hình Nginx do `provision.sh` sinh: tiêu đề bảo mật, CSP, thứ tự chứng chỉ, bước sao lưu |
| `ops-backup.mjs` | 31 | `backup.sh` với restic và mysqldump giả: thứ tự, lưu giữ, từ chối bản CSDL cụt, báo lỗi, vị trí `wp-config.php` |
| `coexist.php` | 6 | Plugin và theme cùng khai báo `vgarco_option` không xung đột |
| `i18n.mjs`, `gettext.php` | 69, 330 + 13 | Bản dịch tiếng Đức khớp chuỗi gốc, tệp `.mo` đọc đúng |

**Trên WordPress thật** (`node tests/wp-lab/run.mjs`, 12 kịch bản):

| Kịch bản | Phép kiểm | Kiểm gì |
|---|---|---|
| `env` | 24 | Plugin, theme, kiểu nội dung, phân loại, vai trò, nhóm trường, trang cấu hình |
| `import` | 29 | Nhập bằng bảng tính: chạy thử, tạo, chạy lại không đổi, cập nhật, ảnh theo SHA-1 |
| `media` | 65 | Ảnh tải lên qua WordPress thật và HTTP thật |
| `export` | 17 | Công cụ xuất ảnh ↔ importer |
| `apply` | 69 | Nộp hồ sơ qua REST, thư HR và ứng viên, gửi lại khi SMTP hỏng, giới hạn tần suất |
| `admin` | 52 | Vai trò, danh sách và tải CV, xuất .xlsx, xóa, tự xóa 12 tháng, cảnh báo |
| `site` | 108 | Trang công khai: SEO, JSON-LD, tìm kiếm REST, bề mặt tấn công, tiêu đề bảo mật |
| `lang` | 92 | Polylang thật: `/de/`, bộ chuyển ngôn ngữ, `hreflang`, nhập dự án có bản Đức, sao chép trường, cấu hình bản Đức |
| `security` | 32 | Khóa IP và tài khoản, quên mật khẩu không lộ tài khoản, báo quản trị mới |
| `crawl` | 6 | Duyệt mọi liên kết nội bộ: 200, tài nguyên tải được, một H1, không trùng id |
| `smoke` | 25 | Kiểm khói từ ngoài vào (cùng tập kiểm dùng sau khi triển khai) |
| `seed` | — | Tạo dữ liệu cho các kịch bản sau, không kiểm |

Kiểm tra bằng trình duyệt Chromium (CSP không vi phạm trên trang chủ, trang dự án, trang ứng tuyển; một vi phạm cố ý bị chặn đúng) làm tay, ghi ở `tests/wp-lab/browser/README.md`.

## 3. Kiểm thử đột biến

473 đột biến được định nghĩa; công cụ kiểm tra từng đột biến vẫn khớp đúng một chỗ trong mã (0 đã lỗi thời).

- Lần chạy đầy đủ trước đó (29/10/2026) chạy được 303 trong 437 đột biến thì dừng: **303 bắt được, 0 bỏ sót**.
- Các nhóm thêm ngày 30/10/2026 (Polylang 12, cấu hình Nginx 9, sao lưu 15; tổng 36) đã chạy riêng: **36/36 bắt được**. Một đột biến ban đầu lọt (ô bắt buộc ở bản Đức) đã thêm phép kiểm tương ứng rồi bắt được.
- Chưa chạy lại toàn bộ 473 trong một lần.

## 4. Lỗi tìm ra bằng kiểm thử trên WordPress thật

Những lỗi mà bộ kiểm thử bằng hàm giả không thấy, tìm ra và đã sửa:

- Trước đó (lần đầu có WordPress thật): 8 lỗi (đường dẫn phân loại 404, khóa đăng nhập vô hiệu, XML-RPC còn mở, dò tài khoản qua "quên mật khẩu"…).
- Khi thay ACF PRO/WPML bằng Secure Custom Fields/Polylang (30/10/2026): trùng tên trường `doi_tac`; importer ghi đè bài tiếng Việt bằng bản Đức; phân loại rác sinh ra khi nhập bản Đức; bộ chuyển ngôn ngữ dẫn tới địa chỉ bị chuyển hướng; trang chủ tiếng Đức ở `/de/startseite/`; chuyển hướng tới trình hướng dẫn của Polylang ở lần vào quản trị đầu tiên.
- Khi chạy thật lần hai trên máy chủ thử (30/10): `wp-config.php` nằm trong `public/` nên **không được sao lưu** (script tìm ở thư mục cha); cron của Ubuntu bỏ qua `CRON_TZ` trong `/etc/cron.d` (lịch sao lưu chạy lệch 7 giờ); xóa bản cũ của restic chia theo nhóm đường dẫn nên bản cũ nằm mãi; `wp vgarco report translations` báo "đã có bản dịch" khi Polylang chưa bật.
- Khi rà sẵn sàng triển khai: tên gói `modern-image-formats` sai (đúng là `webp-uploads`); thiếu ngôn ngữ `vi` khi chưa bật Polylang làm trang ra `lang="en-US"`; Let's Encrypt kiểu webroot không chạy được vì cổng 80 đóng (chuyển sang Cloudflare Origin CA).

## 5. Chưa kiểm — việc còn lại trước khi nghiệm thu

| Việc | Vì sao chưa |
|---|---|
| Chạy `provision.sh` và `deploy.sh` trên **VPS của khách** | Chưa có VPS. Trên máy chủ thử đã chạy thật ngày 30/10: bước `packages`, `nginx`, `backup`, cỡ theo RAM (cả 4 GB giả), `deploy.sh` đủ 8 bước, kiểm khói 27/27 ở hai chế độ, `wp vgarco import` và `wp vgarco languages` bằng WP-CLI thật. Chưa chạy thật: bước `ssh` và `firewall` sau thay đổi, chứng chỉ Origin CA thật (máy thử dùng chứng chỉ tự ký) |
| Sao lưu tới **kho S3 thật** và biên bản diễn tập chính thức | Đã diễn tập thật ngày 30/10 trên máy chủ thử với restic và MariaDB thật nhưng kho là thư mục cục bộ: sao lưu 1,5 giây, khôi phục 2 giây, CSDL khớp, `wp-config.php` giống bản gốc. Chưa có kho S3 và chưa điền mẫu biên bản mục 5 của `SAO-LUU-PHUC-HOI.md` |
| MariaDB thật cho toàn bộ kịch bản WordPress | Phòng thử dùng SQLite; máy chủ thử đã chạy MariaDB thật cho nhập dự án, sao lưu và khôi phục |
| Giao diện biên tập viên bằng trình duyệt (nút "+" của Polylang, trang Cấu hình và Bản DE, kéo thả trường lặp) | Chưa có người thao tác |
| Hiệu năng trên VPS 2 vCPU/4 GB/40 GB (Lighthouse, tải đồng thời, mức dùng RAM) | Chưa có VPS |
| Cloudflare thật (Full strict, Authenticated Origin Pulls, Cache Rules), Brevo, SPF/DKIM/DMARC, thư không vào thư rác | Chờ tên miền và giá trị DNS |
| 2FA, đổi đường dẫn đăng nhập, FluentSMTP, Redis, Patchstack, giám sát | Plugin chưa cài thử |
| Trình duyệt Safari, Firefox, Cốc Cốc; trình đọc màn hình thật | Xem `tests/a11y.md` |
| Nội dung thật, ảnh thật, bản dịch tiếng Đức đã hiệu đính | Chờ khách và đối tác |
| Nghiệm thu 11 use case trên staging (UC-01…UC-11) | Làm tay sau khi có staging; các phép kiểm tự động có ghi chú UC nằm ở `tests/php/pages.php` |

## 6. Chạy lại

```bash
PHP=… PHP_EXT=… node tests/run.mjs                     # không cần WordPress
PHP=… PHP_EXT=… node tests/mutate.mjs --check          # đột biến còn khớp mã không
PHP=… node tests/wp-lab/bootstrap.mjs wordpress.zip sqlite-database-integration.zip secure-custom-fields.zip polylang.zip
PHP=… node tests/wp-lab/run.mjs                        # trên WordPress thật
node scripts/ops/smoke.mjs https://<tên-miền> --env=production   # sau khi triển khai
```

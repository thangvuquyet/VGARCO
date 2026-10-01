# vgarco-web

Mã nguồn website Công ty TNHH Tư vấn Xây dựng VGARCO — WordPress, song ngữ Việt (`/`) và Đức (`/de/`).

Kho này chỉ chứa mã do nhà thầu viết. Lõi WordPress và plugin bên thứ ba **không** nằm trong Git; danh sách và lý do dùng ở [`plugins.lock.json`](plugins.lock.json).

## Cấu trúc

```
wp-content/
├── mu-plugins/vgarco-loader.php   Luôn chạy: khóa XML-RPC, chặn liệt kê tài khoản, tắt bình luận,
│                                  chặn gửi thư thật ở staging
├── plugins/vgarco-core/           DỮ LIỆU và NGHIỆP VỤ: kiểu nội dung, phân loại, trường ACF, trang cấu hình, ảnh,
│                                  tuyển dụng (mã bảo mật, nộp hồ sơ, quản trị hồ sơ), tìm kiếm, khóa đăng nhập,
│                                  xóa cache Cloudflare, cảnh báo, nhập liệu bằng bảng tính (WP-CLI)
└── themes/vgarco/                 TRÌNH BÀY: mẫu trang, SCSS, JS, chuỗi giao diện, SEO cơ bản
content/                           Bảng tính nhập liệu (xem content/README.md)
scripts/i18n/                      Công cụ dịch: trích .pot, gộp bản dịch .po, biên dịch .mo (Node, không cần gettext)
tests/                             Kiểm thử tự động không cần WordPress (xem bên dưới)
tests/wp-lab/                      Kiểm thử tích hợp trên WordPress thật (SQLite) — dựng bằng bootstrap.mjs
```

Nguyên tắc tách lớp: **kiểu nội dung không bao giờ nằm trong theme.** Đổi hoặc tắt theme thì dự án, tin tức, tuyển dụng vẫn còn nguyên trong trang quản trị.
Mỗi chức năng có nghiệp vụ viết thành **hàm thuần** (`*-lib.php`) — kiểm thử được bằng dữ liệu giả — và một tệp mỏng nối vào WordPress.

| Kiểu nội dung | Đường dẫn VI | Ghi chú |
|---|---|---|
| `du_an` — Dự án | `/du-an/` | Phân loại `loai_cong_trinh` tại `/du-an/loai/<nhóm>/` |
| `linh_vuc` — Lĩnh vực hoạt động | — | Không có trang riêng; hiển thị trên một trang, có mã neo |
| `tin_tuc` — Tin tức | `/tin-tuc/` | Phân loại `chuyen_muc` |
| `vi_tri` — Vị trí tuyển dụng | `/tuyen-dung/<vị-trí>/` | Bộ lọc `noi_lam_viec`, `phong_ban` |
| `ho_so_ut` — Hồ sơ ứng tuyển | — | **Riêng tư**, không URL công khai; chỉ vai trò Nhân sự tuyển dụng và Quản trị viên; CV lưu ngoài thư mục web |

Tin tức dùng kiểu riêng thay vì `post` vì WPML không dịch được phần tiền tố cố định trong đường dẫn của bài viết thường.

## Yêu cầu

- WordPress 6.6+ (khuyến nghị bản 7.x), PHP 8.3 (phần mở rộng `gd`, `zip`, `fileinfo`, `mbstring`, `intl`)
- Plugin trong `plugins.lock.json`. Bắt buộc để nhập dữ liệu: **ACF PRO** (bản miễn phí không có trang cấu hình và trường lặp)
- Node.js 20+ để build CSS/JS của theme
- Cấu hình trong `wp-config.php` (không commit): `VGARCO_PRIVATE_DIR` (nơi lưu CV, **ngoài** thư mục web), `VGARCO_CF_ZONE` và `VGARCO_CF_TOKEN`
  (xóa cache Cloudflare), tùy chọn `VGARCO_TELEGRAM_TOKEN` và `VGARCO_TELEGRAM_CHAT` (cảnh báo)

## Build giao diện

```bash
cd wp-content/themes/vgarco
npm install
npm run fonts    # chép phông chữ từ gói Fontsource vào src/fonts/ và sinh src/scss/_fonts.scss
npm run build    # sinh dist/ (không commit)
npm run dev      # build lại mỗi khi sửa src/
```

PHP đọc `dist/.vite/manifest.json` để nạp tệp CSS/JS có mã băm (`inc/enqueue.php`). Chưa build thì trang vẫn chạy nhưng không có kiểu dáng; quản trị viên thấy ghi chú trong mã nguồn trang.

## Kiểm thử

Không cần WordPress; cần PHP 8.3 CLI (bật `mbstring`; `gd`, `fileinfo`, `zip`, `gettext` cho một số bộ) và Node.

```bash
PHP=/đường/dẫn/php PHP_EXT=/đường/dẫn/ext node tests/run.mjs            # toàn bộ: cú pháp, các bộ kiểm thử, build
PHP=... PHP_EXT=... node tests/run.mjs --mutate                          # thêm kiểm thử đột biến (phá mã có chủ đích, xác nhận kiểm thử chuyển đỏ)
PHP=... PHP_EXT=... node tests/mutate.mjs "khóa đăng nhập"               # chỉ chạy các đột biến có tên chứa chuỗi đó
```

| Bộ | Kiểm gì |
|---|---|
| `tests/php/smoke.php` | Hàm thuần, cấu trúc trường ACF, chặn thư ở staging, menu, bộ chuyển ngôn ngữ, đoán mục của trang 404 |
| `tests/php/pages.php` | Chạy mẫu trang THẬT (`render.php` + hàm WordPress giả) rồi phân tích DOM: đúng một H1, ảnh LCP, alt, trường trống tự ẩn, thoát HTML, SEO, JSON-LD, hộp thoại tìm kiếm, trang lỗi… |
| `tests/php/apply.php` | Luồng nộp hồ sơ: mã bảo mật, kiểm tra tệp CV bằng **tệp thật** (exe đổi đuôi, DOCX có macro, quá 10 MB), thứ tự 403/429/400/409/500, xuất .xlsx, hạn 12 tháng |
| `tests/php/media.php` | Gỡ siêu dữ liệu ảnh (GPS, EXIF, XMP, IPTC) khỏi JPEG và PNG bằng ảnh GD thật có chèn EXIF giả: không đổi một byte dữ liệu ảnh, giữ hồ sơ màu, đọc hướng xoay, từ chối tệp hỏng |
| `tests/php/search.php`, `seo.php`, `security.php`, `import.php` | Tìm kiếm bỏ dấu, SEO (thoát ký tự, JSON-LD), khóa đăng nhập và cache Cloudflare, đọc CSV và kế hoạch nhập liệu |
| `tests/i18n.mjs`, `tests/php/gettext.php` | Công cụ dịch và trạng thái bản dịch (`.pot` mới, `.po` đủ mục, `.mo` khớp `.po`), đọc `.mo` bằng libintl độc lập |

Kiểm tra trên trình duyệt (không nằm trong `run.mjs`): `node tests/render-all.mjs` rồi `node tests/serve.mjs 4174`; quy trình axe-core ở [`tests/a11y.md`](tests/a11y.md).

Các bộ trên dùng hàm WordPress **giả** nên không thấy lỗi chỉ lộ khi WordPress thật xử lý. Vì vậy có thêm bộ kiểm thử tích hợp chạy trên **WordPress 7.1 thật** (SQLite, không cần MySQL):

```bash
PHP=... PHP_EXT=... node tests/wp-lab/bootstrap.mjs wordpress.zip sqlite-database-integration.zip   # một lần
PHP=... node tests/wp-lab/run.mjs                                                                   # 10 kịch bản, ~340 phép kiểm
```

Hướng dẫn, danh sách kịch bản và **giới hạn** (ACF PRO/WPML là bản giả, không có Nginx/Cloudflare/trình duyệt khác Chromium) ở [`tests/wp-lab/README.md`](tests/wp-lab/README.md).
Bộ này đã tìm ra 8 lỗi mà các bộ giả không thấy (đường dẫn phân loại 404, khóa đăng nhập vô hiệu, XML-RPC còn mở, dò tài khoản qua "quên mật khẩu"...).

## Dịch

Chuỗi gốc bằng tiếng Việt, bọc gettext. Sau khi thêm/sửa chuỗi:

```bash
node scripts/i18n/sync.mjs     # trích .pot → gộp vào de_DE.po (giữ bản sửa tay) → biên dịch .mo
node scripts/i18n/sync.mjs --check
```

Bản tiếng Đức hiện là **bản NHÁP** (mồi từ từ điển bản thiết kế HTML và `scripts/i18n/de-draft.json`), chờ đối tác Đức hiệu đính trước khi công bố.
Chuỗi mới phải có bản dịch (thêm vào `de-draft.json` hoặc sửa thẳng `de_DE.po`) — `tests/i18n.mjs` báo đỏ nếu thiếu.

## Quy ước

- **Mọi chuỗi hiển thị** bọc gettext, text domain `vgarco` (theme) hoặc `vgarco-core` (plugin); chuỗi gốc bằng tiếng Việt.
- **Không viết cứng** số điện thoại, địa chỉ, email vào mẫu trang: đọc qua `vgarco_option()` từ trang *Cấu hình VGARCO*.
- **Không thêm `!important`.** Độ ưu tiên CSS quản lý bằng `@layer` (`reset, tokens, base, components, pages, utilities`).
- **Không jQuery** ở front-end; JS là module ES thuần, nạp theo loại trang; chức năng chính hoạt động khi chưa có JS (liên kết thật, biểu mẫu GET, `<noscript>`).
- Màu chữ trên nền phải đạt tương phản WCAG 2.1 AA (4,5 : 1). Token `--c-line` (1,32 : 1) chỉ dùng để trang trí, không dùng làm viền ô nhập liệu.
- Thêm nghiệp vụ mới: viết hàm thuần + kiểm thử bằng dữ liệu thật trước, thêm đột biến vào `tests/mutate.mjs` để chắc kiểm thử thật sự bắt lỗi.
- Tệp giấy phép thương mại (`*.zip`), khóa API, mật khẩu: **không bao giờ commit** (đã chặn trong `.gitignore`).

## Trạng thái

Đã viết và kiểm thử bằng máy: 13 màn hình + hộp thoại tìm kiếm + 404 + trang pháp lý, tuyển dụng (giao diện, nộp hồ sơ, quản trị hồ sơ, thư, xuất .xlsx, tự xóa 12 tháng),
tìm kiếm dự án, SEO cơ bản, bản dịch tiếng Đức (nháp), bộ chuyển ngôn ngữ, khóa đăng nhập, xóa cache Cloudflare, nhập liệu bằng bảng tính, trợ năng (axe-core: 0 lỗi).

Chưa làm: script dựng máy chủ (`provision.sh`, `deploy.sh`), kho Git từ xa, tên miền/hosting, cấu hình Nginx/Cloudflare/Brevo/sao lưu.

Đã chạy thử trên WordPress 7.1 thật (SQLite; `tests/wp-lab`): kích hoạt, kiểu nội dung và phân loại, nhập liệu bằng bảng tính (lõi `VGARCO_CLI_Command`, WP-CLI giả), REST nộp hồ sơ và tìm kiếm,
vai trò `vg_tuyen_dung` và quyền riêng của `ho_so_ut`, tải/xuất/xóa hồ sơ, thư, cron, đăng nhập qua `wp-login.php`, xóa cache Cloudflare (API giả), bề mặt tấn công, bản dịch `.mo`, duyệt toàn site.

**Còn phải kiểm chứng khi có giấy phép (spike đầu tiên trên staging):** ACF PRO + WPML/ACFML thật (chế độ nhóm trường, ưu tiên dịch của trường lặp, dịch đường dẫn kiểu nội dung
`wpml-config.xml`, giao diện nhập liệu của biên tập viên), lệnh `wp vgarco import` bằng WP-CLI thật, Nginx/header bảo mật/Cloudflare/Brevo thật, Safari, Firefox, Cốc Cốc và trình đọc màn hình thật.

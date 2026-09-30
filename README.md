# vgarco-web

Mã nguồn website Công ty TNHH Tư vấn Xây dựng VGARCO — WordPress, song ngữ Việt (`/`) và Đức (`/de/`).

Kho này chỉ chứa mã do nhà thầu viết. Lõi WordPress và plugin bên thứ ba **không** nằm trong Git; danh sách và lý do dùng ở [`plugins.lock.json`](plugins.lock.json).

## Cấu trúc

```
wp-content/
├── mu-plugins/vgarco-loader.php   Luôn chạy: khóa XML-RPC, chặn liệt kê tài khoản, tắt bình luận,
│                                  chặn gửi thư thật ở staging
├── plugins/vgarco-core/           DỮ LIỆU: kiểu nội dung, phân loại, trường ACF, trang cấu hình, ảnh
└── themes/vgarco/                 TRÌNH BÀY: mẫu trang, SCSS, JS, chuỗi giao diện
```

Nguyên tắc tách lớp: **kiểu nội dung không bao giờ nằm trong theme.** Đổi hoặc tắt theme thì dự án, tin tức, tuyển dụng vẫn còn nguyên trong trang quản trị.

| Kiểu nội dung | Đường dẫn VI | Ghi chú |
|---|---|---|
| `du_an` — Dự án | `/du-an/` | Phân loại `loai_cong_trinh` tại `/du-an/loai/<nhóm>/` |
| `linh_vuc` — Lĩnh vực hoạt động | — | Không có trang riêng; hiển thị trên một trang, có mã neo |
| `tin_tuc` — Tin tức | `/tin-tuc/` | Phân loại `chuyen_muc` |
| `vi_tri` — Vị trí tuyển dụng | `/tuyen-dung/<vị-trí>/` | Bộ lọc `noi_lam_viec`, `phong_ban` |

Tin tức dùng kiểu riêng thay vì `post` vì WPML không dịch được phần tiền tố cố định trong đường dẫn của bài viết thường.

## Yêu cầu

- WordPress 6.6+ (khuyến nghị bản 7.x), PHP 8.3
- Plugin trong `plugins.lock.json`. Bắt buộc để nhập dữ liệu: **ACF PRO** (bản miễn phí không có trang cấu hình và trường lặp)
- Node.js 20+ để build CSS/JS của theme

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

Không cần WordPress; cần PHP 8.3 CLI (bật `mbstring`) và Node.

```bash
php -l <tệp>                          # cú pháp; chạy cho mọi tệp *.php trong wp-content/
php tests/php/smoke.php               # kiểm thử khói: hàm thuần, cấu trúc trường ACF, chặn thư ở staging, HTML menu
php tests/php/render.php              # chạy mẫu header/index/footer THẬT, đổi mọi cảnh báo PHP thành lỗi → tests/out/frame.html
node tests/serve.mjs 4174             # phục vụ khung HTML + src/js để thử JS trên trình duyệt
```

Kiểm thử khói **không thay** cho chạy thử trên WordPress + WPML + ACF PRO thật. Nó bắt lỗi logic ở phần không phụ thuộc WordPress.

## Quy ước

- **Mọi chuỗi hiển thị** bọc gettext, text domain `vgarco` (theme) hoặc `vgarco-core` (plugin); chuỗi gốc bằng tiếng Việt.
- **Không viết cứng** số điện thoại, địa chỉ, email vào mẫu trang: đọc qua `vgarco_option()` từ trang *Cấu hình VGARCO*.
- **Không thêm `!important`.** Độ ưu tiên CSS quản lý bằng `@layer` (`reset, tokens, base, components, pages, utilities`).
- **Không jQuery** ở front-end; JS là module ES thuần, nạp theo loại trang.
- Màu chữ trên nền phải đạt tương phản WCAG 2.1 AA (4,5 : 1). Token `--c-line` (1,32 : 1) chỉ dùng để trang trí, không dùng làm viền ô nhập liệu.
- Tệp giấy phép thương mại (`*.zip`), khóa API, mật khẩu: **không bao giờ commit** (đã chặn trong `.gitignore`).

## Trạng thái

Đã có: bộ khung dữ liệu (`vgarco-core`), bộ khung giao diện (token, đầu trang, menu, chân trang).

Chưa có (theo thứ tự triển khai): mẫu trang chủ và các trang nội dung; tìm kiếm dự án (REST); tuyển dụng và nộp hồ sơ; bộ chuyển ngôn ngữ đầy đủ (trang chưa có bản dịch); công cụ nhập dữ liệu `wp vgarco import`; script dựng máy chủ; kiểm thử tự động.

Điểm cần kiểm chứng trên WPML/ACFML thật: chế độ nhóm trường ACF, độ ưu tiên dịch của trường lặp, dịch đường dẫn kiểu nội dung (`wpml-config.xml`).

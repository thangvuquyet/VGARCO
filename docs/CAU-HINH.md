# Cấu hình và khóa bí mật

## 1. Nguyên tắc

- **Khóa bí mật không nằm trong Git, không nằm trong CSDL, không nằm trong tài liệu.** Trên máy chủ cất ở `/etc/vgarco/secrets.php` (quyền `0640`, chủ `root`, nhóm `vgarco`), `wp-config.php` nạp tệp đó.
  Bản gốc cất trong **kho mật khẩu đứng tên chủ đầu tư**. (Thiết kế; chưa dựng máy chủ thật.)
- `wp-config.php` và `*.zip` giấy phép bị `.gitignore` chặn.
- Cấu hình thay đổi theo môi trường nằm ở `wp-config.php`; nội dung công ty (số điện thoại, địa chỉ, chỉ số...) nằm ở trang **Cấu hình VGARCO** trong trang quản trị.

## 2. Hằng số trong `wp-config.php`

| Hằng số | Bắt buộc | Tác dụng nếu thiếu | Ghi chú |
|---|---|---|---|
| `VGARCO_PRIVATE_DIR` | Có (production, staging) | Mặc định `<thư mục cha của WordPress>/vgarco-private` | Nơi lưu CV. **Phải nằm ngoài thư mục web**: nếu nằm trong, hệ thống **từ chối nhận hồ sơ** (không lưu tạm). Thư mục phải ghi được bởi người dùng PHP-FPM. Production: `/srv/vgarco/private` |
| `VGARCO_CF_ZONE` | Production | Không xóa cache Cloudflare (không lỗi) | ID zone, 32 ký tự |
| `VGARCO_CF_TOKEN` | Production | Như trên | API token chỉ có quyền **Zone → Cache Purge** trên đúng zone này. Không dùng khóa toàn quyền (Global API Key) |
| `VGARCO_TELEGRAM_TOKEN`, `VGARCO_TELEGRAM_CHAT` | Nên có | Cảnh báo chỉ hiện ở trang quản trị (và email với cảnh báo khẩn) | Cần **cả hai** mới gửi Telegram |
| `VG_MAIL_ALLOW` | Staging | Staging chặn **mọi** thư | Mảng địa chỉ hoặc `@tên-miền` được phép nhận thư khi `WP_ENVIRONMENT_TYPE = staging`. Ví dụ `array( 'ten@example.com', '@vgarco.vn' )` |
| `VGARCO_AVIF_ADAPTIVE` | Không | Bật (`true`) | `false` tắt việc hạ chất lượng AVIF theo từng ảnh (mọi ảnh về mức chung 55, có thể vượt trần dung lượng). Chỉ tắt khi bộ mã hóa AVIF của máy chủ quá chậm khiến nạp/tải ảnh lên bị quá thời gian (mỗi ảnh lớn phải mã hóa thử 1–3 lần) |
| `WP_ENVIRONMENT_TYPE` | Có | WordPress coi là `production` | `local`, `staging` hoặc `production` — xem mục 3 |

Hằng số WordPress nên đặt ở production (thiết kế): `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS` (cập nhật chỉ qua WP-CLI dưới tài khoản `deploy`, xem `VAN-HANH.md`), `AUTOMATIC_UPDATER_DISABLED`, `FORCE_SSL_ADMIN`,
`WP_DEBUG = false`, `WP_DEBUG_DISPLAY = false`, `DISABLE_WP_CRON = true` (đã có cron hệ thống), `WP_POST_REVISIONS = 10`, `EMPTY_TRASH_DAYS = 30`, tiền tố bảng khác `wp_`, salt sinh mới, không có tài khoản tên `admin`.

Mẫu rút gọn:

```php
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'FORCE_SSL_ADMIN', true );
define( 'DISABLE_WP_CRON', true );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'VGARCO_PRIVATE_DIR', '/srv/vgarco/private' );
require '/etc/vgarco/secrets.php';   // định nghĩa VGARCO_CF_ZONE, VGARCO_CF_TOKEN, VGARCO_TELEGRAM_*, khóa bản quyền
```

## 3. Môi trường

| | `local` | `staging` | `production` |
|---|---|---|---|
| Thư gửi ra | Gửi bình thường (dùng Mailpit để bắt) | **Chỉ tới địa chỉ/tên miền trong `VG_MAIL_ALLOW`**; thư có bất kỳ người nhận nào ngoài danh sách bị chặn **toàn bộ** và báo "đã gửi" | Gửi bình thường |
| Công cụ tìm kiếm | `noindex, nofollow` | `noindex, nofollow` (**không bao giờ** xuất hiện trên Google) | Cho lập chỉ mục (trừ 404, kết quả tìm kiếm, trang Ứng tuyển) |
| Xóa cache Cloudflare | Không (thiếu hằng số) | Không | Có |

Kiểm tra môi trường đang chạy: `wp eval 'echo wp_get_environment_type();'`.

## 4. Sau khi cài mới, đặt trong *Cài đặt* của WordPress

- **Đường dẫn cố định:** `/%postname%/`.
- **Múi giờ:** `Asia/Ho_Chi_Minh` (lịch xóa cache hằng ngày lúc 00:10 và hạn nộp hồ sơ tính theo múi giờ này).
- **Địa chỉ email quản trị (`admin_email`):** hộp thư người vận hành đọc hằng ngày — nhận **cảnh báo khẩn, thư báo khóa tài khoản và thư báo có quản trị viên mới**.
- **Biểu tượng trang web (favicon):** mặc định dùng bộ của theme theo logo VGARCO (`favicon.svg`, 32/192/512 px, `apple-touch-icon.png`; `/favicon.ico` chuyển về ảnh 32 px). Muốn đổi: *Giao diện → Tùy biến → Nhận dạng trang web → Biểu tượng trang web* (ảnh vuông từ 512 px); đã chọn thì theme không chèn bộ của mình.
- **Trang chủ tĩnh:** trang "Trang chủ"; các trang tĩnh dùng mẫu tương ứng (Về công ty, Ban lãnh đạo, Phòng ban, Lĩnh vực hoạt động, Tuyển dụng, Ứng tuyển, Liên hệ).

## 5. Trang *Cấu hình VGARCO*

Ba nhóm: **Pháp nhân và liên hệ**, **Trang chủ và giới thiệu**, **Mẫu thư ứng tuyển**. Cài mới chưa lưu trang này thì website vẫn hiển thị đúng nhờ **giá trị mặc định trong mã** (`vgarco_option_defaults()`); khi đã lưu, giá trị đã lưu luôn thắng, ô để trống dùng mặc định.

Các giá trị cần chú ý:
- `email_chung` (mặc định tạm `vgarco.cc@gmail.com`, đổi sang hộp thư công ty khi có) và `email_tuyen_dung`: **thư báo hồ sơ ứng tuyển** gửi tới `email_tuyen_dung`, **để trống thì gửi tới `email_chung`**.
- `zalo`, `facebook`: để trống thì trang Liên hệ ghi "Đang cập nhật" (không phải liên kết).
- Ảnh hero mặc định của trang chủ: dùng khi chưa có dự án nào được đánh dấu nổi bật; cũng là ảnh chia sẻ (`og:image`) của trang không có ảnh riêng. Chưa chọn thì dùng thẻ thương hiệu 1200×630 có sẵn trong theme (`assets/images/share-default-vi.png`, bản Đức `share-default-de.png`).
- Mẫu thư ứng tuyển: **thư báo nhân sự luôn bằng tiếng Việt và không đính kèm CV** (xem và tải CV trong trang quản trị, có nhật ký); **thư xác nhận cho ứng viên** theo ngôn ngữ ứng viên đã nộp hồ sơ. Để trống thì dùng mẫu mặc định trong mã.

Việc **công bố chủ đầu tư và đối tác** của từng dự án nằm ở trường `cong_bo_chu_dau_tu` của dự án (mặc định **tắt**), không ở trang Cấu hình.

## 6. Thành phần bên thứ ba

Danh sách, lý do dùng và trạng thái bắt buộc/tùy chọn: [`plugins.lock.json`](../plugins.lock.json) (12 mục, trong đó 2 tùy chọn; mỗi mục có lý do — đặc tả 5.7 #3).
Giấy phép thương mại (ACF PRO, WPML) **đứng tên VGARCO**; tệp cài đặt `.zip` và khóa **không bao giờ** vào Git.

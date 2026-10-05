# Kiến trúc kỹ thuật

## 1. Ba lớp mã

```
wp-content/
├── mu-plugins/vgarco-loader.php   Luôn chạy, không tắt được từ trang quản trị
├── plugins/vgarco-core/           Dữ liệu và nghiệp vụ
└── themes/vgarco/                 Trình bày
```

**Nguyên tắc tách lớp:** kiểu nội dung không bao giờ nằm trong theme — đổi hoặc tắt theme thì dự án, tin tức, tuyển dụng vẫn còn nguyên (đặc tả 5.7 #1; kiểm chứng ở `tests/wp-lab/it_env.php`).
Mỗi chức năng có phần quyết định viết thành **hàm thuần** (`*-lib.php`, kiểm thử được bằng dữ liệu giả) và một tệp mỏng nối vào WordPress.

### `vgarco-loader.php` (mu-plugin)
Thu hẹp bề mặt tấn công và chặn thư thật ở staging:
- `xmlrpc.php`: trả **403 và dừng ngay** mọi yêu cầu (bộ lọc `xmlrpc_enabled` một mình không đủ: `system.listMethods`, `system.multicall`, `pingback.ping` vẫn trả lời); bỏ header `X-Pingback`.
- Tắt Application Passwords; `?author=N` trả 404 (chạy trước `redirect_canonical` để không lộ tên đăng nhập); bỏ nhà cung cấp "users" khỏi sơ đồ trang; khách vãng lai không thấy `/wp-json/wp/v2/users*`.
- Tắt bình luận và pingback toàn site.
- `WP_ENVIRONMENT_TYPE = staging`: thư gửi ra chỉ tới địa chỉ/tên miền trong `VG_MAIL_ALLOW` (To, Cc và Bcc đều được kiểm), còn lại bị chặn và báo "đã gửi" để luồng nghiệp vụ vẫn chạy.

Header bảo mật (HSTS, CSP...) **do Nginx gửi**, không gửi ở PHP.

### `vgarco-core` (plugin)

| Nhóm tệp (`inc/`) | Làm gì |
|---|---|
| `post-types.php`, `taxonomies.php`, `seed.php` | Kiểu nội dung, phân loại, dữ liệu mồi (7 loại công trình, 3 chuyên mục) |
| `fields.php`, `fields/*.php`, `options.php`, `helpers.php` | Trường ACF (14 nhóm), trang **Cấu hình VGARCO**, giá trị mặc định trong mã (`vgarco_option()`) |
| `careers/*` | Tuyển dụng: mã bảo mật, nhận hồ sơ, lưu CV, quản trị hồ sơ, thư, xuất `.xlsx`, tự xóa 12 tháng |
| `search*.php` | Tìm kiếm dự án (REST, chỉ mục theo ngôn ngữ) |
| `security*.php` | Khóa đăng nhập sai, "quên mật khẩu" không lộ tài khoản, báo quản trị viên mới |
| `cache*.php` | Xóa cache Cloudflare |
| `alerts.php` | Cảnh báo hệ thống cho người vận hành |
| `media*.php` | Gỡ GPS/EXIF khỏi ảnh tải lên, giới hạn JPEG/PNG, kích thước ảnh, chất lượng AVIF/WebP |
| `cli/import*.php` | Lệnh `wp vgarco import` và `wp vgarco report` |
| `editor.php` | Giới hạn khối soạn thảo, ẩn menu "Bài viết" mặc định |

### `vgarco` (theme)
Mẫu trang, SCSS/JS (Vite, `npm run build` sinh `dist/`, **không commit**), chuỗi giao diện gettext, SEO cơ bản (không dùng plugin SEO), menu, bộ chuyển ngôn ngữ.
PHP đọc `dist/.vite/manifest.json` để nạp CSS/JS có mã băm (`inc/enqueue.php`); chưa build thì trang vẫn chạy nhưng không có kiểu dáng.

## 2. Mô hình dữ liệu

| Kiểu nội dung | Đường dẫn | Ghi chú |
|---|---|---|
| `du_an` — Dự án | `/du-an/<slug>/` | Sắp theo "Thứ tự" (`menu_order`) rồi tên. Khóa nhập liệu: trường `ma` (chữ thường không dấu, gạch ngang) |
| `linh_vuc` — Lĩnh vực hoạt động | không có trang riêng | Hiển thị trên một trang, mỗi mục có mã neo |
| `tin_tuc` — Tin tức | `/tin-tuc/<slug>/` | Kiểu riêng (không dùng `post`) vì WPML không dịch được tiền tố của bài viết thường |
| `vi_tri` — Vị trí tuyển dụng | `/tuyen-dung/<slug>/` | `has_archive = false` để không tranh đường dẫn với trang Tuyển dụng (là một `page`) |
| `ho_so_ut` — Hồ sơ ứng tuyển | **không có URL** | Riêng tư: không REST, không xuất, không công khai; chỉ vai trò Nhân sự tuyển dụng và Quản trị viên |

Phân loại: `loai_cong_trinh` (`/du-an/loai/<nhóm>/`), `chuyen_muc` (`/tin-tuc/chuyen-muc/<mục>/`), `noi_lam_viec` và `phong_ban` (chỉ để lọc tuyển dụng, không có URL).
**Thứ tự đăng ký có ý nghĩa:** phân loại phải đăng ký **trước** kiểu nội dung (`init` ưu tiên 5 so với 10), nếu không quy tắc ảnh đính kèm của dự án khớp trước và mọi trang `/du-an/loai/…` trả 404.
Sau khi thêm hoặc đổi kiểu nội dung/phân loại phải **làm mới đường dẫn** (`wp rewrite flush`); kích hoạt plugin đã tự làm.

Trang tĩnh dùng mẫu trong `themes/vgarco/templates/`: Về công ty, Ban lãnh đạo, Phòng ban, Lĩnh vực hoạt động, Tuyển dụng, Ứng tuyển, Liên hệ. Trang chủ là trang tĩnh gắn nhóm trường ACF (để trống thì dùng bản mặc định trong mã).

### Vai trò và quyền
- `vg_tuyen_dung` ("Nhân sự tuyển dụng"): đọc, xem danh sách/chi tiết hồ sơ, tải CV, xuất bảng tính, xóa hồ sơ. **Không** có quyền sửa bài, tải ảnh hay vào cấu hình.
- Quản trị viên có đủ bốn quyền hồ sơ: `vg_view_applications`, `vg_download_cv`, `vg_export_applications`, `vg_delete_applications`.
- Vai trò được tạo **khi kích hoạt plugin** (không chờ ai mở trang quản trị), nên `wp user create --role=vg_tuyen_dung` chạy được ngay sau cài mới.

## 3. Đường REST (`/wp-json/vgarco/v1/…`)

| Đường | Phương thức | Việc |
|---|---|---|
| `/search?q=<từ khóa>&lang=vi\|de` | GET | Tìm dự án: tối thiểu 3 ký tự, tối đa 100; tối đa 10 kết quả; bỏ dấu; `Cache-Control: public, max-age=300` |
| `/apply/token` | GET | Cấp token + ảnh mã bảo mật (5 ký tự). Không cache. **30 lượt/giờ/IP**, quá thì 429 |
| `/apply` | POST | Nhận hồ sơ. Thứ tự kiểm tra và mã trả về: Origin/Referer sai → 403; quá giới hạn tần suất → 429; trường bẫy có chữ hoặc điền < 4 giây/> 2 giờ → 400 `rejected` (không lưu, không gợi ý lý do); mã bảo mật sai/hết hạn/đã dùng → 400 kèm mã mới, giữ dữ liệu; vị trí đã ngừng nhận → 409; trường sai → 400; tệp sai → 400; lỗi lưu → 500; **lỗi gửi thư vẫn trả 200** (hồ sơ đã lưu, thư được đánh dấu để gửi lại) |

Giới hạn nộp hồ sơ: **5 hồ sơ/giờ và 20 hồ sơ/ngày mỗi IP** (IP được băm có muối, không lưu IP gốc). Token sống 10 phút, dùng một lần, chỉ lưu **băm** của mã. Thời gian điền tối thiểu 4 giây.
Tệp CV: PDF, DOC, DOCX, tối đa 10 MB; kiểm tra **nội dung thật** (không tin đuôi tệp), từ chối DOC/DOCX có macro, lưu tại `<VGARCO_PRIVATE_DIR>/cv/YYYY/MM/<32 ký tự hex>` (quyền 0640, không đuôi) và **từ chối lưu** nếu thư mục đó nằm trong thư mục web.

## 4. Tác vụ định kỳ (WP-Cron)

| Hook | Khi nào | Việc |
|---|---|---|
| `vgarco_cf_purge` | 30 giây sau khi nội dung công khai đổi (gom nhiều thay đổi thành **một** lượt) | Xóa **toàn bộ** cache Cloudflare |
| `vgarco_cf_purge_daily` | 00:10 giờ Việt Nam hằng ngày | Như trên (hạn nộp hồ sơ đổi ngày nên HTML cũ phải biến mất) |
| `vgarco_apply_mail_retry` | Mỗi giờ, chỉ khi có hồ sơ lỗi thư | Gửi lại thư HR/ứng viên của hồ sơ lỗi, **tối đa 3 lần mỗi hồ sơ**; quá 3 lần thì cảnh báo khẩn |
| `vgarco_apply_retention` | Hằng ngày 02:30 theo múi giờ **PHP/UTC** (= 09:30 giờ Việt Nam) | Xóa hồ sơ quá **12 tháng** kèm tệp CV (tối đa 200 hồ sơ mỗi lượt) |

Trên production dùng `DISABLE_WP_CRON` và cron hệ thống chạy `wp cron event run --due-now`. **Độ trễ thực của việc xóa cache và gửi lại thư bằng chu kỳ cron hệ thống** (kế hoạch: 5 phút; muốn gần 30 giây thì chạy mỗi phút).
Cache Cloudflare chỉ được lên lịch xóa khi đã khai báo `VGARCO_CF_ZONE` và `VGARCO_CF_TOKEN`.

## 5. Bộ nhớ đệm và bộ đếm (transient)

| Khóa | Nội dung | Hết hạn / xóa |
|---|---|---|
| `vgarco_search_vi`, `vgarco_search_de` | Chỉ mục tìm kiếm theo ngôn ngữ | 24 giờ; **tự xóa** khi dự án hoặc loại công trình đổi |
| `vg_apply_<token>` | Mã bảo mật đang chờ (chỉ băm) | 10 phút, dùng một lần |
| `vg_lock_ip_*`, `vg_lock_user_*` | Bộ đếm đăng nhập sai và thời điểm hết khóa (khóa băm có muối, không lưu IP/tên rõ) | 2 giờ |
| bộ đếm tần suất nộp hồ sơ | Theo IP đã băm: `attempts`, `submissions_hour`, `submissions_day` | Theo cửa sổ của từng bộ đếm |

Có Redis (object cache) thì transient nằm ở Redis, không ở bảng `options`: xóa bằng `wp transient delete …` hoặc `wp cache flush`, không xóa thẳng bảng.

## 6. Biện pháp bảo mật trong mã (không thay cho cấu hình máy chủ)

- **Khóa đăng nhập:** 5 lần sai trong 15 phút khóa IP 15 phút; 10 lần sai trong 1 giờ khóa tài khoản 1 giờ và **báo email quản trị viên đúng một lần mỗi đợt khóa**. Chạy ở ưu tiên 98 của `authenticate`, *sau* kiểm tra mật khẩu của lõi (đặt trước thì vô hiệu: mật khẩu đúng vẫn vào được).
  Đăng nhập bằng tên hay bằng email của cùng người dùng chung một bộ đếm. Mọi lỗi đăng nhập dùng **một thông báo chung**; "Quên mật khẩu" không cho biết tài khoản có tồn tại hay không.
- **Báo quản trị viên mới:** tạo mới hoặc nâng quyền lên Quản trị viên đều gửi email tới `admin_email`.
- **Ảnh tải lên:** GPS, EXIF, XMP, IPTC bị gỡ khỏi tệp gốc (WordPress phục vụ công khai tệp gốc) trước khi tạo các bản thu nhỏ; ảnh dọc chụp bằng điện thoại được xoay thẳng trước khi gỡ hướng xoay; chỉ nhận JPEG và PNG; không xử lý được thì **xóa tệp và từ chối**.
- **IP người gửi:** `vgarco_client_ip()` tin header `CF-Connecting-IP`. An toàn **chỉ khi** máy chủ gốc chỉ nhận kết nối từ Cloudflare (ufw + Authenticated Origin Pulls); nếu không, kẻ tấn công giả header để né khóa đăng nhập và giới hạn tần suất.
- **Không có thư mục tải lên thực thi được, không có cấu hình nằm trong web root:** phần này do Nginx quyết định (xem `TRIEN-KHAI.md`).

## 7. Kiểm thử và công cụ

| Việc | Lệnh |
|---|---|
| Toàn bộ kiểm thử không cần WordPress (~3.600 phép kiểm) | `PHP=… PHP_EXT=… node tests/run.mjs` |
| Kiểm thử đột biến (cố ý phá mã, xác nhận kiểm thử chuyển đỏ) | `node tests/run.mjs --mutate` |
| Kiểm thử trên WordPress thật (SQLite) | `node tests/wp-lab/bootstrap.mjs …` rồi `node tests/wp-lab/run.mjs` |
| Dịch | `node scripts/i18n/sync.mjs` |
| Xuất ảnh master | `php scripts/content/export-images.php …` |

Phòng thử `tests/wp-lab` dùng **ACF PRO và WPML bản giả**: việc nối với ACF PRO, WPML và ACFML thật chưa được kiểm chứng và là việc đầu tiên khi có giấy phép (xem `README.md` gốc, mục Trạng thái).

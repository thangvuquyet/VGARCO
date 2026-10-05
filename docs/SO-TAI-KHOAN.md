# Sổ tài khoản dịch vụ

> **Mẫu trống — điền khi mở từng tài khoản.** Không ghi mật khẩu, khóa API hay token ở đây: chỉ ghi *nơi cất* (kho mật khẩu đứng tên chủ đầu tư). Mọi tài khoản đăng ký theo tên pháp nhân
> **Công ty TNHH Tư vấn Xây dựng VGARCO**, email quản trị là hộp thư của công ty (không phải email cá nhân); nhà thầu chỉ giữ quyền truy cập để vận hành (đặc tả 5.7 #4, 9.7).

## 1. Sổ tài khoản

| Dịch vụ | Dùng để | Chủ sở hữu | Email quản trị | 2FA | Ngày gia hạn | Người có quyền truy cập | Khóa/token nằm ở |
|---|---|---|---|---|---|---|---|
| Tên miền `vgarco.vn` (nhà đăng ký được VNNIC ủy quyền) | Tên miền chính | VGARCO | | | | | |
| Tên miền `vgarco.de` | Giữ thương hiệu, chuyển hướng về bản tiếng Đức | VGARCO | | | | | |
| Cloudflare | DNS, CDN, WAF, giới hạn tần suất, DMARC | VGARCO | | | | | API token Cache Purge: `/etc/vgarco/secrets.php` |
| Máy chủ riêng ảo (VPS) | Chạy website | VGARCO | | | | | Khóa SSH của `deploy` |
| Backblaze B2 (hoặc Wasabi) | Kho sao lưu | VGARCO | | | | | Khóa ứng dụng + mật khẩu restic: `/etc/vgarco/restic.env` |
| Zoho Mail | Hộp thư nhân sự theo tên miền | VGARCO | | | | | |
| Brevo | Thư tự động từ website (qua FluentSMTP, API) | VGARCO | | | | | Khóa API: FluentSMTP |
| HetrixTools | Giám sát hoạt động (đăng nhập ít nhất mỗi 90 ngày) | VGARCO | | | | | |
| Patchstack | Cảnh báo lỗ hổng thành phần | VGARCO | | | | | |
| Heartbeat sao lưu (healthchecks.io hoặc tương đương) | Cảnh báo khi sao lưu không chạy | VGARCO | | | | | Địa chỉ ping: `/etc/vgarco/restic.env` |
| ACF PRO | Giấy phép trường dữ liệu | VGARCO | | | | | Khóa giấy phép: `/etc/vgarco/secrets.php` |
| WPML | Giấy phép đa ngôn ngữ (gồm String Translation, ACFML) | VGARCO | | | | | Khóa giấy phép |
| Kho Git của mã nguồn | `vgarco-web` | VGARCO | | | | | |
| Telegram bot (cảnh báo) | Cảnh báo vận hành | VGARCO | | | | | `VGARCO_TELEGRAM_TOKEN`, `VGARCO_TELEGRAM_CHAT` |
| Figma `Project-1-VGARCO` | Tệp thiết kế gốc (chuyển quyền sở hữu sang tài khoản VGARCO khi bàn giao) | VGARCO | | | | | |
| **Kho mật khẩu** | Nơi cất mọi mật khẩu/khóa | VGARCO | | | | Người nào có thể mở | |
| Tài khoản WordPress | Quản trị viên, Nhân sự tuyển dụng | — | | Bắt buộc (WP 2FA) | — | Danh sách ở trang *Người dùng* | |

## 2. Khóa bí mật và nơi cất

| Khóa | Dùng ở | Nơi cất | Xoay vòng |
|---|---|---|---|
| API token Cloudflare (chỉ quyền Cache Purge) | `VGARCO_CF_TOKEN` | `/etc/vgarco/secrets.php` + kho mật khẩu | Khi có người rời nhóm hoặc nghi lộ |
| ID zone Cloudflare | `VGARCO_CF_ZONE` | `/etc/vgarco/secrets.php` | — |
| Token bot Telegram, ID nhóm | `VGARCO_TELEGRAM_*` | `/etc/vgarco/secrets.php` | Khi nghi lộ |
| Khóa ứng dụng B2, mật khẩu restic | Sao lưu | `/etc/vgarco/restic.env` + kho mật khẩu | **Mất mật khẩu restic = mất toàn bộ bản sao lưu**: bản dự phòng ngoài máy chủ bắt buộc |
| Khóa Brevo | FluentSMTP | Kho mật khẩu (+ cấu hình FluentSMTP) | Khi có người rời nhóm |
| Khóa giấy phép ACF PRO, WPML | Cài và cập nhật plugin | `/etc/vgarco/secrets.php` + kho mật khẩu | Khi gia hạn |
| Salt của WordPress | `wp-config.php` | Máy chủ | Khi nghi bị xâm nhập (`wp config shuffle-salts`) |

## 3. Danh sách rút quyền khi kết thúc hợp tác (đặc tả 9.7 — trong 05 ngày làm việc)

Bàn giao: tài khoản quản trị hệ thống, mã nguồn mới nhất, bản sao lưu đầy đủ, hồ sơ kỹ thuật (`docs/`). Rồi rút **toàn bộ** quyền của nhà thầu và đánh dấu:

- ☐ Tài khoản WordPress của nhà thầu (xóa, chuyển nội dung sang tài khoản của VGARCO)
- ☐ Khóa SSH của nhà thầu trên máy chủ (`~deploy/.ssh/authorized_keys`), tài khoản hệ điều hành `deploy` đổi chủ
- ☐ Thành viên Cloudflare
- ☐ Thành viên kho Git (và token truy cập cá nhân)
- ☐ Backblaze B2 (khóa ứng dụng cá nhân), restic (nếu nhà thầu từng biết mật khẩu: **đổi** và lưu lại)
- ☐ Zoho Mail (quản trị)
- ☐ HetrixTools, Patchstack, heartbeat sao lưu
- ☐ Nhà đăng ký tên miền, nhà cung cấp VPS, Brevo
- ☐ Khóa giấy phép ACF PRO, WPML còn đăng ký theo email của nhà thầu → chuyển sang email của VGARCO
- ☐ Token API đã cấp cho nhà thầu: Cloudflare, Telegram, Brevo, B2 → thu hồi và cấp mới
- ☐ Tệp Figma và kho mật khẩu: nhà thầu không còn quyền
- ☐ Đổi mọi mật khẩu dùng chung đã từng cho nhà thầu biết

Ghi ngày, người thực hiện, người xác nhận của chủ đầu tư.

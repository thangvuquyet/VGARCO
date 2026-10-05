# Sổ tay xử lý sự cố

Cách đọc: mỗi mục có **triệu chứng → kiểm tra → khắc phục**. Lệnh `wp` chạy dưới tài khoản `deploy` ở thư mục WordPress. Các đoạn `wp eval` đã được **chạy thử trên WordPress 7.1 thật** (phòng thử `tests/wp-lab`); lệnh WP-CLI chuẩn và phần máy chủ (Nginx, restic) là thiết kế, chưa chạy trên máy chủ thật.

Mức xử lý theo bảo hành (đặc tả 9.1): **nghiêm trọng** (website không truy cập được, lộ dữ liệu, giao diện bị thay đổi trái phép) — phản hồi ≤ 24 giờ làm việc, khắc phục ≤ 8 giờ làm việc; **thường** — phản hồi ≤ 24 giờ, khắc phục ≤ 3 ngày làm việc.
Mỗi sự cố ghi một dòng vào sổ theo dõi: ngày nhận, mức, người xử lý, ngày đóng.

---

## 1. Website không truy cập được *(nghiêm trọng)*

1. Từ một máy ngoài: `node scripts/ops/smoke.mjs https://<tên-miền> --env=production` — xem **trang nào, mã nào** hỏng.
2. Mã **521/522/523 của Cloudflare** = Cloudflare không nối được máy chủ gốc: kiểm tra máy chủ còn chạy, `systemctl status nginx php8.3-fpm mariadb`, tường lửa còn mở 443 cho dải IP Cloudflare (dải IP đổi: chạy lại `cloudflare-ips.sh`), chứng chỉ máy chủ gốc chưa hết hạn.
3. **502/504:** PHP-FPM treo hoặc hết bộ nhớ: `journalctl -u php8.3-fpm`, nhật ký lỗi PHP; `systemctl restart php8.3-fpm`.
4. **Lỗi kết nối CSDL:** `systemctl status mariadb`, dung lượng đĩa (`df -h`; đĩa đầy là nguyên nhân hay gặp), thông tin trong `wp-config.php`.
5. Chỉ **sau khi cập nhật**: hoàn tác theo `TRIEN-KHAI.md` mục 4.
6. Không cứu được trong giờ khắc phục: khôi phục từ sao lưu (`SAO-LUU-PHUC-HOI.md` mục 3).

## 2. Bị khóa đăng nhập

Hệ thống khóa **IP** (5 lần sai/15 phút → 15 phút) và **tài khoản** (10 lần sai/1 giờ → 1 giờ, kèm email báo `admin_email`). Mọi trường hợp đều hiện cùng một thông báo chung nên không biết đang bị khóa theo IP hay theo tài khoản.
Đợi hết hạn, hoặc mở khóa ngay (đã chạy thử):

```bash
# Mở khóa một tài khoản và một IP (thay tên đăng nhập và IP)
wp eval '
  delete_transient( vgarco_lockout_key( "user", "ten-dang-nhap", vgarco_lockout_salt() ) );
  delete_transient( vgarco_lockout_key( "ip", "203.0.113.7", vgarco_lockout_salt() ) );
  echo "đã mở khóa";
'
```

Nhận thư báo "Tài khoản bị tạm khóa" mà không phải do người trong nhóm gõ sai: có thể đang bị dò mật khẩu — bật kiểm tra ở Cloudflare (rate limiting đường dẫn đăng nhập), bảo đảm mọi tài khoản đã bật 2FA, xem mục 10.

## 3. Hồ sơ ứng tuyển đã lưu nhưng thư chưa gửi

Triệu chứng: trang quản trị hiện cảnh báo "Hồ sơ #… đã lưu nhưng thư chưa gửi được. Hệ thống sẽ tự thử lại mỗi giờ (tối đa 3 lần)" (và Telegram nếu đã cấu hình). **Hồ sơ và CV không mất.**

1. Mở nhật ký FluentSMTP: lỗi xác thực, hết hạn mức, tên miền gửi chưa xác minh (SPF/DKIM)?
2. Sửa nguyên nhân (khóa API Brevo, tên miền gửi, cấu hình FluentSMTP).
3. Cho gửi lại ngay thay vì đợi: `wp cron event run vgarco_apply_mail_retry`.
4. Sau **3 lần** vẫn lỗi hệ thống ngừng thử và gửi **cảnh báo khẩn qua email** tới `admin_email`: mở hồ sơ trong trang quản trị, tải CV, **liên hệ ứng viên thủ công** và báo bộ phận nhân sự.
5. Xem trạng thái từng hồ sơ: trường thư báo nhân sự/thư xác nhận của hồ sơ (`da_gui` hoặc `loi`).

Thư báo nhân sự **không đính kèm CV** (xem CV trong trang quản trị). Nhân sự báo "không nhận được thư": kiểm tra hộp thư rác và địa chỉ nhận ở trang *Cấu hình VGARCO* (`email_tuyen_dung`, trống thì `email_chung`).

## 4. Không xem hoặc tải được CV

- Báo lỗi "thư mục lưu CV nằm trong thư mục web" hoặc không ghi được: `wp eval 'print_r( vgarco_private_dir_check() );'` — `ok => 1` là đúng. Sai thì kiểm tra `VGARCO_PRIVATE_DIR` (phải **ngoài** thư mục web) và quyền ghi của người dùng PHP-FPM. Khi sai, hệ thống **từ chối nhận hồ sơ** (không lưu tạm) và báo lỗi cho ứng viên.
- Hồ sơ có nhưng thiếu tệp: khôi phục tệp từ sao lưu (`SAO-LUU-PHUC-HOI.md` mục 4); tên tệp ở trường `cv_duong_dan` của hồ sơ.
- Tài khoản Nhân sự không thấy mục *Hồ sơ ứng tuyển*: vai trò phải là `vg_tuyen_dung` (hoặc Quản trị viên). Kiểm tra `wp user get <tên> --field=roles`.

## 5. Ứng viên không lấy được mã bảo mật / báo "quá nhiều lần"

Giới hạn theo IP đã băm: **30 lượt xin mã hoặc thử gửi mỗi giờ; 5 hồ sơ mỗi giờ; 20 hồ sơ mỗi ngày.** Nhiều ứng viên dùng chung một IP (mạng công ty, trường học) có thể chạm giới hạn. Mở giới hạn ngay cho mọi IP:

```bash
wp transient delete --all        # xóa mọi bộ đếm (và mã bảo mật đang chờ, và bộ đếm đăng nhập sai); có Redis thì thêm: wp cache flush
```

Giao diện nói rõ nguyên nhân (không còn báo "kiểm tra mạng" khi bị giới hạn). Ứng viên gõ sai mã nhiều lần: mã bị hủy và cấp mã mới, dữ liệu đã nhập được giữ.

## 6. Cache Cloudflare không được xóa

Triệu chứng: trang quản trị hiện cảnh báo "Xóa cache Cloudflare thất bại (…). Kiểm tra token có quyền Cache Purge và ID zone"; nội dung đã sửa chưa hiện ra công khai.

```bash
wp eval 'var_dump( vgarco_cf_purge() );'     # true = xong; false = xem cảnh báo mới ở trang quản trị
```

Nguyên nhân thường gặp: token hết hạn/bị thu hồi, thiếu quyền **Zone → Cache Purge**, sai `VGARCO_CF_ZONE`. Tạm thời xóa tay trong bảng điều khiển Cloudflare (Caching → Purge Everything).
Trang vị trí tuyển dụng đổi trạng thái khi quá hạn nộp: HTML cũ bị xóa bởi tác vụ 00:10 hằng ngày — nếu cache hỏng thì hạn nộp hồ sơ có thể hiển thị trễ đến hết TTL (4 giờ).

## 7. Dự án mới không hiện ở trang hoặc ô tìm kiếm

1. Dự án phải **đã xuất bản** (không phải nháp) và ở đúng ngôn ngữ đang xem.
2. Trang danh mục chưa đổi: xem mục 6 (cache). Ô tìm kiếm chưa thấy: làm mới chỉ mục (đã chạy thử):

```bash
wp transient delete vgarco_search_vi
wp transient delete vgarco_search_de
```

3. Nhiều trang `/du-an/loai/…` hoặc `/tin-tuc/chuyen-muc/…` trả 404 sau cập nhật: `wp rewrite flush`.

## 8. Nhập liệu bằng bảng tính báo lỗi

Tệp có **bất kỳ lỗi nào** thì không ghi gì cả; mọi lỗi được báo một lượt kèm số dòng. Sửa bảng rồi chạy lại với `--dry-run` cho tới khi sạch, rồi chạy thật.
Lỗi hay gặp: `ma` có dấu/chữ hoa/gạch ngang cuối; quá 5 dự án nổi bật hoặc 3 dự án tiêu biểu; ảnh thư viện thiếu `chu_thich_vi`; tệp ảnh thiếu hoặc quá 15 MB; mã quốc gia/loại công trình sai.
Ảnh tải lên bị từ chối "Không xử lý được ảnh này": chỉ nhận **JPEG và PNG**; WebP, AVIF, HEIC, GIF bị từ chối vì mang siêu dữ liệu chưa gỡ được — xuất lại bằng `php scripts/content/export-images.php`.

## 9. Cảnh báo hệ thống trong trang quản trị

Cảnh báo gần nhất hiện ở đầu trang cho Quản trị viên; tối đa 20 cảnh báo được giữ. Xem tất cả và xóa:

```bash
wp option get vgarco_alerts --format=json
wp option delete vgarco_alerts           # hoặc bấm "Đã xem, ẩn cảnh báo" trên trang quản trị
```

## 10. Nghi bị xâm nhập *(nghiêm trọng)*

Dấu hiệu: thư báo "Có tài khoản quản trị mới" mà không ai tạo; thư báo khóa tài khoản liên tục; giao diện hay nội dung bị đổi; cảnh báo Patchstack mức cao; nhà cung cấp hoặc Google báo mã độc.

1. **Giữ nguyên hiện trường:** chụp bản sao CSDL và nhật ký Nginx/PHP trước khi sửa gì.
2. **Cắt đường vào:** đổi mật khẩu mọi tài khoản quản trị, đăng xuất mọi phiên (`wp user session destroy --all <tên>` hoặc đổi salt), thu hồi token (Cloudflare, Telegram, B2, API Brevo), bật chế độ "I'm Under Attack" của Cloudflare nếu đang bị tấn công.
3. **Kiểm tra:**
   ```bash
   wp user list --role=administrator --fields=ID,user_login,user_email,user_registered   # tài khoản lạ?
   wp core verify-checksums                       # tệp lõi bị sửa?
   wp plugin verify-checksums --all               # tệp plugin bị sửa?
   find wp-content/uploads -name '*.php' -o -name '*.phtml' -o -name '*.phar'   # mã thực thi lạ trong uploads (phải rỗng)
   wp cron event list                             # tác vụ định kỳ lạ?
   git -C <thư-mục-mã> status                     # mã tự viết bị sửa so với Git?
   ```
4. Mã tự viết do `deploy` sở hữu và PHP **chỉ đọc**, nên kẻ chiếm tài khoản quản trị cũng không sửa được mã; nếu **mã bị sửa**, máy chủ đã bị chiếm sâu hơn tài khoản WordPress → dựng lại máy chủ từ đầu và khôi phục dữ liệu từ sao lưu **trước thời điểm bị xâm nhập**.
5. **Lộ dữ liệu cá nhân ứng viên:** có nghĩa vụ thông báo theo luật bảo vệ dữ liệu cá nhân; báo chủ đầu tư ngay trong ngày.
6. Sau xử lý: đổi toàn bộ khóa trong `/etc/vgarco/secrets.php` và salt (`wp config shuffle-salts`), kiểm tra lại 2FA, ghi biên bản sự cố.

## 11. Đĩa đầy hoặc website chậm bất thường

- `df -h`; thư mục lớn nhất thường là `uploads` (mỗi ảnh tải lên sinh khoảng 7 tệp; đo trên ảnh thật: 115 ảnh master 47 MB sinh ra ~113 MB), nhật ký, bản sao lưu cục bộ ở `/var/backups/vgarco` (chỉ giữ 2 ngày).
- Bộ nhớ đệm đối tượng (Redis) đầy/chết: `wp cache flush`; kiểm tra `systemctl status redis-server`.
- Ảnh tải lên lớn làm hết bộ nhớ khi tạo bản thu nhỏ: kiểm tra `memory_limit` ≥ 256 MB và dùng `export-images.php` để thu nhỏ ảnh trước khi tải.

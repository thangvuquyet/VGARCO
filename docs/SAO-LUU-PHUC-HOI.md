# Sao lưu và phục hồi

> **Trạng thái: thiết kế; phần sao lưu chưa chạy trên máy chủ nào** (máy chủ thử đã dựng bằng `provision.sh`, nhưng chưa có sao lưu). Chưa có `vgarco-backup` hay kho restic; **biên bản diễn tập khôi phục (đặc tả 5.5 #3) chưa có** — mục 5 là mẫu để điền khi diễn tập.

## 1. Cái gì phải sao lưu

| Dữ liệu | Vị trí (production) | Vì sao |
|---|---|---|
| CSDL | MariaDB `vgarco_prod` (dùng `mysqldump --single-transaction`) | Dự án, tin, hồ sơ ứng tuyển (bản ghi), cấu hình, tài khoản |
| Ảnh tải lên | `/var/www/vgarco/public/wp-content/uploads` | Toàn bộ ảnh dự án và các kích thước đã sinh |
| **CV ứng viên** | `/srv/vgarco/private` (`cv/YYYY/MM/<32 hex>`) | **Không có ở nơi nào khác.** Bản ghi hồ sơ trong CSDL chỉ giữ đường dẫn *tương đối* nên khôi phục sang thư mục khác vẫn đúng nếu `VGARCO_PRIVATE_DIR` trỏ tới đó |
| `wp-config.php` và `/etc/vgarco` | Máy chủ | Khóa bí mật, bản quyền; mất là phải xin cấp lại |
| Cấu hình Nginx | `/etc/nginx` | Dựng lại máy chủ nhanh |
| Mã nguồn | **Git** — không cần sao lưu từ máy chủ | Lõi và plugin cài lại từ `plugins.lock.json` |

## 2. Lịch và lưu giữ (thiết kế)

- **Hằng ngày 02:30** (cron, người dùng `backup`): CSDL + `uploads` + `/srv/vgarco/private` + `wp-config.php` + `/etc/vgarco` + `/etc/nginx`, bằng **restic** (mã hóa sẵn, khử trùng lặp) tới bucket **Backblaze B2 trong tài khoản của VGARCO**; khóa ứng dụng B2 chỉ có quyền trên đúng bucket đó.
  Sao lưu cả tệp **hằng ngày** (vượt yêu cầu "tệp hằng tuần") vì CV nộp trong tuần không được mất nếu sự cố xảy ra trước kỳ sao lưu tuần.
- **Lưu giữ:** 7 bản theo ngày, 4 theo tuần, 3 theo tháng (`restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 3 --prune`).
- **Kiểm tra:** `restic check --read-data-subset=5%` hằng tuần; mỗi quý khôi phục thử vào staging.
- **Giám sát:** sau mỗi lần sao lưu thành công gọi một địa chỉ heartbeat; **quá 26 giờ không có tín hiệu thì báo**.
- **Mục tiêu:** mất dữ liệu tối đa 24 giờ (RPO), khôi phục trong 2 giờ (RTO).
- **Mật khẩu restic, khóa B2 cất trong kho mật khẩu đứng tên chủ đầu tư. Mất mật khẩu restic là mất toàn bộ bản sao lưu.**

Hệ quả cho dữ liệu cá nhân: CV bị xóa khỏi hệ thống (tự xóa sau 12 tháng, hoặc xóa tay) còn trong bản sao lưu **tối đa ~3 tháng** (bản theo tháng). Ghi rõ điều này trong *Chính sách bảo vệ dữ liệu*.

## 3. Khôi phục toàn bộ vào máy chủ mới hoặc staging

```bash
# 0. Máy chủ dựng bằng scripts/ops/provision.sh (đã chạy thật trên máy chủ thử 29/10/2026): người dùng, Nginx, PHP-FPM, MariaDB, quyền tệp — xem TRIEN-KHAI.md mục 2
# 1. Lấy khóa và mật khẩu từ kho mật khẩu của chủ đầu tư, đặt /etc/vgarco/restic.env
source /etc/vgarco/restic.env
restic snapshots                                   # chọn mã snapshot (thường là bản mới nhất)

# 2. Khôi phục tệp
restic restore <mã-snapshot> --target /tmp/restore
rsync -a /tmp/restore/var/www/vgarco/public/wp-content/uploads/  /var/www/vgarco/public/wp-content/uploads/
rsync -a /tmp/restore/srv/vgarco/private/                         /srv/vgarco/private/
cp /tmp/restore/var/www/vgarco/wp-config.php /var/www/vgarco/wp-config.php
rsync -a /tmp/restore/etc/vgarco/ /etc/vgarco/

# 3. Quyền: PHP chỉ ghi được uploads và private
chown -R vgarco:vgarco /var/www/vgarco/public/wp-content/uploads /srv/vgarco/private
chmod -R u=rwX,g=rX,o= /srv/vgarco/private         # CV 0640

# 4. CSDL
gunzip -c /tmp/restore/var/backups/vgarco/db-<ngày>.sql.gz | mysql vgarco_prod

# 5. Mã nguồn từ Git (git checkout <commit>), build theme, cài lại plugin bên thứ ba theo plugins.lock.json (xem TRIEN-KHAI.md mục 3–4)

# 6. Làm sạch bộ nhớ đệm rồi kiểm tra
wp cache flush
wp rewrite flush
node scripts/ops/smoke.mjs https://<tên-miền> --env=production
```

Khôi phục vào **staging** để kiểm tra (không dùng production): đặt `WP_ENVIRONMENT_TYPE = staging` và `VG_MAIL_ALLOW` **trước khi** mở site, để bản sao dữ liệu thật **không gửi thư tới ứng viên thật** và không lên Google.
Nếu đổi tên miền, chạy `wp search-replace 'https://<tên-cũ>' 'https://<tên-mới>' --skip-columns=guid`.

## 4. Khôi phục từng phần

- **Một CV bị mất hoặc hỏng:** `restic restore <snapshot> --target /tmp/restore --include /srv/vgarco/private/cv/<năm>/<tháng>/<tên-tệp>` rồi chép về đúng đường dẫn (tên tệp ghi ở trường `cv_duong_dan` của hồ sơ).
- **Ảnh dự án bị xóa nhầm:** khôi phục tệp từ `uploads`; nếu bản ghi đính kèm cũng mất thì nhập lại ảnh bằng `wp vgarco import` (nạp theo nội dung, không nhân đôi).
- **Nội dung dự án bị sửa sai:** nếu còn trong bảng tính nguồn `content/projects.csv` thì nhập lại (ô có giá trị thì bảng thắng); nếu không, khôi phục CSDL vào staging và lấy lại từ đó.

## 5. Biên bản diễn tập khôi phục (mẫu — điền khi diễn tập; cần có trước khi bàn giao)

| Mục | Giá trị |
|---|---|
| Ngày, giờ bắt đầu → kết thúc | |
| Người thực hiện | |
| Mã snapshot đã dùng và thời điểm của bản sao lưu | |
| Môi trường khôi phục | staging |
| Thời gian khôi phục (đo bằng đồng hồ; mục tiêu ≤ 2 giờ) | |
| Trang chủ hiển thị đúng | ☐ |
| Mở được một trang chi tiết dự án có ảnh | ☐ |
| Một CV tải được từ trang quản trị (và đúng nội dung) | ☐ |
| Đăng nhập quản trị và vào *Hồ sơ ứng tuyển* | ☐ |
| `scripts/ops/smoke.mjs` | ☐ đạt |
| Vấn đề gặp phải và cách xử lý | |
| Kết luận (đạt / không đạt) và chữ ký người thực hiện, người xác nhận của chủ đầu tư | |

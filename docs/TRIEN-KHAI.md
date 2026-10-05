# Triển khai

> **Trạng thái: thiết kế, chưa chạy trên máy chủ thật.** Kho này **chưa có** `provision.sh` (dựng máy chủ) và `deploy.sh` (đưa mã lên); tên miền và máy chủ được hoãn theo yêu cầu.
> Những gì **đã chạy được và kiểm chứng**: dựng website trên WordPress 7.1 thật bằng phòng thử (`tests/wp-lab`), build theme, bộ kiểm thử, và script kiểm nhanh `scripts/ops/smoke.mjs` (chạy với bất kỳ địa chỉ nào).
> Tài liệu này ghi quy trình để khi có máy chủ chỉ còn việc *chạy*, không phải *nghĩ lại*.

## 1. Cái gì nằm ở đâu

| Thành phần | Nguồn | Vào máy chủ bằng cách |
|---|---|---|
| Mã tự viết: `vgarco-loader.php`, `vgarco-core/`, theme `vgarco/` | Git (kho này) | Sao chép sau khi build theme |
| Lõi WordPress, WPML, ACF PRO, FluentSMTP, WP 2FA, WPS Hide Login, Redis Object Cache, Modern Image Formats, Patchstack | `plugins.lock.json` (tên, lý do, bắt buộc/tùy chọn) — **không** nằm trong Git | Cài bằng WP-CLI từ nguồn chính thức; WPML và ACF PRO bằng tệp `.zip` + khóa giấy phép đứng tên VGARCO |
| `wp-config.php`, khóa bí mật | Máy chủ (`/etc/vgarco/secrets.php`) | Xem `CAU-HINH.md` |
| Ảnh tải lên (`uploads`), CV (`/srv/vgarco/private`) | Máy chủ; có trong sao lưu | Không triển khai; chỉ sao lưu/khôi phục |

## 2. Thiết kế máy chủ (tóm tắt)

| Lớp | Thiết lập |
|---|---|
| Hệ điều hành | Ubuntu LTS; người dùng `deploy` (sở hữu mã, chạy WP-CLI), `vgarco` (người dùng PHP-FPM, không có shell), `backup`; tắt đăng nhập root; SSH chỉ khóa công khai, `AllowUsers deploy`, fail2ban |
| Tường lửa | `ufw` mặc định chặn vào; **443 chỉ mở cho dải IP Cloudflare** (làm mới hằng tuần); không mở cổng 80 |
| Nginx | `real_ip_header CF-Connecting-IP`; `default_server` với `ssl_reject_handshake on`; Authenticated Origin Pulls (chứng chỉ riêng của zone); `client_max_body_size 16m`; chặn thực thi PHP trong `uploads`, chặn `xmlrpc.php`, tệp ẩn (trừ `.well-known`), `.sql .bak .log .ini .sh .lock`, **`readme.html` và `license.txt`**; `/srv/vgarco/private` **ngoài** `root` |
| PHP-FPM 8.3 | Hai pool (production, staging), mỗi pool một người dùng; `disable_functions = exec,passthru,shell_exec,system,proc_open,popen`; `expose_php = Off`; OPcache `validate_timestamps = 0` (triển khai xong thì reload pool); **`memory_limit` ≥ 256 MB** (ảnh toàn cảnh 12 MB cần ~190 MB khi tạo bản thu nhỏ); extension `gd` (có AVIF), `zip`, `fileinfo`, `mbstring`, `intl` (`exif` tùy chọn: mã tự đọc hướng xoay ảnh) |
| Quyền tệp | Mã thuộc `deploy`, PHP **chỉ đọc**; PHP chỉ ghi được `wp-content/uploads` và `/srv/vgarco/private` — kẻ chiếm được tài khoản quản trị cũng không cài được plugin hay sửa mã |
| CSDL, Redis | MariaDB chỉ nghe `127.0.0.1`, user riêng cho mỗi môi trường; Redis chỉ localhost, có mật khẩu, `maxmemory 256mb`, `allkeys-lru` |
| Cron hệ thống | `wp cron event run --due-now` (mỗi 5 phút; **mỗi phút** nếu muốn xóa cache Cloudflare sát 30 giây); sao lưu 02:30; làm mới IP Cloudflare và kiểm hạn chứng chỉ hằng tuần |
| Chứng chỉ | Let's Encrypt (certbot, xác minh qua Cloudflare → cổng 443). Let's Encrypt đã **ngừng gửi email cảnh báo hết hạn** nên phải có kiểm tra hạn chứng chỉ riêng |

Chi tiết Cloudflare (DNS, SSL Full strict, Cache Rules, WAF, rate limiting) nằm ở mục 10.1 của phương án triển khai nội bộ; điều **bắt buộc** với mã này: máy chủ gốc chỉ nhận kết nối từ Cloudflare
(xem `KIEN-TRUC.md` mục 6 — `vgarco_client_ip()` tin `CF-Connecting-IP`) và **không** cache các đường `/wp-admin`, đường dẫn đăng nhập, `/ung-tuyen/`, `/wp-json/vgarco/v1/apply*`.

## 3. Cài mới (lần đầu, mỗi môi trường)

Thứ tự có ý nghĩa: **ACF PRO phải có trước `vgarco-core`** (không có thì trang quản trị báo thiếu và không có trường nhập liệu).

```bash
# 1. Lõi WordPress, cấu hình (wp-config.php theo CAU-HINH.md), CSDL
wp core install --url=https://<tên-miền> --title="VGARCO" --admin_user=<không-dùng-admin> --admin_email=<hộp-thư-vận-hành> --skip-email

# 2. Plugin theo plugins.lock.json (bản quyền: tệp .zip + khóa)
wp plugin install <acf-pro.zip> <wpml-multilingual-cms.zip> <wpml-string-translation.zip> <acfml.zip> --activate
wp plugin install fluent-smtp wp-2fa wps-hide-login redis-cache modern-image-formats patchstack --activate   # kiểm tra tên gói khi cài

# 3. Mã tự viết (mu-plugin tự chạy; plugin và theme cần kích hoạt)
wp plugin activate vgarco-core
wp theme activate vgarco

# 4. Đường dẫn, múi giờ, trang theo mẫu
wp option update permalink_structure '/%postname%/'
wp option update timezone_string 'Asia/Ho_Chi_Minh'
wp rewrite flush
```

Trang cần tạo (tiêu đề → đường dẫn → mẫu); **bản đồ đường dẫn này đã được kiểm chứng trên WordPress thật** (`tests/wp-lab/it_site.php`):

| Trang | Đường dẫn | Mẫu |
|---|---|---|
| Trang chủ (đặt làm trang chủ tĩnh) | `/` | — |
| Về công ty | `/gioi-thieu/` | `templates/about.php` |
| Ban lãnh đạo | `/gioi-thieu/ban-lanh-dao/` (**trang con** của Về công ty) | `templates/leadership.php` |
| Phòng ban | `/gioi-thieu/phong-ban/` (trang con) | `templates/departments.php` |
| Lĩnh vực hoạt động | `/linh-vuc-hoat-dong/` | `templates/services.php` |
| Tuyển dụng | `/tuyen-dung/` (cùng tiền tố với chi tiết vị trí `/tuyen-dung/<slug>/` — đã kiểm chứng không xung đột) | `templates/careers.php` |
| Ứng tuyển | `/ung-tuyen/` (noindex) | `templates/apply.php` |
| Liên hệ | `/lien-he/` | `templates/contact.php` |
| Chính sách bảo vệ dữ liệu | `/chinh-sach-bao-ve-du-lieu/` | mặc định |

Dự án `/du-an/`, tin tức `/tin-tuc/`, vị trí tuyển dụng `/tuyen-dung/<slug>/` do kiểu nội dung tạo; **không** tạo trang cho chúng.

```bash
# 5. Cấu hình bản dịch/WPML theo mục 6.7 của phương án (chạy thử trên staging trước), rồi:
# 6. Nhập nội dung (xem VAN-HANH.md mục 2)
wp vgarco import projects content/projects.csv --images=<thư-mục-ảnh> --dry-run
wp vgarco import projects content/projects.csv --images=<thư-mục-ảnh>

# 7. Tài khoản Nhân sự tuyển dụng
wp user create <tên> <email> --role=vg_tuyen_dung
```

Việc **chưa được kiểm chứng** ở bước này vì chưa có giấy phép: nối với ACF PRO thật (trang cấu hình, trường lặp), WPML/ACFML (URL `/de/`, dịch đường dẫn, đồng bộ trường) — đây là việc đầu tiên trên staging (xem `README.md` gốc, mục Trạng thái).

## 4. Triển khai bản mới (mã tự viết)

Làm trên **staging trước**, kiểm tra, rồi mới production.

```bash
# Trên máy build (có Node 20+ và PHP 8.3)
git pull
node tests/run.mjs                         # toàn bộ kiểm thử (PHP=… PHP_EXT=… nếu php không nằm trong PATH); phải "Tất cả đạt"
cd wp-content/themes/vgarco && npm ci && npm run build && cd -   # sinh dist/ (không commit)

# Đưa lên máy chủ: chỉ ba thứ này, thuộc người dùng deploy, PHP chỉ đọc
rsync -a wp-content/mu-plugins/vgarco-loader.php  deploy@<máy>:/var/www/vgarco/public/wp-content/mu-plugins/
rsync -a --delete wp-content/plugins/vgarco-core/  deploy@<máy>:/var/www/vgarco/public/wp-content/plugins/vgarco-core/
rsync -a --delete --exclude node_modules --exclude src \
  wp-content/themes/vgarco/                deploy@<máy>:/var/www/vgarco/public/wp-content/themes/vgarco/

# Trên máy chủ
sudo systemctl reload php8.3-fpm           # OPcache validate_timestamps = 0: không reload thì mã cũ vẫn chạy
wp rewrite flush                           # CHỈ khi đổi kiểu nội dung, phân loại hoặc quy tắc đường dẫn
wp eval 'var_dump( vgarco_cf_purge() );'   # xóa cache Cloudflare ngay (true = xong)
node scripts/ops/smoke.mjs https://<tên-miền> --env=production   # từ máy bất kỳ
```

Trước khi đưa lên production: **sao lưu** (xem `SAO-LUU-PHUC-HOI.md`) và ghi lại mã commit đang chạy (`git rev-parse HEAD`) để hoàn tác.

**Hoàn tác:** chạy lại các bước trên với commit trước đó (`git checkout <commit-trước>`, build, rsync, reload). Thay đổi **dữ liệu** (nhập liệu) không hoàn tác bằng mã: khôi phục từ sao lưu.
Thiết kế tốt hơn khi viết `deploy.sh`: mỗi lần đưa lên một thư mục `releases/<mã-commit>` rồi đổi liên kết `current`, hoàn tác chỉ là đổi lại liên kết và reload.

## 5. Kiểm tra sau triển khai

```bash
node scripts/ops/smoke.mjs <địa-chỉ-gốc> --env=local|staging|production
```

Script **chỉ đọc**, kiểm: 8 trang chính trả 200 và có H1; 404 thật và có `noindex`; tìm kiếm REST (và từ chối thiếu `lang`); xin mã bảo mật (có token, ảnh, không cache); `xmlrpc.php`, `/?author=1`, `/wp-json/wp/v2/users` đã đóng;
`/wp-config.php`, `/.git/config`, `/.env`, nhật ký debug, danh sách thư mục `uploads` (và `readme.html`, `license.txt` ở staging/production) không lộ; không tải phông/script từ miền ngoài.
Với `--env=production` thêm: HTTPS, HSTS, `X-Content-Type-Options`, chống nhúng khung, `Referrer-Policy`, không lộ phiên bản PHP, cho lập chỉ mục, `http://` chuyển sang `https://`; staging và local phải có `noindex`.
Mỗi lần chạy xin **một** mã bảo mật (tính vào giới hạn 30 lượt/giờ/IP). Đã chạy trên WordPress thật của phòng thử (`--env=local`: 24 phép kiểm đạt) và có trong `node tests/wp-lab/run.mjs`; các phép kiểm header của production **chưa chạy trên Nginx thật**.

Kiểm tay thêm trước khi mở cho khách (xem mục 12 của phương án): trang ứng tuyển hiện ảnh mã bảo mật đọc được (ảnh dựng bằng GD trên PHP của máy chủ thật), thử nộp một hồ sơ giả rồi xóa; thư xác nhận tới Gmail/Outlook/Zoho vào hộp chính;
SSL Labs và securityheaders.com hạng A; mail-tester ≥ 9/10; diễn tập khôi phục có biên bản.

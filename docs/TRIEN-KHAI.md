# Triển khai

> **Trạng thái: thiết kế, mới chạy thử một phần.** `scripts/ops/provision.sh` (dựng máy chủ) đã chạy thật 7/9 bước (`packages users php mariadb redis nginx cron`, hai lần liên tiếp để kiểm tính chạy lại được) trên một VM Ubuntu 24.04 thử ngày 29/10/2026; hai bước `ssh` và `firewall` **chưa chạy thật** vì dễ tự khóa mình. `scripts/ops/deploy.sh` (đưa mã lên, tự hoàn tác khi kiểm khói hỏng) vẫn là **bản thiết kế**: mới kiểm được cú pháp (`bash -n`),
> chế độ `--dry-run` (in kế hoạch) và các chốt chặn tham số, nên còn những chỗ phải kiểm lần đầu (đã ghi trong tiêu đề mỗi tệp). Tên miền và máy chủ chính thức được hoãn theo yêu cầu.
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

**Bản tự động: `scripts/ops/deploy.sh`** (bản thiết kế, chưa chạy thật) làm đúng chuỗi trên và thêm các chốt chặn:

```bash
scripts/ops/deploy.sh --env=staging --host=deploy@<máy> --domain=<tên-miền-staging> --dry-run   # xem kế hoạch
scripts/ops/deploy.sh --env=staging --host=deploy@<máy> --domain=<tên-miền-staging>
scripts/ops/deploy.sh --env=production --host=deploy@<máy> --domain=<tên-miền> --yes-production
scripts/ops/deploy.sh --env=production --host=deploy@<máy> --domain=<tên-miền> --rollback-to=<tên-gói>   # hoàn tác tay
```

Kho Git phải sạch (triển khai từ commit đã có); chạy toàn bộ kiểm thử; build theme; **nén bản đang chạy thành gói hoàn tác** (`/var/www/<site>/rollback/<thời-gian>-<commit>.tar.zst`, giữ 5 gói); rsync với `--delay-updates`; reload PHP-FPM;
xóa cache đối tượng và Cloudflare; chạy `smoke.mjs`; **nếu kiểm khói hỏng thì tự giải gói hoàn tác**, reload, xóa cache và thoát mã 1. Người dùng ssh phải là `deploy` (quyền sudo của `deploy` chỉ gồm `systemctl reload php8.3-fpm`, do `provision.sh` cấp).
Chọn rsync + gói hoàn tác thay cho `releases/<commit>` + liên kết `current` vì WordPress xử lý plugin/theme/mu-plugin qua liên kết tượng trưng nhiều chỗ chưa kiểm chứng (đường dẫn thật của plugin, `plugins_url`); đổi sang liên kết chỉ sau khi thử trên staging.

**Dựng máy chủ: `scripts/ops/provision.sh`** (7/9 bước đã chạy thật trên VM thử, xem cuối mục): Ubuntu 24.04 sạch → người dùng (`deploy`, người dùng PHP không shell, `backup`), SSH chỉ khóa, `ufw` (443 chỉ cho Cloudflare, không mở 80), pool PHP-FPM riêng từng môi trường, MariaDB và Redis chỉ localhost
(mật khẩu sinh ngẫu nhiên, chỉ nằm trong `/etc/vgarco/*.php` quyền 0640), Nginx (khối `default_server` từ chối, khối của tên miền, chặn tệp nhạy cảm, Authenticated Origin Pulls khi đã có CA), cron. Chạy lại được (chỉ ghi tệp khi nội dung đổi, giữ bản `.bak`).
**Rủi ro lớn nhất là tự khóa mình ngoài máy chủ** (SSH, tường lửa): bước `ssh` từ chối chạy nếu `deploy` chưa có khóa công khai, giữ cổng 22 tới khi chạy lại với `--finalize-ssh`, và mở cổng SSH trước khi bật `ufw`.
Chưa có trong script: sao lưu restic, Telegram, cấp chứng chỉ Let's Encrypt lần đầu, tiêu đề bảo mật (HSTS/CSP) của Nginx — vẫn là tài liệu.

*Chạy thử 29/10/2026* (VM Ubuntu 24.04.4, 8 CPU/8 GB, tên miền thử `staging.vgarco.test`, môi trường `staging`; chạy lần lượt từng bước rồi chạy lại cả 7 bước: không tệp nào đổi, không có `.bak`, không dịch vụ nào bị khởi động lại, mật khẩu giữ nguyên). Lỗi tìm được và đã sửa: `http2 on;` chỉ nginx ≥ 1.25.1 hiểu, Ubuntu 24.04 có 1.24.0 nên đổi thành `listen 443 ssl http2`; tập lệnh làm mới IP Cloudflare trước đây chỉ được tạo ở bước `firewall` nên bước `nginx` chạy riêng bị hỏng, nay cả hai bước cùng gọi `cf_helper`.
Kiểm sâu cùng ngày (gửi yêu cầu thật qua Nginx → PHP-FPM bằng một tệp thử tạm, rồi xóa): PHP-FPM chạy đúng người dùng, thư mục mã không ghi được, ảnh và thư mục riêng ghi được, nối CSDL và Redis từ FPM được, `exec()` bị tắt, nhiều tên miền lạ bị từ chối ở bước bắt tay TLS. Lỗi tìm thêm và đã sửa:
- Nhật ký lỗi của pool (`/var/log/php8.3-fpm-<môi trường>.log`) không tạo được vì người dùng PHP không có quyền ghi `/var/log`, nên mọi `error_log()` biến mất: nay bước `php` tạo sẵn tệp (0640, nhóm `adm`) và thêm logrotate.
- `deploy` không đọc được `/etc/vgarco/*.php` nên mọi lệnh `wp` và cron `wp cron` chạy dưới `deploy` đều không nạp được mật khẩu CSDL: nay `deploy` thuộc nhóm của người dùng PHP.
- cron không có `/usr/local/bin` trong `PATH` nên không thấy `wp`: đã thêm dòng `PATH`.
- Nginx giới hạn thân yêu cầu 16 MB nhưng PHP cho 18 MB (tệp 16 MB bị 413): Nginx nay 18 MB.
- Khối Nginx mặc định của gói vẫn nghe cổng 80: đã gỡ `sites-enabled/default`.
- MariaDB: tắt `local_infile`. **Không** thêm `skip-name-resolve`: ứng dụng nối qua `127.0.0.1` và quyền cấp cho `'…'@'localhost'` sẽ không khớp nữa.

Còn mở:
- GD của PHP trên Ubuntu báo `AVIF Support=false` và chưa cài Imagick: nén AVIF cần `php8.3-imagick` (kiểm ImageMagick của Ubuntu có AVIF không) hoặc plugin Modern Image Formats; cần quyết định trước khi đo ngân sách ảnh.
- Tài khoản `backup` đã có sẵn trong Ubuntu (không đăng nhập được), nên script không tạo tài khoản `backup` dùng shell; cần chọn tên khác khi viết phần sao lưu.
- Staging và production chung một máy: `redis.php` và `secrets.php` chỉ được tạo một lần với nhóm của môi trường chạy trước, người dùng PHP của môi trường sau sẽ không đọc được; cần tách theo môi trường hoặc dùng nhóm chung trước khi dựng môi trường thứ hai trên cùng máy.
- WP-CLI chưa cài (cron `wp cron event run` sẽ lỗi tới khi cài). Múi giờ máy là UTC (cron 02:30 sao lưu sẽ là 09:30 giờ Việt Nam), chưa có swap, Nginx chưa nghe IPv6 ở cổng 443 dù `ufw` đã có dải IPv6 của Cloudflare, VM còn 43 gói chờ cập nhật và đang chờ khởi động lại (kernel, libc6).
- `ssh` và `firewall` chưa chạy thật.

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

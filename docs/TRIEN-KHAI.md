# Triển khai

> **Trạng thái: thiết kế, mới chạy thử một phần.** `scripts/ops/provision.sh` (dựng máy chủ) đã chạy thật cả 9 bước trên một VM Ubuntu 24.04 thử ngày 29/10/2026 (7 bước `packages users php mariadb redis nginx cron`, rồi `firewall` và `ssh`; mỗi nhóm chạy lại để kiểm tính chạy lại được). `scripts/ops/deploy.sh` (đưa mã lên, tự hoàn tác khi kiểm khói hỏng) đã chạy thật trên VM đó cùng ngày: lần đầu, lần cập nhật, đường thành công đủ kiểm thử và kiểm khói, và tự hoàn tác khi cố tình đưa lên một bản hỏng (xem cuối mục 4); chưa chạy với `--env=production`. Tên miền và máy chủ chính thức được hoãn theo yêu cầu.
> Những gì **đã chạy được và kiểm chứng**: dựng website trên WordPress 7.1 thật bằng phòng thử (`tests/wp-lab`), build theme, bộ kiểm thử, và script kiểm nhanh `scripts/ops/smoke.mjs` (chạy với bất kỳ địa chỉ nào).
> Tài liệu này ghi quy trình để khi có máy chủ chỉ còn việc *chạy*, không phải *nghĩ lại*.

## 1. Cái gì nằm ở đâu

| Thành phần | Nguồn | Vào máy chủ bằng cách |
|---|---|---|
| Mã tự viết: `vgarco-loader.php`, `vgarco-core/`, theme `vgarco/` | Git (kho này) | Sao chép sau khi build theme |
| Lõi WordPress, Polylang, Secure Custom Fields, FluentSMTP, WP 2FA, WPS Hide Login, Redis Object Cache, Modern Image Formats, Patchstack | `plugins.lock.json` (tên, lý do, bắt buộc/tùy chọn) — **không** nằm trong Git | Cài bằng WP-CLI từ wordpress.org; tất cả miễn phí, không có giấy phép hay tệp `.zip` riêng |
| `wp-config.php`, khóa bí mật | Máy chủ (`/etc/vgarco/secrets.php`) | Xem `CAU-HINH.md` |
| Ảnh tải lên (`uploads`), CV (`/srv/vgarco/private`) | Máy chủ; có trong sao lưu | Không triển khai; chỉ sao lưu/khôi phục |

## 2. Thiết kế máy chủ (tóm tắt)

| Lớp | Thiết lập |
|---|---|
| Hệ điều hành | Ubuntu LTS; người dùng `deploy` (sở hữu mã, chạy WP-CLI), `vgarco` (người dùng PHP-FPM, không có shell), `backup`; tắt đăng nhập root; SSH chỉ khóa công khai, `AllowUsers deploy` cùng tài khoản quản trị đã chạy `sudo provision.sh` (để còn một đường vào có quyền root), fail2ban |
| Tường lửa | `ufw` mặc định chặn vào; **443 chỉ mở cho dải IP Cloudflare** (làm mới hằng tuần); không mở cổng 80 |
| Nginx | `real_ip_header CF-Connecting-IP`; `default_server` với `ssl_reject_handshake on`; Authenticated Origin Pulls (chứng chỉ riêng của zone); `client_max_body_size 18m` (bằng `post_max_size` của PHP); `server_tokens off`; chặn thực thi PHP trong `uploads`, chặn `xmlrpc.php`, tệp ẩn (trừ `.well-known`), `.sql .bak .log .ini .sh .lock`, **`readme.html` và `license.txt`**; `/srv/vgarco/private` **ngoài** `root` |
| PHP-FPM 8.3 | Hai pool (production, staging), mỗi pool một người dùng; `disable_functions = exec,passthru,shell_exec,system,proc_open,popen`; `expose_php = Off`; OPcache `validate_timestamps = 0` (triển khai xong thì reload pool); **`memory_limit` ≥ 256 MB** (ảnh toàn cảnh 12 MB cần ~190 MB khi tạo bản thu nhỏ); `max_input_vars = 3000` (mặc định 1000 làm cắt cụt biểu mẫu ACF và menu lớn); extension `gd`, `imagick` (**mã hóa AVIF đi qua Imagick**: `gd` của Ubuntu không có AVIF, kiểm bằng `Imagick::queryFormats('AVIF')`), `zip`, `fileinfo`, `mbstring`, `intl` (`exif` tùy chọn: mã tự đọc hướng xoay ảnh) |
| Bộ nhớ | `provision.sh` đọc RAM và đặt cỡ theo bậc: từ 7 GB trở lên `innodb_buffer_pool_size = 1G`, 8 tiến trình PHP mỗi pool; 3,5–7 GB thì 512M và 5 tiến trình (pool staging 3); dưới 3,5 GB thì 256M và 3. Dưới 7 GB còn tự tạo `/swapfile` 2 GB (`vm.swappiness = 10`) nếu máy chưa có swap. Gói VPS 2 vCPU/4 GB/40 GB đã chọn (30/10/2026) nằm ở bậc giữa; **chưa đo thực tế**, đo lại sau khi dựng staging và nâng gói nếu RAM dùng quá khoảng 80% |
| Quyền tệp | Mã thuộc `deploy`, PHP **chỉ đọc**; PHP chỉ ghi được `wp-content/uploads` và `/srv/vgarco/private` — kẻ chiếm được tài khoản quản trị cũng không cài được plugin hay sửa mã |
| CSDL, Redis | MariaDB chỉ nghe `127.0.0.1`, user riêng cho mỗi môi trường; Redis chỉ localhost, có mật khẩu, `maxmemory 256mb`, `allkeys-lru` |
| Cron hệ thống | `wp cron event run --due-now` (mỗi 5 phút; **mỗi phút** nếu muốn xóa cache Cloudflare sát 30 giây); sao lưu 02:30; làm mới IP Cloudflare và kiểm hạn chứng chỉ hằng tuần |
| Chứng chỉ | Let's Encrypt (certbot, xác minh qua Cloudflare → cổng 443). Let's Encrypt đã **ngừng gửi email cảnh báo hết hạn** nên phải có kiểm tra hạn chứng chỉ riêng |

Chi tiết Cloudflare (DNS, SSL Full strict, Cache Rules, WAF, rate limiting) nằm ở mục 10.1 của phương án triển khai nội bộ; điều **bắt buộc** với mã này: máy chủ gốc chỉ nhận kết nối từ Cloudflare
(xem `KIEN-TRUC.md` mục 6 — `vgarco_client_ip()` tin `CF-Connecting-IP`) và **không** cache các đường `/wp-admin`, đường dẫn đăng nhập, `/ung-tuyen/`, `/wp-json/vgarco/v1/apply*`.

## 3. Cài mới (lần đầu, mỗi môi trường)

Thứ tự có ý nghĩa: **Secure Custom Fields phải có trước `vgarco-core`** (không có thì trang quản trị báo thiếu và không có trường nhập liệu). Không cài ACF hoặc ACF PRO song song: SCF tự tắt chúng.

```bash
# 1. Lõi WordPress, cấu hình (wp-config.php theo CAU-HINH.md), CSDL
wp core install --url=https://<tên-miền> --title="VGARCO" --admin_user=<không-dùng-admin> --admin_email=<hộp-thư-vận-hành> --skip-email
wp language core install vi --activate   # bắt buộc khi chưa bật Polylang (Mốc 1): thiếu thì <html lang> thành en-US và phông tiếng Việt không được nạp trước

# 2. Plugin theo plugins.lock.json (ghim đúng bản đã kiểm thử)
wp plugin install secure-custom-fields --version=6.9.7 --activate
wp plugin install polylang --version=3.9.1 --activate
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
# 5. Ngôn ngữ: tạo vi (mặc định, không tiền tố) và de (/de/), đặt tùy chọn Polylang, gán tiếng Việt cho nội dung đã có; chạy lại an toàn
wp vgarco languages
# Rồi:
# 6. Nhập nội dung (xem VAN-HANH.md mục 2)
wp vgarco import projects content/projects.csv --images=<thư-mục-ảnh> --dry-run
wp vgarco import projects content/projects.csv --images=<thư-mục-ảnh>

# 7. Tài khoản Nhân sự tuyển dụng
wp user create <tên> <email> --role=vg_tuyen_dung
```

Đã kiểm chứng trong phòng thử (`tests/wp-lab`) trên Secure Custom Fields 6.9.7 và Polylang 3.9.1 thật, WordPress 7.1.2: trang cấu hình và trường lặp, URL `/de/` và trang chủ tiếng Đức ở `/de/`, bộ chuyển ngôn ngữ và `hreflang`, nhập dự án có bản Đức, sao chép trường khi tạo bản dịch, cấu hình riêng cho bản Đức. **Chưa kiểm chứng**: thao tác trên giao diện bằng trình duyệt (nút "+" của Polylang, màn hình nhập liệu), MariaDB thật (phòng thử dùng SQLite) và việc dịch tên phân loại sang tiếng Đức; đó là các việc đầu tiên trên staging. Giới hạn của Polylang miễn phí: không dịch được phần tiền tố cố định của địa chỉ, nên `/du-an/`, `/tin-tuc/`, `/tuyen-dung/` giữ nguyên ở bản `/de/`; các trang (Về công ty, Liên hệ…) có địa chỉ tiếng Đức riêng như bảng trên khi tạo bản dịch.

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

**Bản tự động: `scripts/ops/deploy.sh`** (đã chạy thật trên staging thử, xem cuối mục này) làm đúng chuỗi trên và thêm các chốt chặn:

```bash
scripts/ops/deploy.sh --env=staging --host=deploy@<máy> --domain=<tên-miền-staging> --dry-run   # xem kế hoạch
scripts/ops/deploy.sh --env=staging --host=deploy@<máy> --domain=<tên-miền-staging>
scripts/ops/deploy.sh --env=production --host=deploy@<máy> --domain=<tên-miền> --yes-production
scripts/ops/deploy.sh --env=production --host=deploy@<máy> --domain=<tên-miền> --rollback-to=<tên-gói>   # hoàn tác tay
```

Kho Git phải sạch (triển khai từ commit đã có); chạy toàn bộ kiểm thử; build theme; **nén bản đang chạy thành gói hoàn tác** (`/var/www/<site>/rollback/<thời-gian>-<commit>.tar.zst`, giữ 5 gói); rsync với `--delay-updates` (máy không có `rsync`, như máy Windows của người vận hành, thì gửi bằng `tar` qua ssh: mỗi thư mục dựng ở `.new` rồi đổi tên); reload PHP-FPM;
xóa cache đối tượng và Cloudflare; chạy `smoke.mjs`; **nếu kiểm khói hỏng thì tự giải gói hoàn tác**, reload, xóa cache và thoát mã 1. Người dùng ssh phải là `deploy` (quyền sudo của `deploy` chỉ gồm `systemctl reload php8.3-fpm`, do `provision.sh` cấp).
Chọn rsync + gói hoàn tác thay cho `releases/<commit>` + liên kết `current` vì WordPress xử lý plugin/theme/mu-plugin qua liên kết tượng trưng nhiều chỗ chưa kiểm chứng (đường dẫn thật của plugin, `plugins_url`); đổi sang liên kết chỉ sau khi thử trên staging.

**Dựng máy chủ: `scripts/ops/provision.sh`** (9/9 bước đã chạy thật trên VM thử, xem cuối mục): Ubuntu 24.04 sạch → người dùng (`deploy`, người dùng PHP không shell, `backup`), SSH chỉ khóa, `ufw` (443 chỉ cho Cloudflare, không mở 80), pool PHP-FPM riêng từng môi trường, MariaDB và Redis chỉ localhost
(mật khẩu sinh ngẫu nhiên, chỉ nằm trong `/etc/vgarco/*.php` quyền 0640), Nginx (khối `default_server` từ chối, khối của tên miền, chặn tệp nhạy cảm, Authenticated Origin Pulls khi đã có CA), cron. Chạy lại được (chỉ ghi tệp khi nội dung đổi, giữ bản `.bak`).
**Rủi ro lớn nhất là tự khóa mình ngoài máy chủ** (SSH, tường lửa): bước `ssh` từ chối chạy nếu `deploy` hoặc tài khoản quản trị đang chạy `sudo` chưa có khóa công khai (`AllowUsers deploy <quản trị>`; `deploy` không có quyền root nên không để lại tài khoản quản trị thì sau bước này không ai chạy lại được kịch bản), giữ cổng 22 tới khi chạy lại với `--finalize-ssh` (chỉ khi đổi cổng bằng `--ssh-port`; với cổng 22 tham số này bị từ chối vì nó sẽ xóa luôn quy tắc SSH), và mở cổng SSH trước khi bật `ufw`.
Chưa có trong script: sao lưu restic, Telegram, cấp chứng chỉ Let's Encrypt lần đầu, tiêu đề bảo mật (HSTS/CSP) của Nginx — vẫn là tài liệu.

*Chạy thử 29/10/2026* (VM Ubuntu 24.04.4, 8 CPU/8 GB, tên miền thử `staging.vgarco.test`, môi trường `staging`; chạy lần lượt từng bước rồi chạy lại cả 7 bước: không tệp nào đổi, không có `.bak`, không dịch vụ nào bị khởi động lại, mật khẩu giữ nguyên). Lỗi tìm được và đã sửa: `http2 on;` chỉ nginx ≥ 1.25.1 hiểu, Ubuntu 24.04 có 1.24.0 nên đổi thành `listen 443 ssl http2`; tập lệnh làm mới IP Cloudflare trước đây chỉ được tạo ở bước `firewall` nên bước `nginx` chạy riêng bị hỏng, nay cả hai bước cùng gọi `cf_helper`.
Kiểm sâu cùng ngày (gửi yêu cầu thật qua Nginx → PHP-FPM bằng một tệp thử tạm, rồi xóa): PHP-FPM chạy đúng người dùng, thư mục mã không ghi được, ảnh và thư mục riêng ghi được, nối CSDL và Redis từ FPM được, `exec()` bị tắt, nhiều tên miền lạ bị từ chối ở bước bắt tay TLS. Lỗi tìm thêm và đã sửa:
- Nhật ký lỗi của pool (`/var/log/php8.3-fpm-<môi trường>.log`) không tạo được vì người dùng PHP không có quyền ghi `/var/log`, nên mọi `error_log()` biến mất: nay bước `php` tạo sẵn tệp (0640, nhóm `adm`) và thêm logrotate.
- `deploy` không đọc được `/etc/vgarco/*.php` nên mọi lệnh `wp` và cron `wp cron` chạy dưới `deploy` đều không nạp được mật khẩu CSDL: nay `deploy` thuộc nhóm của người dùng PHP.
- cron không có `/usr/local/bin` trong `PATH` nên không thấy `wp`: đã thêm dòng `PATH`.
- Nginx giới hạn thân yêu cầu 16 MB nhưng PHP cho 18 MB (tệp 16 MB bị 413): Nginx nay 18 MB.
- Khối Nginx mặc định của gói vẫn nghe cổng 80: đã gỡ `sites-enabled/default`.
- MariaDB: tắt `local_infile`. **Không** thêm `skip-name-resolve`: ứng dụng nối qua `127.0.0.1` và quyền cấp cho `'…'@'localhost'` sẽ không khớp nữa.

Vòng kiểm thứ ba (cùng ngày) tìm và sửa thêm:
- Thư mục `uploads` thuộc nhóm `deploy` nên thư mục tháng do `wp` (chạy dưới `deploy`) tạo ra không ghi được bởi người dùng PHP (tải ảnh trong trang quản trị và tạo bản thu nhỏ sẽ lỗi): nay nhóm của `uploads` là nhóm của người dùng PHP, mà `deploy` đã thuộc nhóm này, nên hai bên ghi được tệp của nhau (đã thử cả hai chiều).
- Khối `default_server` trả 200 cho `Host` lạ khi đã bắt tay TLS bằng tên miền đúng: nay `return 444`; `server_tokens off` áp cho cả khối này (trang lỗi 400 không còn lộ phiên bản).
- Bước `ssh` chỉ cho `deploy` vào, mà `deploy` không có quyền root nên sau bước này không ai chạy lại được kịch bản (cấp chứng chỉ, cập nhật): nay thêm tài khoản quản trị đang chạy `sudo` vào `AllowUsers`, và từ chối chạy nếu tài khoản đó chưa có khóa công khai.
- Redis báo `vm.overcommit_memory` = 0 (lưu nền có thể lỗi khi thiếu RAM): bước `redis` đặt `vm.overcommit_memory = 1` (tệp `/etc/sysctl.d/99-vgarco-redis.conf`).
- Chứng chỉ tự ký thiếu SAN: đã thêm `subjectAltName`.
- `gd` không có AVIF nên mục "kiểm khi chạy thật" của chính script không đạt: thêm `php8.3-imagick`; Imagick 6.9.12 mã hóa và giải mã AVIF được (thử bên trong PHP-FPM).
- `max_input_vars = 3000`.

Cách kiểm cuối cùng: dọn máy thử về trạng thái sạch (gỡ đúng các gói do script cài, xóa thư mục, tài khoản, quy tắc `ufw` do script tạo), dựng lại bằng từng bước một (`packages users php mariadb redis nginx cron`, mỗi bước một lần gọi riêng), kiểm từng bước và toàn bộ (yêu cầu thật qua Nginx → PHP-FPM, quyền ghi hai chiều giữa `deploy` và người dùng PHP, giới hạn tải lên 17/18 MB, `Host` lạ, chứng chỉ, nhật ký), rồi chạy lại cả 7 bước: không tệp nào đổi, không có `.bak`, không dịch vụ nào bị khởi động lại, mật khẩu giữ nguyên.

*Chạy thật `firewall` rồi `ssh` (29/10/2026, cùng VM)*. Chuẩn bị: `--dry-run` trước; hẹn giờ tự hoàn tác bằng `systemd-run` (4 phút tắt `ufw`; 5 phút gỡ `10-vgarco.conf` và nạp lại `ssh`), hủy sau khi kiểm xong; khóa công khai của người vận hành thêm vào `ubuntu` và `deploy` trước khi chạy `ssh`.
- `firewall`: `ufw` bật, chặn vào và cho ra mặc định, `22/tcp` (IPv4 và IPv6) cùng 22 quy tắc cổng 443 cho Cloudflare; kết nối SSH mới vẫn vào được, 443 từ máy ngoài Cloudflare bị chặn, 80 đóng, Nginx tải lại bình thường.
- `ssh`: `sshd -T` cho kết quả đúng (không mật khẩu, không `root`, `AllowUsers deploy ubuntu`, `MaxAuthTries 3`, sau đó thêm `X11Forwarding no`, `LoginGraceTime 30`); thử đăng nhập bằng mật khẩu và bằng `root` đều bị từ chối, đăng nhập bằng khóa ở kết nối mới vẫn vào được; `ssh.service` được bật, `ssh.socket` vẫn chạy.
- Chạy lại cả hai bước: tập quy tắc `ufw` (24 = 22 Cloudflare + 2 SSH), mã băm các tệp cấu hình và tiến trình `sshd`, fail2ban, Nginx không đổi, không có `.bak` mới (đường xóa rồi thêm lại 22 quy tắc Cloudflare lúc `ufw` đang bật, cũng là đường tác vụ làm mới IP định kỳ dùng, chạy đúng).
Lỗi tìm được và đã sửa:
- Jail `sshd` của fail2ban chỉ đọc nhật ký của `sshd.service`, nhưng Ubuntu 24.04 ghi vào `ssh.service` (221 dòng so với 1), nên fail2ban không thấy lần đăng nhập sai nào: nay jail có `backend = systemd` và `journalmatch = _SYSTEMD_UNIT=ssh.service + _COMM=sshd`; bộ lọc khớp dòng thật và hành động cấm (qua `nftables`) đã thử bằng địa chỉ TEST-NET rồi gỡ.
- `--finalize-ssh` ở cổng 22 chạy `ufw allow 22/tcp` rồi `ufw delete allow 22/tcp`, tức tự đóng SSH; ghi chú sau bước `ssh` cũng nhắc chạy lại với tham số này kể cả ở cổng 22. Nay tham số bị từ chối (mã 2, không đổi gì) và ghi chú chỉ nhắc khi đổi cổng.
Chưa kiểm: đăng nhập `deploy` bằng khóa (khóa riêng có passphrase, người vận hành tự thử); cấm thật sau 4 lần sai (không thử, vì ở phòng thử mọi SSH tới VM đi qua máy chuyển tiếp mạng nên mọi kết nối mang cùng một địa chỉ nguồn (của máy chuyển tiếp), bị cấm thì cả mạng này mất SSH tới VM trong 1 giờ; máy thật thấy IP thật).

Kiểm sâu sau khi bật `firewall` và `ssh` (cùng ngày): chạy lại toàn bộ bộ kiểm của 7 bước (yêu cầu thật qua Nginx → PHP-FPM, nối CSDL và Redis từ FPM, quyền ghi hai chiều, giới hạn tải lên 17/18 MB, `Host` lạ, chứng chỉ, chạy lại không đổi gì) đều đạt. Cổng nghe chỉ có 22 và 443 (MariaDB, Redis chỉ localhost); danh sách Cloudflare trong Nginx và `ufw` khớp danh sách hiện hành; dấu vân khóa của người vận hành trên `deploy` trùng khóa trên máy họ; `deploy` bị khóa mật khẩu nhưng `UsePAM yes` nên vẫn vào được bằng khóa. Tìm thêm (hai điểm đầu đã sửa và chạy lại `firewall`, `ssh` thật cùng ngày, hẹn giờ tự hoàn tác như lần trước; bộ kiểm lớn chạy lại không có khác biệt ngoài thời gian và bộ đếm):
- `rsyslog` không tạo được `/var/log/ufw.log` vì `/var/log` trên ảnh VM này là 755 `root:syslog` (bản chuẩn 775): nhật ký `ufw` chỉ có trong `journalctl -k`. Đã sửa: bước `firewall` tạo sẵn tệp (`syslog:adm`, 0640) rồi khởi động lại `rsyslog` khi tệp chưa có, như đã làm với nhật ký PHP (gói `ufw` đã kèm `logrotate`); thử bằng cách thăm dò cổng 443/80 từ ngoài, các dòng `[UFW BLOCK]` vào tệp.
- `sshd` còn `X11Forwarding yes` và `LoginGraceTime 120` (mặc định của Ubuntu). Đã sửa: `10-vgarco.conf` có thêm `X11Forwarding no` và `LoginGraceTime 30` (`sshd -T` xác nhận; đăng nhập bằng khóa ở kết nối mới vẫn vào được).
- Kiểm khói của `deploy.sh` chạy từ máy vận hành tới `https://<tên miền>`; vì `ufw` chỉ cho Cloudflare vào 443, ở phòng thử máy vận hành không tới được. Ở phòng thử đã dùng đường hầm SSH (`ssh -L`, xem cuối mục 4); khi có tên miền thật thì kiểm khói qua Cloudflare.
- Cập nhật gói rồi khởi động lại (cùng ngày): 43 gói đổi, 0 gói bị gỡ, không có gói nào của nginx, PHP, MariaDB, Redis, OpenSSH, fail2ban, `ufw`; chạy bằng `systemd-run` (sống sót khi mất SSH), `dpkg --audit` sạch. Sau khởi động lại (kernel 6.8.0-136 → 147, lên lại sau khoảng 1 phút), bản chụp trạng thái giống hệt trước đó trừ dòng kernel: dịch vụ chạy và bật, 24 quy tắc `ufw`, cổng nghe, cấu hình `sshd`, mã băm các tệp cấu hình, `vm.overcommit_memory = 1`, khóa SSH, đăng nhập CSDL của ứng dụng, jail fail2ban; bộ kiểm lớn chạy lại đều đạt. Hai điều rút ra: cập nhật gói đổi `/var/log` từ 755 thành 775 (cách tạo sẵn `ufw.log` vẫn cần cho ảnh chưa cập nhật); `logrotate -d` chạy riêng một tệp trong `/etc/logrotate.d` báo "thư mục cha không an toàn", đó là do thiếu `su root adm` của cấu hình chính, kiểm đúng cách là `logrotate -d /etc/logrotate.conf` (0 lỗi).

*WP-CLI và WordPress trên VM thử (29/10/2026)*. Bước `packages` nay tự cài WP-CLI bản ghim (2.12.0, mã SHA-512 lấy từ trang phát hành chính thức của dự án, ghim trong script; không khớp thì dừng, không để lại tệp): thử cả đường sai mã băm (thoát mã 1, không cài gì) lẫn đường đúng, chạy lại không đổi gì; `wp` chạy được dưới `deploy`. WordPress 7.1.4 (bản vá của nhánh 7.1 đã kiểm thử, `vi`) cài bằng `wp core download` (WP-CLI tự kiểm băm, `verify-checksums` đạt), `wp-config.php` đọc mật khẩu CSDL, Redis và khóa từ `/etc/vgarco/*.php` (chủ `deploy`, nhóm người dùng PHP, 0640; PHP không ghi được), salt sinh bằng `wp config shuffle-salts`, tiền tố bảng `vgx_`, `wp core install` với tài khoản quản trị không phải `admin` (mật khẩu cất ở `/home/deploy/.wp-admin-initial-password`, quyền 600, không in ra). Yêu cầu thật qua Nginx → PHP-FPM → MariaDB: trang đăng nhập tiếng Việt, REST có phản hồi, `xmlrpc.php`, `readme.html`, `.git`, `debug.log` bị chặn. Lỗi tìm được và đã sửa:
- Mẫu `secrets.php` của script dùng tên `VGARCO_CF_API_TOKEN`, `VGARCO_CF_ZONE_ID`, `VGARCO_TELEGRAM_CHAT_ID` còn mã đọc `VGARCO_CF_TOKEN`, `VGARCO_CF_ZONE`, `VGARCO_TELEGRAM_CHAT`: ai điền theo mẫu sẽ làm xóa cache Cloudflare và cảnh báo Telegram im lặng không chạy. Nay mẫu dùng đúng tên và để các dòng đó ở dạng chú thích, vì mã Cloudflare chỉ kiểm `defined()` (khai báo rỗng là bật tính năng với khóa rỗng); bỏ `VGARCO_IP_SALT` vì mã dùng `wp_salt('auth')`.
- `/wp-config.php` trả 200 (trang trắng, chạy cả WordPress): nay khối Nginx chặn 403.
Chưa làm ở bước dựng máy chủ: cài plugin bên thứ ba (cần tải thêm từ wordpress.org; không còn plugin nào cần giấy phép). Theme, mã tự viết và trang: xem phần chạy thật `deploy.sh` cuối mục 4.

Còn mở (không thuộc bước dựng máy chủ, cần quyết định hoặc việc của vận hành):
- Tài khoản `backup` đã có sẵn trong Ubuntu (không đăng nhập được), nên script không tạo tài khoản `backup` dùng shell; cần chọn tên khác khi viết phần sao lưu.
- Staging và production chung một máy: `redis.php` và `secrets.php` chỉ được tạo một lần với nhóm của môi trường chạy trước, người dùng PHP của môi trường sau sẽ không đọc được; cần tách theo môi trường (không dùng một nhóm chung, vì staging sẽ đọc được khóa production) trước khi dựng môi trường thứ hai trên cùng máy.
- Máy giữ múi giờ UTC (đúng thiết kế: lịch của mã chạy theo UTC, xem `KIEN-TRUC.md`; cron sao lưu 02:30 sẽ là 09:30 giờ Việt Nam), Nginx chưa nghe IPv6 ở cổng 443 (Cloudflare nối bằng IPv4 khi bản ghi gốc chỉ có A), VM thử đã cập nhật hết và khởi động lại ngày 29/10/2026 (xem phần kiểm sâu); `unattended-upgrades` đã bật nên sẽ tự cập nhật bản vá bảo mật.
- Hai dòng cảnh báo vô hại: Redis ghi `overcommit_memory` ở lần khởi động đầu lúc cài gói (trước khi bước `redis` đặt tham số), MariaDB ghi "unset environment variable" của đơn vị systemd Ubuntu.
- Cổng 443 chỉ nhận từ Cloudflare nên tên miền thật phải đi qua Cloudflare (proxy bật) trước khi mở site ra ngoài; chưa có IP công cộng ánh xạ về VM thử.

*Chạy thật `deploy.sh` trên VM thử (29/10/2026).* Máy vận hành (Windows, Git Bash) không có `rsync` nên dùng đường `tar` qua ssh; khóa SSH riêng truyền qua biến `DEPLOY_SSH_OPTS` (ví dụ `-i <khóa> -o IdentitiesOnly=yes`); build theme chạy `npm ci` ở chế độ offline (`npm_config_offline=true`) khi bộ nhớ đệm npm đã đủ (đã chứng minh `npm ci --offline` trên một bản sao tạm trước khi chạy). Vì `ufw` chỉ cho Cloudflare vào 443, kiểm khói đi qua đường hầm `ssh -N -L 127.0.0.1:443:127.0.0.1:443 <tài-khoản-quản-trị>@<máy>`, kèm tệp tiền nạp cho `node` ánh xạ tên miền thử về `127.0.0.1` (không sửa tệp `hosts`) và `NODE_TLS_REJECT_UNAUTHORIZED=0` vì chứng chỉ tự ký. Các lần chạy:
1. Lần đầu (site chưa có trang): kiểm thử đạt, mã lên đúng quyền (`deploy:deploy`, thư mục 755, không mang theo `node_modules`/`src`), kiểm khói hỏng đúng dự kiến; `deploy.sh` báo "lần triển khai đầu, không có bản cũ để hoàn tác" thay vì hoàn tác vào một gói không tồn tại (kẽ hở đã sửa).
2. Kích hoạt plugin khi theme đang chạy gây `Fatal error: Cannot redeclare vgarco_option()`: theme khai báo hai hàm dự phòng khi plugin chưa nạp, plugin khai báo lại. Nghĩa là plugin lỡ bị tắt thì bật lại sẽ lỗi, trong khi thông báo của theme bảo bật lại. Đã sửa (bọc hai hàm bằng `function_exists`), có `tests/php/coexist.php` và 2 phép đột biến; kiểm lại trên WordPress thật: kích hoạt được, vai trò Nhân sự và 4 đường REST có đủ.
3. Tạo 9 trang theo bảng mục 3 rồi kiểm khói: 26/26 đạt.
4. Chạy đầy đủ (mọi kiểm thử, build, tar, kiểm khói 26/26): gói hoàn tác đặt tên theo mã commit đang chạy, `.deployed-ref` được ghi.
5. Tự hoàn tác: một nhánh tạm có `throw` ở đầu `functions.php` làm mọi trang trả 500; kiểm khói hỏng 13 phép; `deploy.sh` giải gói cũ, reload, kiểm khói lại 26/26 và thoát mã 1; `.deployed-ref` giữ nguyên, không còn mã hỏng trên máy chủ; nhánh tạm đã xóa.

Lần cài đầu nên chạy `deploy.sh --no-smoke`, kích hoạt plugin rồi theme, tạo trang (mục 3), rồi chạy lại có kiểm khói. Kiểm khói 26/26 là trên chồng phần mềm thật nhưng **chưa có Secure Custom Fields, Polylang và nội dung thật** (theme dùng giá trị mặc định trong mã), nên chưa chứng minh được trang có dữ liệu thật; đó là việc của staging thật.

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

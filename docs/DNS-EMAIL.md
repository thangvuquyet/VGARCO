# DNS, thư và bảo mật tên miền

Tên miền chính: `vgarco.com.vn` (đăng ký ở Vietnix). **DNS đặt ở Cloudflare**: nhà đăng ký chỉ giữ tên miền và trỏ nameserver về Cloudflare; mọi bản ghi tạo ở Cloudflare. Thư nhân viên dùng gói Email doanh nghiệp 15 hộp thư của Vietnix; thư tự động của website gửi qua Brevo (FluentSMTP). Giá trị bản ghi thư của Vietnix đã có (mục 2; Vietnix tạo sẵn khi mua gói email, Cloudflare nhập tự động ngày 09/11/2026); giá trị của Brevo **chưa có** (chờ xác thực tên miền) nên các ô `<…>` của Brevo điền khi có.

## 1. Bản ghi cho website

| Loại | Tên | Giá trị | Proxy |
|---|---|---|---|
| A | `@` | địa chỉ IPv4 của VPS | Bật (mây cam) |
| AAAA | `@` | chỉ thêm nếu VPS có IPv6 và Nginx đã nghe IPv6 (hiện chưa) | Bật |
| CNAME | `www` | `vgarco.com.vn` | Bật; thêm Redirect Rule 301 `www` → tên miền chính |
| A | `staging` | cùng IP VPS (chỉ khi cần cho khách xem) | Bật |

Cài đặt Cloudflare:
- SSL/TLS ở **Full (strict)**, bật Always Use HTTPS, phiên bản TLS tối thiểu 1.2, bật Authenticated Origin Pulls (đặt CA vào `/etc/nginx/aop/<site>-aop-ca.pem` rồi chạy lại `provision.sh --phase=nginx`).
- Chứng chỉ ở máy chủ gốc: tạo **Origin CA** (SSL/TLS > Origin Server, 15 năm), đặt vào `/etc/nginx/vgarco/<site>-origin.pem` và `<site>-origin.key` (quyền 0600), chạy lại `--phase=nginx`. Không dùng Let's Encrypt kiểu webroot vì cổng 80 đóng.
- **Tắt** Rocket Loader, Web Analytics tự chèn và Email Address Obfuscation: CSP của máy chủ chặn script ngoài.
- HSTS do Nginx đặt, không bật thêm ở Cloudflare.
- Cache Rules: bỏ qua cache cho `/wp-admin`, `/wp-login.php` (hoặc đường dẫn đã đổi), `/ung-tuyen/`, `/wp-json/vgarco/v1/apply*`, và khi có cookie `wordpress_logged_in_` (xem `TRIEN-KHAI.md` mục 2).

## 2. Bản ghi cho thư

Mọi bản ghi thư để **DNS only** (mây xám). Chỉ được có **một** bản ghi SPF.

| Loại | Tên | Giá trị | Nguồn giá trị |
|---|---|---|---|
| A | `mail` | `103.200.23.49` (máy chủ thư của Vietnix; **bắt buộc DNS only**, proxy làm hỏng SMTP/IMAP) | Vietnix |
| MX | `@` | `mail.vgarco.com.vn`, ưu tiên 0 | Vietnix |
| TXT (SPF) | `@` | hiện có: `v=spf1 +mx +a +ip4:103.200.23.49 +include:_spf.mta.vietnix.com ~all`; khi có Brevo sửa thành `v=spf1 +mx +a +ip4:103.200.23.49 +include:_spf.mta.vietnix.com include:<tên SPF của Brevo> ~all` | Vietnix, Brevo |
| TXT (DKIM thư nhân viên) | `default._domainkey` | khóa RSA do Vietnix tạo (đã có, không sửa) | Vietnix |
| DKIM Brevo | theo Brevo (hai CNAME) | theo Brevo | Brevo: Senders, Domains & Dedicated IPs > Domains |
| TXT xác thực Brevo | theo Brevo | theo Brevo | Brevo |
| TXT (DMARC) | `_dmarc` | hiện có (Vietnix tạo sẵn): `v=DMARC1; p=reject;`. Đổi thành `v=DMARC1; p=none; rua=mailto:<hộp thư nhận báo cáo trong tên miền>; adkim=r; aspf=r` **trước khi bật Brevo** | tự đặt |

Quy tắc:
- DMARC `p=reject` của Vietnix không có địa chỉ báo cáo. Thư của website gửi qua Brevo mà Brevo chưa xác thực tên miền (DKIM) sẽ bị Gmail và các nơi khác từ chối; vì vậy hạ xuống `p=none` rồi nâng lại theo lộ trình bên dưới.
- SPF gộp cả hai nơi gửi thư trong **một** bản ghi và dùng không quá 10 lần tra cứu DNS; kiểm bằng công cụ SPF sau khi thêm.
- Lộ trình DMARC: `p=none` trong 2 tuần đầu (đọc báo cáo, sửa nguồn gửi hợp lệ còn thiếu), rồi `p=quarantine; pct=25`, `pct=100`, cuối cùng `p=reject` (khoảng tuần thứ 6, theo `VAN-HANH.md`).
- Thư của website gửi từ một địa chỉ cố định trong tên miền (ví dụ `no-reply@vgarco.com.vn`), đặt ở FluentSMTP (nhà cung cấp Brevo API, bật Force From Email). Trước khi nhận thư thật, giữ staging ở chế độ chặn (`VG_MAIL_ALLOW`).
- Địa chỉ nhận hồ sơ ứng tuyển là một hộp thư công ty thuộc gói 15 hộp thư; đặt trong trang Cấu hình VGARCO.

## 3. Bảo mật tên miền

Làm ở Vietnix và Cloudflare, không cần mua thêm:
- [ ] Bật khóa chuyển tên miền (transfer lock) tại nhà đăng ký.
- [ ] Bật 2FA cho tài khoản nhà đăng ký và Cloudflare; email quản trị là hộp thư công ty, không phải email cá nhân.
- [ ] Bật tự gia hạn và đăng ký trước 2–3 năm; hai người cùng nhận email nhắc hạn.
- [ ] **DNSSEC**: bật ở Cloudflare (DNS > Settings), lấy bản ghi DS, nhập tại trang quản lý tên miền ở Vietnix; kiểm bằng `dig +dnssec vgarco.com.vn`. Cần xác nhận Vietnix cho nhập DS cho `.com.vn`.
- [ ] Token API Cloudflare chỉ quyền Cache Purge (đã có trong `CAU-HINH.md`), cất ở `/etc/vgarco/secrets.php`.
- [ ] Tên miền giống tên (`vgarco-vn…`) không chặn hết được: khi gặp thì báo nhà đăng ký đó hoặc VNNIC.

## 4. Kiểm tra sau khi đặt bản ghi

```bash
dig +short NS vgarco.com.vn                    # hai nameserver của Cloudflare
dig +short MX vgarco.com.vn
dig +short TXT vgarco.com.vn                   # đúng một bản ghi SPF
dig +short TXT _dmarc.vgarco.com.vn
dig +dnssec +short DNSKEY vgarco.com.vn        # sau khi bật DNSSEC
curl -sI https://vgarco.com.vn/ | grep -i -E "strict-transport|content-security|x-frame"
node scripts/ops/smoke.mjs https://vgarco.com.vn --env=production
```

Mục tiêu kế hoạch 5.5: SSL Labs A, securityheaders.com A, mail-tester từ 9 điểm. Gửi thử một thư từ trang ứng tuyển và một thư từ hộp thư nhân viên tới địa chỉ Gmail ngoài; thư không vào thư rác, xem tiêu đề thư thấy `spf=pass`, `dkim=pass`, `dmarc=pass`.

## 5. Thứ tự làm

1. Mua tên miền và VPS; trỏ nameserver về Cloudflare; chờ phân giải. *(Xong 09/11/2026: tên miền Active ở Cloudflare.)* Vietnix tự thêm `nsbak.vietnix.net` vào danh sách nameserver và phải nhờ gỡ; kiểm bằng `nslookup -norecurse -type=NS vgarco.com.vn a.dns-servers.vn` (chỉ được còn hai nameserver Cloudflare) rồi mới thêm bản ghi website, nếu không một phần truy vấn tới nameserver cũ không biết bản ghi mới.
2. Bản ghi website (mục 1) ở chế độ chưa bật proxy cho tới khi chứng chỉ Origin CA đã đặt.
3. Chạy `provision.sh` và `deploy.sh` (xem `TRIEN-KHAI.md`), bật proxy, đổi SSL sang Full (strict), bật Authenticated Origin Pulls.
4. Mua gói email; thêm MX, SPF, DKIM của Vietnix. *(Xong 09/11/2026: Cloudflare nhập sẵn từ DNS Vietnix, `mail` để DNS only; chưa tạo hộp thư nào.)*
5. Xác thực tên miền ở Brevo; thêm DKIM của Brevo; sửa SPF thành một bản ghi gộp; cấu hình FluentSMTP.
6. Thêm DMARC ở `p=none`; thử thư; nâng chính sách theo lộ trình.
7. Bật DNSSEC.

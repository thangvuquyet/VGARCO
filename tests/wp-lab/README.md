# Kiểm thử trên WordPress thật (môi trường thử SQLite)

Bộ kiểm thử PHP trong `tests/php/` chạy với hàm WordPress **giả**, nên không thấy được những lỗi chỉ xuất hiện khi WordPress thật xử lý
(thứ tự quy tắc đường dẫn, thứ tự bộ lọc đăng nhập, `xmlrpc.php`, quyền của vai trò, nạp bản dịch...). Thư mục này dựng một WordPress thật
chạy bằng PHP tích hợp và SQLite — không cần MySQL, Docker hay Nginx — rồi chạy các luồng của website qua HTTP thật.

Ở lần chạy đầu tiên, bộ này tìm ra 10 lỗi mà bộ giả không thấy (xem lịch sử Git: "Sửa 7 lỗi chỉ lộ ra khi chạy trên WordPress 7.1 thật" và
"Đăng nhập an toàn: sửa khóa không có tác dụng..."). Mỗi lỗi đã có phép kiểm hoặc đột biến tương ứng ở `tests/php/smoke.php` để không quay lại.

## Không kiểm chứng điều gì

- **ACF PRO và WPML là bản giả** (`plugins/lab-acf-shim`): chỉ đủ `get_field`, `update_field`, nhóm trường cục bộ và trang cấu hình, lưu bằng meta/option.
  Việc nối với ACF PRO/WPML thật (giao diện nhập liệu, đồng bộ bản dịch, `wpml_cf_preferences`) **chưa được kiểm chứng** và phải thử trên staging
  khi có tệp cài đặt giấy phép. Không có ACF PRO hay WPML nào trong thư mục này.
- Thư không gửi thật (`plugins/lab-mail-capture` ghi vào `.lab/mail.log`); Cloudflare không được gọi thật (yêu cầu ghi vào `.lab/http.log`, trả kết quả giả).
- Không kiểm tra Nginx, header bảo mật, TLS, Cloudflare, Safari/Firefox/Cốc Cốc, trình đọc màn hình. Xem `tests/a11y.md` cho phần kiểm tay.

## Dựng môi trường

Cần PHP 8.3 với các phần mở rộng `mbstring gd fileinfo zip intl exif curl openssl pdo_sqlite sqlite3 gettext sodium`, Node 20+, và theme đã build
(`cd wp-content/themes/vgarco && npm ci && npm run build`).

Tải tay hai tệp (không nằm trong Git, `*.zip` bị bỏ qua):

- WordPress: <https://wordpress.org/latest.zip>
- Plugin *SQLite Database Integration*: <https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip>

```bash
PHP=/đường/dẫn/php PHP_EXT=/đường/dẫn/thư-mục-ext \
  node tests/wp-lab/bootstrap.mjs wordpress.zip sqlite-database-integration.zip
```

Bước này giải nén vào `tests/wp-lab/.lab/` (không vào Git), tạo `wp-config.php` với khóa ngẫu nhiên, cài WordPress, nối `wp-content/mu-plugins`,
`vgarco-core` và theme `vgarco` bằng liên kết (sửa mã là thấy ngay), kích hoạt, tạo các trang theo mẫu. Mật khẩu quản trị thử được sinh ngẫu nhiên và
ghi vào `.lab/credentials.txt`. Dùng `--reset` để dựng lại từ đầu.

## Chạy

```bash
PHP=/đường/dẫn/php node tests/wp-lab/run.mjs            # tất cả
PHP=/đường/dẫn/php node tests/wp-lab/run.mjs site       # một số kịch bản
```

Máy chủ chạy ở `http://localhost:8080` (cố định) trong lúc chạy và được tắt khi xong. Có thể mở trình duyệt vào địa chỉ đó nếu tự chạy
`php -c tests/wp-lab/.lab/php.ini -S localhost:8080 -t tests/wp-lab/.lab/wordpress tests/wp-lab/router.php`.

| Kịch bản   | Kiểm tra gì |
|------------|-------------|
| `env`      | WordPress 7.x, plugin/theme hoạt động, kiểu nội dung, phân loại, dữ liệu mồi, `ho_so_ut` riêng tư |
| `import`   | `wp vgarco import` với `content/projects.csv`: chạy thử, tạo, chạy lại không đổi, cập nhật, ô trống không xóa, tệp lỗi dừng, ảnh theo SHA-1, thư viện đúng thứ tự |
| `seed`     | Tạo tin, vị trí tuyển dụng (đang mở / quá hạn / đã đóng) cho các kịch bản sau; không kiểm tra |
| `apply`    | Nộp hồ sơ qua REST: mã bảo mật, token một lần, Origin/Referer, bẫy, kiểm tra trường và tệp thật, CV ngoài web root, thư HR và ứng viên (cả tiếng Đức), SMTP hỏng + gửi lại theo giờ, giới hạn 5 lần/giờ |
| `admin`    | Vai trò và quyền, danh sách hồ sơ, xem, tải CV (nonce, nhật ký, header), xuất `.xlsx` an toàn công thức, xóa kèm tệp, tự xóa sau 12 tháng, cảnh báo |
| `site`     | Trang công khai: SEO, JSON-LD, 404 theo mục, tìm kiếm REST, bộ lọc phân loại, sơ đồ trang, bề mặt tấn công (author, REST users, XML-RPC), bản dịch `.mo` |
| `security` | Đăng nhập qua `wp-login.php`: khóa IP và tài khoản, thông báo chung, quên mật khẩu không lộ tài khoản, báo quản trị mới, xóa cache Cloudflare |
| `crawl`    | Duyệt mọi liên kết nội bộ: 200, tài nguyên tải được, một H1, không trùng id, ảnh có alt, không tài nguyên ngoài miền |

Thứ tự trong `run.mjs` có ý nghĩa (`import` → `seed` → các kịch bản dùng dữ liệu đó). Mỗi kịch bản chạy lại được.

## Viết thêm

Mỗi kịch bản là một tệp `it_<tên>.php` nạp `lib.php` (WordPress nạp trong tiến trình, WP-CLI giả, `http()`, `check()`, `same()`, `finish()`),
rồi thêm tên vào `order` trong `run.mjs`. Gặp lỗi do bộ nhớ đệm đối tượng của tiến trình kiểm thử (đọc dữ liệu cũ sau một yêu cầu HTTP) thì gọi `wp_cache_flush()`.

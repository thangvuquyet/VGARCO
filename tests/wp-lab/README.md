# Kiểm thử trên WordPress thật (môi trường thử SQLite)

Bộ kiểm thử PHP trong `tests/php/` chạy với hàm WordPress **giả**, nên không thấy được những lỗi chỉ xuất hiện khi WordPress thật xử lý
(thứ tự quy tắc đường dẫn, thứ tự bộ lọc đăng nhập, `xmlrpc.php`, quyền của vai trò, nạp bản dịch...). Thư mục này dựng một WordPress thật
chạy bằng PHP tích hợp và SQLite — không cần MySQL, Docker hay Nginx — rồi chạy các luồng của website qua HTTP thật.

Ở lần chạy đầu tiên, bộ này tìm ra 8 lỗi mà bộ giả không thấy (xem lịch sử Git: "Sửa 7 lỗi chỉ lộ ra khi chạy trên WordPress 7.1 thật" và
"Đăng nhập an toàn: sửa khóa không có tác dụng..."). Các lỗi về đường dẫn phân loại, thứ tự bộ lọc đăng nhập, quên mật khẩu và XML-RPC có thêm phép kiểm và đột biến
ở `tests/php/smoke.php`, `tests/mutate.mjs` để không quay lại.

## Không kiểm chứng điều gì

- **Secure Custom Fields và Polylang là bản thật** (tải từ wordpress.org, cho vào `.lab/`, không vào Git). Chưa kiểm chứng: thao tác trong trình duyệt của biên tập viên
  (nút "+" của Polylang, kéo thả trường lặp) và MariaDB (phòng thử dùng SQLite).
- Thư không gửi thật (`plugins/lab-mail-capture` ghi vào `.lab/mail.log`); Cloudflare không được gọi thật (yêu cầu ghi vào `.lab/http.log`, trả kết quả giả).
- Không kiểm tra Nginx, header bảo mật, TLS, Cloudflare, Safari/Firefox/Cốc Cốc, trình đọc màn hình. Xem `tests/a11y.md` cho phần kiểm tay.

## Dựng môi trường

Cần PHP 8.3 với các phần mở rộng `mbstring gd fileinfo zip intl exif curl openssl pdo_sqlite sqlite3 gettext sodium`, Node 20+, và theme đã build
(`cd wp-content/themes/vgarco && npm ci && npm run build`).

Tải tay bốn tệp (không nằm trong Git, `*.zip` bị bỏ qua):

- WordPress: <https://wordpress.org/latest.zip>
$1- Plugin *Secure Custom Fields*: <https://downloads.wordpress.org/plugin/secure-custom-fields.6.9.7.zip>
- Plugin *Polylang*: <https://downloads.wordpress.org/plugin/polylang.3.9.1.zip>

```bash
PHP=/đường/dẫn/php PHP_EXT=/đường/dẫn/thư-mục-ext \
  $1 secure-custom-fields.zip polylang.zip
```

Bước này giải nén vào `tests/wp-lab/.lab/` (không vào Git), tạo `wp-config.php` với khóa ngẫu nhiên, cài WordPress, nối `wp-content/mu-plugins`,
`vgarco-core` và theme `vgarco` bằng liên kết (sửa mã là thấy ngay), kích hoạt, tạo các trang theo mẫu. Mật khẩu quản trị thử được sinh ngẫu nhiên và
ghi vào `.lab/credentials.txt`. Dùng `--reset` để dựng lại từ đầu (xóa cả dữ liệu minh họa `demo`).
Sau khi cài, `languages.php` chạy `vgarco_setup_languages()` (tiếng Việt mặc định, tiếng Đức ở `/de/`) và `pages.php` tạo 9 trang tiếng Việt cùng bản dịch tiếng Đức của chúng. Lab dựng từ bản cũ có bản giả ACF/WPML phải dựng lại bằng `--reset`.

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
| `media`    | Ảnh tải lên: GPS/EXIF/XMP bị gỡ khỏi tệp gốc và mọi bản thu nhỏ (cả ảnh >2560 px giữ tệp gốc), ảnh dọc xoay đúng chiều (hướng 6/8/3), PNG, từ chối GIF/WebP/tệp hỏng và không để lại tệp, tải qua `async-upload.php` bằng HTTP thật |
| `export`   | Hợp đồng công cụ xuất ảnh ↔ importer: xuất ảnh thật + bộ khung `images.csv`; importer chặn bộ khung chú thích trống, nạp đúng vai trò/thứ tự/văn bản thay thế sau khi điền, không còn GPS, chạy lại không nạp trùng |
| `seed`     | Tạo tin, vị trí tuyển dụng (đang mở / quá hạn / đã đóng) cho các kịch bản sau; không kiểm tra |
| `apply`    | Nộp hồ sơ qua REST: mã bảo mật, token một lần, Origin/Referer, bẫy, kiểm tra trường và tệp thật, CV ngoài web root, thư HR và ứng viên (cả tiếng Đức), SMTP hỏng + gửi lại theo giờ, giới hạn 5 lần/giờ |
| `admin`    | Vai trò và quyền, danh sách hồ sơ, xem, tải CV (nonce, nhật ký, header), xuất `.xlsx` an toàn công thức, xóa kèm tệp, tự xóa sau 12 tháng, cảnh báo |
| `site`     | Trang công khai: SEO, JSON-LD, 404 theo mục, tìm kiếm REST, bộ lọc phân loại, sơ đồ trang, bề mặt tấn công (author, REST users, XML-RPC), bản dịch `.mo` |
| `lang`     | Polylang thật: hai ngôn ngữ và cài đặt, 8 cặp trang VI ↔ DE (200, bộ chuyển trỏ thẳng, `hreflang`), `/de/` là trang chủ, không đoán ngôn ngữ trình duyệt, nhập dự án có bản Đức (nối bản dịch, chạy lại không đổi, danh mục mỗi ngôn ngữ), sao chép trường khi tạo bản dịch, cấu hình riêng cho bản Đức, màn hình quản trị |
| `security` | Đăng nhập qua `wp-login.php`: khóa IP và tài khoản, thông báo chung, quên mật khẩu không lộ tài khoản, báo quản trị mới, xóa cache Cloudflare |
| `crawl`    | Duyệt mọi liên kết nội bộ: 200, tài nguyên tải được, một H1, không trùng id, ảnh có alt, không tài nguyên ngoài miền |

Thứ tự trong `run.mjs` có ý nghĩa (`import` → `seed` → các kịch bản dùng dữ liệu đó). Mỗi kịch bản chạy lại được.

## Dữ liệu minh họa: `demo` (không nằm trong lượt chạy mặc định)

Website thử sau các kịch bản trên chỉ có dữ liệu tối thiểu (không ảnh, không lĩnh vực, không đối tác), nên **trông khác hẳn trang tĩnh**. Muốn xem website thử đầy đủ như trang tĩnh:

```bash
PHP=/đường/dẫn/php node tests/wp-lab/run.mjs demo     # chạy SAU CÙNG; chạy lại được
```

`it_demo.php` dọn dự án/ảnh/tin/vị trí/lĩnh vực rồi nhập 19 dự án **đã công bố** kèm ảnh thật, dựng 6 lĩnh vực, 5 đối tác, khối "câu chuyện công trình", tin tức có ảnh, 3 vị trí đang mở, ảnh mặc định của trang chủ; kiểm lại 20 điều trên trang thật.
Mọi kịch bản khác dọn dữ liệu này lúc bắt đầu, nên `demo` không đưa vào thứ tự mặc định; chạy lại `demo` sau khi chạy các kịch bản khác để website thử trở về dạng minh họa.

- **Ảnh** lấy từ thư mục ngoài kho mã (đặt biến `VG_DEMO_PHOTOS`, mặc định là thư mục `vgarco-demo-photos` nằm cạnh thư mục kho mã); thiếu thư mục thì kịch bản in "BỎ QUA" (không phải lỗi). Thư mục này do `demo/prepare-photos.php` dựng từ ảnh gốc của dự án
  (cắt theo vùng nổi bật, **phóng to và làm nét** ảnh gốc thấp độ phân giải bằng ffmpeg, xuất qua `scripts/content/export-images.php`; có `contact-sheet.jpg` và `report.json` để xem lại).
  Cách chạy: `php tests/wp-lab/demo/prepare-photos.php <thư-mục-ảnh-gốc> <thư-mục-ra> [--only=<mã-dự-án>]`. **Đây không phải quy trình cắt ảnh của website thật**: phương án mục 9.3 yêu cầu cắt **tay** (cắt tự động có thể xén mất mái hoặc nền công trình); công cụ chỉ dựng bản đề xuất để website thử có ảnh.
  Ảnh được chọn theo xếp hạng (phối cảnh trước, rồi ảnh chụp; ảnh lớn hơn trước), bỏ ảnh toàn cảnh PANO và thư mục "giấy khen", cắt đúng tỉ lệ hero 21:9, trình chiếu 16:9, thẻ 4:3. Phóng to **không** tạo thêm chi tiết thật: ảnh mềm hơn bản gốc cùng cỡ, khi có ảnh gốc độ phân giải cao thì thay.
- **Dự án chưa có ảnh riêng để trống có chủ ý** (3 trong 19: Đài kiểm soát không lưu Đồng Hới, Học viện Kỹ thuật & Công nghệ An ninh, Biệt thự tại Niedernhausen): thẻ vẫn có chữ và liên kết nhưng không có ảnh và không mang nhãn loại ảnh.

## So website thử với trang tĩnh: [`compare/`](compare/README.md)

`structure.php` so cấu trúc 14 cặp trang (cần `node tests/serve.mjs 4174`); `collector.mjs` + `fn.js` + `cmpstyles.mjs` so kiểu hiển thị của từng phần tử. Kết quả ngày 08/10/2026 và cách đọc: xem README của thư mục đó.

## Kiểm tra trên trình duyệt (không tự động)

Bố cục ở nhiều độ rộng (kể cả giao diện **tiếng Đức** ở `/de/…`), thao tác JS, giảm chuyển động (mô phỏng) và duyệt bằng phím Tab cần trình duyệt: xem [`browser/README.md`](browser/README.md).
Lần kiểm đầu tiên tìm ra 4 chỗ vỡ bố cục tiếng Đức và 2 lỗi thao tác (Esc ở hộp thoại tìm kiếm, thông báo khi xin mã bảo mật quá nhiều lần).

## Viết thêm

Mỗi kịch bản là một tệp `it_<tên>.php` nạp `lib.php` (WordPress nạp trong tiến trình, WP-CLI giả, `http()`, `check()`, `same()`, `finish()`),
rồi thêm tên vào `order` trong `run.mjs`. Gặp lỗi do bộ nhớ đệm đối tượng của tiến trình kiểm thử (đọc dữ liệu cũ sau một yêu cầu HTTP) thì gọi `wp_cache_flush()`.

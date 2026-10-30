# Vận hành

Phạm vi theo đặc tả 9.5–9.6: nhà thầu vận hành hệ quản trị và cập nhật nội dung; chủ đầu tư không trực tiếp thao tác. Tài liệu này mô tả *cách làm*, không mô tả điều khoản thương mại.

## 1. Việc hằng tháng (mục tiêu ≤ 2 giờ)

| Việc | Cách làm | Ghi |
|---|---|---|
| Cập nhật lõi, plugin, theme **trên staging trước** | `wp core update`, `wp plugin update --all` dưới tài khoản `deploy` ở staging → chạy kiểm thử khói (mục 3 của `TRIEN-KHAI.md`) → sao lưu production → áp dụng lên production | Cập nhật chỉ qua WP-CLI vì `DISALLOW_FILE_MODS` (thiết kế) |
| Xử lý cảnh báo lỗ hổng (Patchstack) | Xem ngay khi có email, không chờ cuối tháng. Vá ≤ 72 giờ; mức nghiêm trọng ≤ 24 giờ | Thiết kế |
| Kiểm tra sao lưu | `restic snapshots`, `restic check`; mỗi quý khôi phục thử vào staging (xem `SAO-LUU-PHUC-HOI.md`) | Thiết kế |
| **Đăng nhập HetrixTools** | Tài khoản không đăng nhập quá 90 ngày bị vô hiệu hóa | Thiết kế |
| Đĩa, nhật ký lỗi PHP, nhật ký thư, nhật ký tự xóa hồ sơ | Xem dung lượng đĩa; `tail` nhật ký PHP-FPM; mở nhật ký FluentSMTP | Thiết kế |
| Cảnh báo hệ thống trong trang quản trị | Mở trang quản trị: cảnh báo gần nhất hiện ở đầu trang cho Quản trị viên; xử lý rồi bấm "Đã xem, ẩn cảnh báo" | **Đã kiểm chứng** (`it_admin`) |
| 6 tuần đầu sau go-live | Đọc báo cáo DMARC, nâng chính sách theo lộ trình | Thiết kế |

## 2. Cập nhật nội dung bằng bảng tính (không nhập tay)

Dự án: sửa `content/projects.csv` rồi nhập bằng WP-CLI. Chạy lại nhiều lần được — cập nhật theo cột `ma`, không tạo bản trùng.

```bash
wp vgarco import projects content/projects.csv --images=<thư-mục-ảnh> --dry-run   # xem trước, không ghi gì
wp vgarco import projects content/projects.csv --images=<thư-mục-ảnh>             # nhập (dự án mới ở trạng thái nháp)
wp vgarco import projects content/projects.csv --lang=de                           # chỉ các cột tiếng Đức
wp vgarco report translations --lang=de                                            # nội dung chưa có bản tiếng Đức
```

Quy tắc chính (chi tiết ở `content/README.md`):
- Tệp còn **bất kỳ lỗi nào** thì **không ghi gì cả**; mọi lỗi được báo một lượt kèm số dòng.
- Ô trống trong bảng **không xóa** dữ liệu đã nhập tay trong trang quản trị; ô có giá trị thì bảng thắng.
- Dự án mới vào ở trạng thái **nháp**: kiểm tra rồi mới xuất bản. Quyền **công bố chủ đầu tư/đối tác** mặc định tắt (cột `cong_bo_chu_dau_tu`).
- Ảnh: xuất bằng `php scripts/content/export-images.php` (xoay, gỡ GPS, giới hạn kích thước và dung lượng, ra bộ khung `images.csv`), điền chú thích thật rồi nhập; ảnh nạp **một lần** theo nội dung (SHA-1), chạy lại không nạp trùng.
- Sau khi nạp ảnh: `wp vgarco report images` liệt kê ảnh bị hạ chất lượng AVIF để vừa trần dung lượng (chất lượng 50 hoặc 45) và ảnh **vẫn vượt trần ở 45** — ảnh sau nên được thay bằng ảnh ít chi tiết hơn hoặc cắt lại. Ảnh không có trong báo cáo vẫn giữ chất lượng chung 55. Mã hóa thử làm việc nạp ảnh lớn chậm hơn (1–3 lần mã hóa thêm cho mỗi ảnh); nạp hàng loạt nên chạy bằng WP-CLI (không bị giới hạn thời gian của web).
- Chỉ mục tìm kiếm tự làm mới khi dự án đổi. Khi bạn **xuất bản** dự án (hoặc sửa dự án đã xuất bản), cache Cloudflare được lên lịch xóa sau 30 giây (tùy chu kỳ cron hệ thống); dự án nháp không làm đổi trang công khai nên không kích hoạt xóa cache.

Bản tiếng Đức (Polylang):
- Các cột `*_de` của bảng tạo hoặc cập nhật bài tiếng Đức và nối với bài tiếng Việt cùng `ma`; bài tiếng Đức nằm ở `/de/du-an/<slug-de>/`. Dự án chưa có bản Đức thì bộ chuyển ngôn ngữ đưa người xem về danh mục.
- Tin, vị trí, lĩnh vực và trang: bấm dấu "+" ở cột ngôn ngữ trong danh sách bài để tạo bản dịch (Polylang chép các ô sao chép, để trống ô cần dịch).
- Chữ của trang **Cấu hình VGARCO** (chỉ số, địa chỉ, mẫu thư, câu chuyện, đối tác) nhập ở **Cấu hình VGARCO → Bản DE**; để trống thì dùng giá trị mặc định tiếng Đức trong mã.
- Loại công trình và chuyên mục phải được dịch tên trước (Ngôn ngữ → trang phân loại) thì `wp vgarco import` mới gắn được cho dự án tiếng Đức; chưa dịch thì lệnh báo và bỏ qua loại đó.

Tin tức, vị trí tuyển dụng, lĩnh vực: nhập trong trang quản trị (khối soạn thảo bị giới hạn để không phá bố cục).

## 3. Quản lý tài khoản

| Việc | Lệnh hoặc thao tác |
|---|---|
| Tạo tài khoản Nhân sự tuyển dụng | `wp user create <tên-đăng-nhập> <email> --role=vg_tuyen_dung --user_pass=<mật-khẩu-mạnh>` rồi bật xác thực hai lớp (WP 2FA). Vai trò này chỉ xem/tải CV/xuất/xóa hồ sơ |
| Tạo quản trị viên | Chỉ khi thật cần. **Hệ thống gửi email báo tới `admin_email`** mỗi khi có quản trị viên mới hoặc ai đó được nâng quyền — nếu không phải việc đã dự định thì đổi mật khẩu các tài khoản quản trị ngay (xem `SU-CO.md`, mục "Nghi bị xâm nhập") |
| Thu hồi quyền khi nhân sự nghỉ | Xóa hoặc hạ vai trò tài khoản, đổi mật khẩu dùng chung (nếu có), rút khỏi Cloudflare/Git/kho sao lưu theo `SO-TAI-KHOAN.md` |
| Mở khóa tài khoản/IP bị khóa đăng nhập | Xem `SU-CO.md`, mục "Bị khóa đăng nhập" |

Không dùng tên đăng nhập `admin`. Đường dẫn đăng nhập đổi bằng WPS Hide Login và bắt buộc 2FA (cấu hình khi dựng máy chủ; thiết kế).

## 4. Hồ sơ ứng tuyển

- **Xem:** trang quản trị → *Hồ sơ ứng tuyển* (lọc theo vị trí và khoảng ngày, tìm kiếm). Chỉ vai trò Nhân sự tuyển dụng và Quản trị viên thấy.
- **Tải CV:** nút trong trang chi tiết hồ sơ — có kiểm tra quyền và mã chống giả mạo yêu cầu, ghi nhật ký người tải và thời điểm. Tệp không có URL công khai.
- **Xuất danh sách:** mục *Xuất danh sách* → `.xlsx` (nội dung được bảo vệ khỏi công thức Excel độc hại).
- **Tự xóa sau 12 tháng:** tác vụ hằng ngày xóa hồ sơ quá 12 tháng **kèm tệp CV** (tối đa 200 hồ sơ mỗi lượt). Xóa tay (kể cả từ thùng rác) cũng xóa tệp CV.
- Bản sao lưu giữ theo tháng nên CV đã xóa còn trong bản sao lưu tối đa ~3 tháng: ghi rõ trong *Chính sách bảo vệ dữ liệu*.

## 5. Tác vụ định kỳ

Xem bảng ở `KIEN-TRUC.md` mục 4. Kiểm tra lịch và chạy tay:

```bash
wp cron event list --fields=hook,next_run_relative | grep vgarco
wp cron event run vgarco_apply_mail_retry            # gửi lại thư của hồ sơ lỗi thư
wp eval 'var_dump( vgarco_cf_purge() );'              # xóa cache Cloudflare ngay (true = thành công)
wp eval 'echo vgarco_apply_run_retention();'          # chạy tự xóa hồ sơ quá hạn, in số hồ sơ đã xóa
```

## 6. Chỉ số cần theo dõi

| Chỉ số | Ngưỡng | Nguồn |
|---|---|---|
| Website truy cập được | Cảnh báo sau 2 lần kiểm tra lỗi liên tiếp (1 phút/lần) | HetrixTools (thiết kế) |
| Tỉ lệ hoạt động | ≥ 99,5% mỗi tháng (đặc tả 5.5 #5) | Báo cáo tháng HetrixTools |
| Hồ sơ lỗi thư | 0 tồn đọng | Trang quản trị (cảnh báo), cột trạng thái thư của hồ sơ |
| Đĩa | < 80% | Agent giám sát |
| Hạn chứng chỉ | ≥ 14 ngày ở biên, ≥ 21 ngày ở máy chủ gốc | HetrixTools, `check-cert.sh` (thiết kế) |

## 7. Khi kết thúc hợp tác (đặc tả 9.7)

Trong **05 ngày làm việc** nhà thầu bàn giao tài khoản quản trị hệ thống, mã nguồn mới nhất, bản sao lưu đầy đủ và hồ sơ kỹ thuật, đồng thời rút mọi quyền truy cập theo danh sách ở [`SO-TAI-KHOAN.md`](SO-TAI-KHOAN.md).

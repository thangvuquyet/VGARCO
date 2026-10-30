# Hồ sơ kỹ thuật bàn giao — website VGARCO

Tài liệu kỹ thuật cho người vận hành website (đặc tả mục 5.7 #3 và #5, kế hoạch mục 13.1). Theo đặc tả 8.1 và 9.2, **không** có tài liệu hướng dẫn quản trị cho người biên tập và đào tạo sử dụng;
các tài liệu dưới đây dành cho người kỹ thuật.

| Tài liệu | Nội dung | Mức kiểm chứng |
|---|---|---|
| [`KIEN-TRUC.md`](KIEN-TRUC.md) | Các lớp mã, mô hình dữ liệu, đường dẫn, REST, tác vụ định kỳ, bộ nhớ đệm, biện pháp bảo mật trong mã | **Đã kiểm chứng** trên WordPress 7.1 thật (`tests/wp-lab`) |
| [`CAU-HINH.md`](CAU-HINH.md) | Hằng số trong `wp-config.php`, nơi cất khóa bí mật, trang Cấu hình, khác biệt giữa môi trường | Hằng số và trang Cấu hình đã kiểm chứng; mẫu `wp-config.php` production là **thiết kế** |
| [`VAN-HANH.md`](VAN-HANH.md) | Việc hằng tháng, cập nhật nội dung bằng bảng tính, quản lý tài khoản, tác vụ định kỳ | Lệnh nhập liệu và tài khoản đã kiểm chứng; việc cập nhật plugin/lõi là **thiết kế** |
| [`TRIEN-KHAI.md`](TRIEN-KHAI.md) | Quy trình đưa mã lên máy chủ, kiểm tra sau triển khai, hoàn tác | **Thiết kế, mới chạy thử một phần** (`provision.sh`: 9/9 bước đã chạy thật trên VM thử 29/10/2026; `deploy.sh`: đã chạy thật trên staging thử cùng ngày, gồm tự hoàn tác; chưa chạy production) |
| [`SAO-LUU-PHUC-HOI.md`](SAO-LUU-PHUC-HOI.md) | Cái gì phải sao lưu, lịch, khôi phục từng bước, mẫu biên bản diễn tập | **Thiết kế, chưa chạy thật**; biên bản diễn tập còn trống |
| [`DNS-EMAIL.md`](DNS-EMAIL.md) | Bản ghi DNS của website và thư (SPF, DKIM, DMARC), cài đặt Cloudflare, chứng chỉ Origin CA, bảo mật tên miền, thứ tự làm | **Thiết kế**; giá trị bản ghi thư chờ Vietnix và Brevo |
$1| [`SO-TAI-KHOAN.md`](SO-TAI-KHOAN.md) | Sổ tài khoản dịch vụ (mẫu để điền) và danh sách rút quyền khi kết thúc hợp tác | **Mẫu trống** — điền khi mở tài khoản |

Nguyên tắc chung: **không ghi mật khẩu, khóa API hay token vào các tài liệu này hay vào Git** — chỉ ghi *nơi cất* (kho mật khẩu đứng tên chủ đầu tư, hoặc `/etc/vgarco/secrets.php` trên máy chủ).

Phần tiếng Việt của mọi chuỗi giao diện là chuỗi gốc; bản tiếng Đức là **bản nháp** chờ đối tác hiệu đính (xem `README.md` ở thư mục gốc, mục Dịch).

Nơi tra cứu khác: `README.md` (cấu trúc kho, kiểm thử), `content/README.md` (nhập liệu bằng bảng tính, xuất ảnh), `tests/wp-lab/README.md` (kiểm thử trên WordPress thật), `plugins.lock.json` (thành phần bên thứ ba và lý do dùng).

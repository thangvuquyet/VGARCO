# Kiểm tra trên trình duyệt (tay hoặc bằng cửa sổ trình duyệt nhúng)

Những gì `tests/php` và `tests/wp-lab` **không** kiểm được vì cần một trình duyệt thật: bố cục ở nhiều độ rộng (kể cả tiếng Đức) và thao tác JS (nút dừng,
trình chiếu ảnh, xem phóng to, sao chép liên kết, lọc tuyển dụng, hộp thoại tìm kiếm, mã bảo mật). Cách làm: bật máy chủ thử (`node tests/wp-lab/run.mjs` bật rồi tắt
ngay; để giữ lại, chạy tay `php -c tests/wp-lab/.lab/php.ini -S localhost:8080 -t tests/wp-lab/.lab/wordpress tests/wp-lab/router.php`), mở `http://localhost:8080`,
dán nội dung tệp vào Console (F12) rồi gọi hàm.

## Bố cục: `layout-check.js`

```js
const urls = ['/', '/gioi-thieu/', '/du-an/', '/tuyen-dung/vt-kts/', '/lien-he/'];
const de = await vgLayout(390, urls, 'de');   // giao diện tiếng Đức (plugin lab-lang, tham số ?lab_lang=de)
const vi = await vgLayout(390, urls, '');
vgLayoutDiff(de, vi);                         // chỉ lỗi xuất hiện riêng ở tiếng Đức
```

Chạy ở 360, 390, 768, 1024, 1440, 1920, 2560 px (đặc tả 5.2 #2). Báo "TRÀN NGANG" (cả trang cuộn ngang được) và chữ rộng hơn hộp chứa (chữ tràn hay đè lên chữ khác).
Mỗi lần dán một độ rộng rồi chạy trong vòng ~45 giây (cửa sổ trình duyệt nhúng giới hạn thời gian một lệnh).

Hai kết quả "lỗi" được chấp nhận: mục cuối đường dẫn vị trí của trang chi tiết dự án (cắt bằng dấu "…" theo thiết kế, cả hai ngôn ngữ) và mũi tên menu xoay 45° (hình học, vượt 3 px ở 1440+).

## Thao tác JS

Đã kiểm ngày 05/10/2026 bằng cách gọi trực tiếp trong trang (chọn phần tử theo thuộc tính `data-*` trong `src/js/modules/`):

| Trang | Thao tác | Kỳ vọng |
|---|---|---|
| `/` | bấm nút "Tạm dừng chuyển động" hai lần | nhãn đổi "Tiếp tục chuyển động" rồi trở lại; `aria-pressed` đổi; `body.is-paused` bật/tắt |
| chi tiết dự án | `[data-gallery-next]`, chấm, phím Home/End/←/→ trên `[data-gallery-stage]`, vuốt (`pointerType: 'touch'` kéo ≥ 50 px) | số ảnh "Ảnh n / m: chú thích" đổi; vuốt ngắn (< 50 px) không đổi |
| chi tiết dự án | bấm `[data-gallery-open]`, rồi phím **Esc thật** | hộp xem ảnh mở có chú thích; Esc đóng; tiêu điểm trở về nút mở |
| chi tiết tin | bấm `[data-copy-link]` (thay `navigator.clipboard`) | chép đúng địa chỉ chuẩn của bài (không kèm `?v=`); thông báo "Đã sao chép liên kết" rồi tự xóa |
| `/tuyen-dung-trang/` | gõ từ khóa vào ô tìm; từ khóa không khớp; "Xóa bộ lọc" | số vị trí đổi tại chỗ không tải lại trang; hiện "Chưa tìm thấy vị trí phù hợp" + "Xóa bộ lọc" + "Xem tất cả vị trí" |
| mọi trang | mở hộp thoại tìm kiếm (`[data-vg-search-open]`), gõ **chữ rồi bấm Esc thật một lần** | tiêu điểm vào ô nhập; < 3 ký tự giữ hướng dẫn; không khớp báo "Chưa có dự án phù hợp"; Esc, nút đóng, bấm nền đều đóng và trả tiêu điểm về nút mở |
| `/ung-tuyen/?id=…` | bấm `[data-captcha-refresh]`; gửi trống | token và ảnh mã mới, ô mã được xóa; mọi trường bắt buộc đánh dấu lỗi, tiêu điểm vào trường đầu, ô đồng ý không tích sẵn |
| `/lien-he/` | gửi trống; email sai; thiếu đồng ý | lỗi đúng trường; ô đồng ý không tích sẵn |

Phím **Esc phải là phím thật** (không dùng `dispatchEvent`): chính vì thế mới tìm ra lỗi hộp thoại tìm kiếm — ô nhập kiểu `search` của Chrome/Edge dùng Esc để *xóa chữ* trước,
nên khi đang có chữ thì Esc đầu tiên không đóng hộp thoại (UC-09 2a). Đã sửa trong `search.js`.

Chưa kiểm được: thiết lập "giảm chuyển động" thật của hệ điều hành, màn hình cảm ứng thật, Safari/Firefox/Cốc Cốc, trình đọc màn hình.

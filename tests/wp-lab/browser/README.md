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

## Cấu trúc bố cục so với bản thiết kế: `structure-check.js`

`layout-check.js` chỉ bắt chữ tràn; nó **không** bắt được cột chữ bị bóp hẹp hay trang đổi số cột sớm/muộn hơn bản thiết kế. `structure-check.js` làm việc đó: `vgSweep` in số cột thật của mọi khối lưới/flex
ở nhiều độ rộng (chạy ở cả theme và bản thiết kế HTML `node server.cjs` → http://localhost:4173, rồi so hai bên), `vgCramped` liệt kê đoạn chữ dưới 24 ký tự/dòng. Cách dùng ở đầu tệp.

Bản thiết kế **không** đổi bố cục cùng một độ rộng ở mọi trang (Tin tức 800 px, chi tiết vị trí 900 px, Tuyển dụng 600 px, Dự án 1000 px); theme dùng các biến `$bp-*` trong `src/scss/_mixins.scss` cho từng trang.
Kiểm ngày 05/10/2026 ở 600–1181 px (cả VI và DE):

| Trang | Phát hiện | Xử lý |
|---|---|---|
| Tin tức, danh sách | Từ 701 px theme chia 3 cột (ảnh 150, chữ, ngày 210) trong khi bản thiết kế chỉ chia ở 801 px: ở 701 px cột chữ chỉ còn **198 px (15 ký tự/dòng)**, ở 768 px 265 px | Sửa: 601–800 px dùng hàng gọn như bản thiết kế (ảnh 96 × 74 bên trái, ngày dưới đoạn chữ), 3 cột từ 801 px (`$bp-news`) |
| Tin tức, chi tiết | Đầu bài chia 2 cột từ 701 px → tiêu đề còn 419 px (3 dòng, 15 ký tự/dòng) | Sửa: 1 cột tới 800 px (`$bp-news`) |
| Chi tiết vị trí | Nút "Ứng tuyển" nằm cạnh tiêu đề từ 701 px; tiêu đề tiếng Đức dài (60 ký tự) thành 4 dòng ở 701–800 px | Sửa: xếp dưới tiêu đề tới 900 px (`$bp-job`), như bản thiết kế |
| Tuyển dụng, danh sách | Ở 601–700 px theme xếp 1 cột, bản thiết kế đã chia (số thứ tự + nội dung; ô tìm kiếm 2 cột) | Giữ nguyên: không có chữ bị bóp, chỉ là dạng điện thoại thêm 100 px |
| Tin tức, chi tiết (khung thân bài) | Bản thiết kế chia 2 cột (cột chia sẻ 120 px) từ 801 px nhưng **tự nó cuộn ngang 29 px** ở 801–1050 px | Giữ nguyên của theme (1 cột tới 1000 px, không cuộn ngang) |
| Tin tức, danh sách ở 801–900 px | Tiêu đề tiếng Đức rất dài (90+ ký tự) ra 5 dòng ở cột chữ 298 px | Giữ nguyên (đúng bản thiết kế; tiêu đề thực tế ngắn hơn, tách từ tự động đang bật) |

## Trợ năng trên trang thật: `axe-check.js`

axe-core chạy trên trang thật của website thử ở nhiều độ rộng, cả tiếng Đức và các trạng thái động (hộp thoại tìm kiếm, menu di động, biểu mẫu báo lỗi, hộp xem ảnh).
Cần thêm `node tests/serve.mjs 4174` (phục vụ `/axe/axe.min.js`). Cách dùng và **phép đối chứng bắt buộc** ở đầu tệp; kết quả ngày 05/10/2026 ở `tests/a11y.md`.

## Thao tác JS

Đã kiểm ngày 05/10/2026 bằng cách gọi trực tiếp trong trang (chọn phần tử theo thuộc tính `data-*` trong `src/js/modules/`):

| Trang | Thao tác | Kỳ vọng |
|---|---|---|
| `/` | bấm nút "Tạm dừng chuyển động" hai lần | nhãn đổi "Tiếp tục chuyển động" rồi trở lại; `aria-pressed` đổi; `body.is-paused` bật/tắt |
| chi tiết dự án | `[data-gallery-next]`, chấm, phím Home/End/←/→ trên `[data-gallery-stage]`, vuốt (`pointerType: 'touch'` kéo ≥ 50 px) | số ảnh "Ảnh n / m: chú thích" đổi; vuốt ngắn (< 50 px) không đổi |
| chi tiết dự án | bấm `[data-gallery-open]`, rồi phím **Esc thật** | hộp xem ảnh mở có chú thích; Esc đóng; tiêu điểm trở về nút mở |
| chi tiết tin | bấm `[data-copy-link]` (thay `navigator.clipboard`) | chép đúng địa chỉ chuẩn của bài (không kèm `?v=`); thông báo "Đã sao chép liên kết" rồi tự xóa |
| `/tuyen-dung/` | gõ từ khóa vào ô tìm; từ khóa không khớp; "Xóa bộ lọc" | số vị trí đổi tại chỗ không tải lại trang; hiện "Chưa tìm thấy vị trí phù hợp" + "Xóa bộ lọc" + "Xem tất cả vị trí" |
| mọi trang | mở hộp thoại tìm kiếm (`[data-vg-search-open]`), gõ **chữ rồi bấm Esc thật một lần** | tiêu điểm vào ô nhập; < 3 ký tự giữ hướng dẫn; không khớp báo "Chưa có dự án phù hợp"; Esc, nút đóng, bấm nền đều đóng và trả tiêu điểm về nút mở |
| `/ung-tuyen/?id=…` | bấm `[data-captcha-refresh]`; gửi trống | token và ảnh mã mới, ô mã được xóa; mọi trường bắt buộc đánh dấu lỗi, tiêu điểm vào trường đầu, ô đồng ý không tích sẵn |
| `/lien-he/` | gửi trống; email sai; thiếu đồng ý | lỗi đúng trường; ô đồng ý không tích sẵn |

Phím **Esc phải là phím thật** (không dùng `dispatchEvent`): chính vì thế mới tìm ra lỗi hộp thoại tìm kiếm — ô nhập kiểu `search` của Chrome/Edge dùng Esc để *xóa chữ* trước,
nên khi đang có chữ thì Esc đầu tiên không đóng hộp thoại (UC-09 2a). Đã sửa trong `search.js`.

## Chuyển động (theo bản đầu của theme), kiểm ngày 06/10/2026

Trình duyệt nhúng **không vẽ khi cửa sổ ẩn** nên không có sự kiện `scroll` thật, chuyển cảnh CSS không chạy tiếp và `document.hidden` là `true` (trình chiếu hero tự dừng). Cách làm: đọc trạng thái cuối
sau `document.getAnimations().forEach( ( a ) => a.finish() )`; đặt `Object.defineProperty( document, 'hidden', { get: () => false } )` rồi bắn `visibilitychange`; bắn `scroll`/`PointerEvent` bằng tay;
riêng phím Tab dùng phím thật (`:focus-visible` chỉ đúng với tiêu điểm do bàn phím).

| Trang | Thao tác | Kỳ vọng (đã thấy) |
|---|---|---|
| mọi trang | cuộn qua 12 px / về 0 | lớp `is-stuck` bật ở 13 px, tắt khi về đầu; bóng `0 2px 14px` ; chiều cao thanh không đổi |
| mọi trang, ≥ 1181 px | mở một mục menu có con | bảng con có hoạt ảnh `nav-panel-in` 0,16 s (mờ dần, nhích lên 6 px) rồi `opacity: 1`, `transform: none`; đóng lại thì `hidden` |
| mọi trang, ≤ 1180 px | bấm nút menu rồi bấm lần nữa | mở: menu `transform: none`, `visibility: visible`, hai vạch thành X (±45°, ±3,5 px); đóng: còn thấy menu trượt đi, sau 0,25 s `visibility: hidden`, `translateX(100%)`; **không có liên kết nào còn trong thứ tự Tab**; trang không cuộn ngang ở 390, 768, 1180 px |
| mọi trang, ≥ 1181 px | đọc kiểu của `.nav` | `position: static`, `visible`, không dịch chuyển |
| `/` có từ 2 dự án nổi bật | đọc chấm chọn ảnh | mỗi ảnh một chấm (nút 40 × 44 px, góc trên trái khung ảnh, không đè chú thích hay chữ VG), chấm đầu `aria-current` |
| `/` | bấm chấm 3 rồi chấm 1 | ảnh, chú thích và chấm đổi cùng nhau |
| `/` | tự chạy → rê **chuột** vào khung → rời đi | 5,6 s đổi ảnh; rê chuột thì đứng (6,3 s không đổi); rời đi chạy lại |
| `/` | `pointerenter` kiểu **cảm ứng** | KHÔNG dừng (chạm vào ảnh không được làm trình chiếu đứng mãi) |
| `/` | nút dừng chuyển động; bấm chấm khi đang dừng; bấm lại nút | dừng thật; bấm chấm đổi ảnh nhưng không tự chạy lại; tiếp tục thì chạy lại |
| `/` | Tab thật từ liên kết "Từ bản vẽ" tới chấm đầu | tiêu điểm vào chấm (`:focus-visible`), trình chiếu đứng; rời khung thì chạy lại |
| trang có thẻ dự án | đọc kiểu `.project-photo img` | `transform 0.5s cubic-bezier(.2,.7,.3,1)`; quy tắc rê chuột `scale(1.055)`, không phủ màu |
| mọi trang | đọc `scroll-behavior` của `html` | `smooth` (và `auto` khi bật giảm chuyển động) |

Chưa kiểm được: thiết lập "giảm chuyển động" thật của hệ điều hành, màn hình cảm ứng thật, Safari/Firefox/Cốc Cốc, trình đọc màn hình. Chưa nhìn tận mắt chuyển động đang chạy (cửa sổ ẩn); chỉ đo trạng thái đầu/cuối và quy tắc đã biên dịch.

# Nhập liệu bằng bảng tính

Dữ liệu dự án nhập bằng CSV qua WP-CLI (phương án mục 8.7). Chạy lại nhiều lần được: cập nhật theo cột `ma`, không tạo bản trùng.

```bash
wp vgarco import projects content/projects.csv --images=<thư mục ảnh> --dry-run   # xem trước, không ghi gì
wp vgarco import projects content/projects.csv --images=<thư mục ảnh>             # nhập (bài mới ở trạng thái nháp)
wp vgarco import projects content/projects.csv --lang=de                           # chỉ các cột tiếng Đức (bản tiếng Việt phải có sẵn)
wp vgarco report translations --lang=de                                            # nội dung chưa có bản tiếng Đức
```

- Lưu CSV dạng **UTF-8** (Excel: *Lưu thành → CSV UTF-8*). Dấu phân cách `;`, `,` hoặc tab được tự nhận. Ô có dấu `;` hoặc xuống dòng phải đặt trong dấu nháy kép (Excel tự làm).
- Tệp còn **bất kỳ lỗi nào** thì **không ghi gì cả**; mọi lỗi được báo một lượt, kèm số dòng.
- Ô trống không xóa dữ liệu đã nhập tay trong trang quản trị.
- `images.csv` (cạnh `projects.csv`, hoặc `--images-csv=`) cho biết ảnh nào của dự án nào. Ảnh nạp một lần (so khớp nội dung), nặng tối đa 15 MB
  (PNG render 20–30 MB phải xuất JPEG trước).

## projects.csv

| Cột | Bắt buộc | Ý nghĩa |
|---|---|---|
| `ma` | có | Khóa, chữ thường không dấu và gạch ngang (ví dụ `bao-tang-qdnd-lao`). Không đổi sau khi đã nhập |
| `ten_vi` | có | Tên dự án |
| `thu_tu` | | Số nguyên; nhỏ hiện trước (mặc định 0) |
| `ten_de` | | Có tên tiếng Đức thì tạo bản tiếng Đức, nối vào cùng nhóm dịch |
| `slug_vi`, `slug_de` | | Đường dẫn; trống thì WordPress tự tạo từ tên |
| `loai` | | Loại công trình: `ngoai-giao-cong-quyen`, `quan-su-an-ninh`, `giao-duc-dao-tao`, `van-hoa-di-tich`, `y-te`, `cong-nghiep-ha-tang`, `nha-o-dan-dung` (hoặc tên đầy đủ); nhiều loại ngăn cách bằng `\|` |
| `quoc_gia` | | `vn`, `la`, `de` (hoặc Việt Nam, Lào, Đức) |
| `dia_diem_vi/_de`, `thoi_gian` | | Địa điểm; thời gian (tối đa 20 ký tự, ví dụ `2019–2020`) |
| `vai_tro_vi/_de`, `pham_vi_vi/_de` | | Vai trò và phạm vi tham gia của VGARCO |
| `chu_dau_tu_vi/_de`, `doi_tac` | | Chủ đầu tư, đối tác (`doi_tac` dùng chung hai ngôn ngữ; `doi_tac_vi/_de` nếu cần tách) |
| `cong_bo_chu_dau_tu` | | `1`/`0`, `có`/`không`. **Chỉ bật khi chủ đầu tư đồng ý công bố** (mục 3.2 #8); tắt thì website không hiển thị chủ đầu tư và đối tác |
| `mo_ta_vi/_de` | | Mô tả; dòng trống tách đoạn; HTML bị thoát |
| `loai_anh_dai_dien` | | `thiet_ke` (mặc định) hoặc `cong_trinh` |
| `noi_bat`, `tieu_bieu` | | Đưa lên trình chiếu (tối đa 5) / khối "Dự án tiêu biểu" (tối đa 3) của trang chủ |
| `mo_ta_seo_vi/_de` | | Tối đa 160 ký tự; trống thì lấy đoạn đầu của mô tả |

`content/projects.csv` là **bảng nguồn** của dự án (sửa trực tiếp, không còn công cụ sinh). Dữ liệu lấy từ tệp `NOI DUNG WEB` của khách: 20 dự án trong danh mục công trình tiêu biểu
(tên, chủ đầu tư, địa điểm, đối tác, vai trò, thời gian) cùng 3 dự án có ảnh nhưng ngoài danh mục (Nhà Đại sứ, Trường mầm non Xuân Đỉnh, Đồn Biên phòng 214). Ô nào khách chưa cung cấp thì để trống.
Chưa có tên tiếng Đức. `cong_bo_chu_dau_tu` là `1` ở mọi dự án khách đã cung cấp chủ đầu tư hoặc đối tác. Trang chủ: 5 dự án nổi bật, 3 dự án tiêu biểu, đều chọn trong 20 dự án của danh mục
theo số ảnh công trình thật và chất lượng ảnh. Dự án chưa có ảnh (7 dự án) vẫn hiện, thẻ không có ảnh.

## images.csv

| Cột | Bắt buộc | Ý nghĩa |
|---|---|---|
| `ma` | có | Mã dự án (phải có trong `projects.csv`) |
| `tep` | có | Đường dẫn tương đối trong thư mục `--images`, đuôi jpg/jpeg/png/webp, không `..` |
| `vai_tro` | có | `hero` (đầu trang 21:9), `the` (thẻ 4:3), `slide` (trình chiếu 16:9), `tv` (thư viện, tối đa 15) |
| `thu_tu` | | Thứ tự trong thư viện (ô trống xếp cuối) |
| `loai_anh` | | `thiet_ke` (mặc định) hoặc `cong_trinh` |
| `chu_thich_vi/_de` | `tv` cần `chu_thich_vi` | Mô tả thật của ảnh, dùng làm văn bản thay thế cho người khiếm thị |

Siêu dữ liệu (EXIF/GPS) của ảnh tải lên được **gỡ tự động ở máy chủ** (kể cả ảnh nhập bằng `wp vgarco import`): đo trên ảnh thật, 22 trong 113 ảnh JPEG của dự án có tọa độ GPS.
Vẫn nên xuất ảnh bằng công cụ dưới đây trước khi nhập — nó còn xoay đúng chiều, giới hạn kích thước và dung lượng.

### Xuất ảnh master

```bash
php scripts/content/export-images.php <thư-mục-đã-cắt> <thư-mục-xuất> --csv=<thư-mục-xuất>/images.csv
php scripts/content/export-images.php <thư-mục-đã-cắt> <thư-mục-xuất> --dry-run    # chỉ kiểm tra, không ghi tệp
```

Cần PHP 8.3 với `gd` (JPEG, PNG; AVIF để có số ước lượng), không cần WordPress hay gói npm. **Ảnh phải được cắt tay đúng tỉ lệ trước** (hero 21:9, trình chiếu 16:9, thẻ 4:3):
công cụ không bao giờ tự cắt vì cắt tự động xén mất mái hoặc nền công trình. Đặt tên theo mã dự án:

| Tên tệp | Vai trò | Khung tối đa (không phóng to) |
|---|---|---|
| `<ma>-hero.jpg` | đầu trang 21:9 | 2560 × 1097 |
| `<ma>-slide.jpg` | trình chiếu 16:9 | 1600 × 900 |
| `<ma>-the.jpg` | thẻ 4:3 | 1200 × 900 |
| `<ma>-tv-01.jpg`, `-tv-02.jpg`... | thư viện (tối đa 15) | 1800 × 1800 |

Đuôi `jpg`, `jpeg` hoặc `png` (PNG render 20–30 MB được đổi sang JPEG); tệp xuất luôn là `.jpg`. Mỗi ảnh được xoay theo EXIF, thu nhỏ vừa khung, nén JPEG chất lượng 86 (tự hạ từng bước nếu quá **1,5 MB**,
chạm q70 mà vẫn quá thì báo lỗi), gỡ mọi siêu dữ liệu. Công cụ **báo** (không sửa): ảnh lệch tỉ lệ quy cách (`--strict` coi là lỗi), ảnh có hồ sơ màu không phải sRGB (GD không đổi được hồ sơ màu:
xuất sRGB từ Lightroom/Photoshop trước), ảnh có AVIF ước lượng vượt trần dung lượng của mục 9.2 (chỉ cảnh báo: trần được giữ, máy chủ tự hạ chất lượng AVIF riêng ảnh đó khi nạp; xem `wp vgarco report images`). Tên sai, trùng hay tệp hỏng thì **không ghi gì cả**,
mọi lỗi báo một lượt. Kết quả: các tệp `.jpg` trong thư mục xuất và (nếu có `--csv`) bộ khung `images.csv` — **chú thích để trống**, phải điền `chu_thich_vi` thật cho ảnh thư viện trước khi nhập;
công cụ không ghi đè `images.csv` đã có. Rồi nhập: `wp vgarco import projects content/projects.csv --images=<thư-mục-xuất>`.

### Ảnh thật của 16 dự án: `photos.json` và `images.csv`

- `photos.json` là **kế hoạch cắt ảnh**: với mỗi dự án có thư mục ảnh, ghi tệp gốc dùng cho hero (21:9), trình chiếu (16:9), thẻ (4:3), vùng cắt (`cat` theo điểm ảnh, hoặc `cx`/`cy`/`w` là tâm và độ rộng theo tỉ lệ),
  và 74 ảnh thư viện kèm `loai` (`thiet_ke`/`cong_trinh`), `vi` (chú thích tiếng Việt) và `cat` (cắt bỏ viền, theo tỉ lệ `[x0,y0,x1,y1]`). Các vùng cắt đã được xem từng ảnh và chọn tay, không dùng cắt tự động.
- `images.csv` là bảng ảnh dùng để nhập (`wp vgarco import projects ... --images=<thư-mục-masters>`), sinh ra cùng bộ ảnh master; chú thích tiếng Đức còn trống.
- Dựng lại ảnh master từ thư mục ảnh gốc của khách:
  `php tests/wp-lab/demo/prepare-photos.php <thư-mục-ảnh-gốc> <thư-mục-ra> --manual=content/photos.json`. Ảnh gốc và ảnh master **không** nằm trong kho mã.
- Ảnh thấp độ phân giải (14 ảnh) được phóng to và làm nét, mềm hơn ảnh gốc cùng cỡ; thay bằng ảnh gốc độ phân giải cao khi khách có.

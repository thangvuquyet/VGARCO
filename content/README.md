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

`content/projects.csv` là **bản nháp** sinh từ dữ liệu của bản thiết kế HTML (`node scripts/content/make-projects-csv.mjs`): 19 dự án, chưa có tên tiếng Đức, nhiều dự án còn thiếu địa điểm/năm,
và `cong_bo_chu_dau_tu` đang `0` cho mọi dự án. Cần rà cùng chủ đầu tư trước khi nhập thật (danh sách 20 dự án chưa chốt).

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
xuất sRGB từ Lightroom/Photoshop trước), ảnh có AVIF ước lượng vượt trần dung lượng của mục 9.2 (chỉ cảnh báo vì trần còn chờ chủ đầu tư quyết). Tên sai, trùng hay tệp hỏng thì **không ghi gì cả**,
mọi lỗi báo một lượt. Kết quả: các tệp `.jpg` trong thư mục xuất và (nếu có `--csv`) bộ khung `images.csv` — **chú thích để trống**, phải điền `chu_thich_vi` thật cho ảnh thư viện trước khi nhập;
công cụ không ghi đè `images.csv` đã có. Rồi nhập: `wp vgarco import projects content/projects.csv --images=<thư-mục-xuất>`.

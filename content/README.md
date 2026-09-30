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

Ảnh chụp bằng điện thoại phải gỡ siêu dữ liệu (EXIF/GPS) trước khi nhập — bắt buộc với công trình quân sự, ngoại giao (phương án mục 9.1).

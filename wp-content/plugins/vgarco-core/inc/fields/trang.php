<?php
/**
 * Trường của các trang tĩnh (mục 6.5 của phương án): mỗi trang dùng một template, mỗi template một nhóm trường.
 *
 * Mọi ô chữ đều có bản mặc định trong template (giao diện): biên tập viên để trống thì trang vẫn hiển thị đúng
 * nội dung của bản thiết kế, nhập vào thì bản nhập luôn thắng. Ảnh và các khối phụ thuộc dữ liệu (ghi nhận, thành viên)
 * không có mặc định: để trống thì khối ẩn.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quy tắc vị trí: nhóm trường chỉ hiện ở trang dùng một template.
 *
 * @param string $template Ví dụ "templates/about.php".
 */
function vgarco_location_page_template( string $template ): array {
	return array(
		array(
			array(
				'param'    => 'page_template',
				'operator' => '==',
				'value'    => $template,
			),
		),
	);
}

/** Khung chung của một nhóm trường trang. */
function vgarco_page_group( string $key, string $title, string $template, array $fields ): array {
	return array(
		'key'                   => $key,
		'title'                 => $title,
		'fields'                => $fields,
		'location'              => vgarco_location_page_template( $template ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	);
}

/** Ô chữ một dòng, dịch theo từng ngôn ngữ. */
function vgarco_page_text_field( Closure $b, string $name, string $label, string $hint = '' ): array {
	return $b( $name, $label, 'text', 'translate', array( 'instructions' => $hint ) );
}

/** Ô nhiều dòng; $break = true: giữ xuống dòng làm chỗ ngắt dòng chủ ý. */
function vgarco_page_area_field( Closure $b, string $name, string $label, string $hint = '', int $rows = 2 ): array {
	return $b(
		$name,
		$label,
		'textarea',
		'translate',
		array(
			'rows'         => $rows,
			'new_lines'    => '',
			'instructions' => $hint,
		)
	);
}

/** Ô tiêu đề: hai dòng, cụm nhấn đặt trong <em>. */
function vgarco_page_heading_field( Closure $b, string $name, string $label ): array {
	return vgarco_page_area_field(
		$b,
		$name,
		$label,
		'Xuống dòng ở chỗ muốn ngắt. Đặt <em>...</em> quanh cụm chữ nhấn (chữ nghiêng, màu đỏ). Để trống thì dùng nội dung mặc định.'
	);
}

function vgarco_page_tab( Closure $b, string $name, string $label ): array {
	return $b( $name, $label, 'tab', 'ignore', array( 'placement' => 'top' ) );
}

/**
 * Trang Về công ty.
 */
function vgarco_fields_page_about(): array {
	$b = vgarco_field_builder( 'about' );
	// Ô con của trường lặp cần tiền tố riêng: khóa trường phải duy nhất toàn site.
	$moc = vgarco_field_builder( 'about_moc' );
	$nt  = vgarco_field_builder( 'about_nguyen_tac' );

	return vgarco_page_group(
		'group_vg_page_about',
		'Nội dung trang Về công ty',
		'templates/about.php',
		array(
			vgarco_page_tab( $b, 'tab_hero', 'Đầu trang' ),
			vgarco_page_text_field( $b, 'hero_nhan', 'Nhãn đầu trang', 'Mặc định: Về công ty / Est. 2013.' ),
			vgarco_page_heading_field( $b, 'hero_tieu_de', 'Tiêu đề' ),
			vgarco_page_text_field( $b, 'hero_mo_ta_1', 'Dòng giới thiệu ngắn' ),
			vgarco_page_area_field( $b, 'hero_mo_ta_2', 'Câu giới thiệu', '', 3 ),

			vgarco_page_tab( $b, 'tab_bia', 'Ảnh bìa' ),
			$b(
				'bia_anh',
				'Ảnh bìa',
				'image',
				'copy',
				array(
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'instructions'  => 'Ảnh ngang rộng (tỷ lệ khoảng 2,4 : 1). Không chọn ảnh thì ẩn cả khối ảnh bìa.',
				)
			),
			$b( 'bia_nam', 'Năm ở góc ảnh', 'text', 'copy', array( 'maxlength' => 9, 'instructions' => 'Mặc định: 2013.' ) ),
			vgarco_page_text_field( $b, 'bia_nam_nhan', 'Nhãn dưới năm', 'Mặc định: Năm khởi đầu.' ),
			vgarco_page_text_field( $b, 'bia_chu_thich_trai', 'Chú thích ảnh — bên trái', 'Tên công trình trong ảnh. Cũng dùng làm mô tả ảnh cho người dùng trình đọc màn hình.' ),
			vgarco_page_text_field( $b, 'bia_chu_thich_phai', 'Chú thích ảnh — bên phải', 'Địa điểm và loại ảnh, ví dụ: Hà Nội, Việt Nam / Ảnh công trình.' ),

			vgarco_page_tab( $b, 'tab_cau_chuyen', 'Chúng tôi là ai' ),
			vgarco_page_area_field( $b, 'cau_chuyen_ghi_chu', 'Ghi chú bên lề', 'Hai dòng ngắn cạnh tiêu đề. Ẩn trên điện thoại.' ),
			vgarco_page_heading_field( $b, 'cau_chuyen_tieu_de', 'Tiêu đề' ),
			vgarco_page_area_field( $b, 'cau_chuyen_doan_1', 'Đoạn 1', 'Thông tin pháp lý phải khớp giấy đăng ký: thành lập 19/08/2013 (3SEVEN), đổi tên VGARCO 05/12/2023.', 4 ),
			vgarco_page_area_field( $b, 'cau_chuyen_doan_2', 'Đoạn 2', '', 4 ),
			vgarco_page_area_field( $b, 'cau_chuyen_ket', 'Đoạn kết', '', 3 ),
			vgarco_page_text_field( $b, 'chi_so_ghi_chu', 'Ghi chú dưới các chỉ số', 'Bốn chỉ số nhập ở trang Cấu hình VGARCO.' ),

			vgarco_page_tab( $b, 'tab_hanh_trinh', 'Hành trình' ),
			vgarco_page_heading_field( $b, 'hanh_trinh_tieu_de', 'Tiêu đề' ),
			vgarco_page_text_field( $b, 'hanh_trinh_mo_ta', 'Mô tả ngắn' ),
			$b(
				'hanh_trinh_moc',
				'Các mốc',
				'repeater',
				'translate',
				array(
					'layout'       => 'block',
					'min'          => 0,
					'max'          => 8,
					'button_label' => 'Thêm mốc',
					'instructions' => 'Theo đặc tả có 4 mốc. Để trống toàn bộ thì dùng 4 mốc mặc định.',
					'sub_fields'   => array(
						$moc( 'nam', 'Năm', 'text', 'copy', array( 'maxlength' => 9, 'instructions' => 'Ví dụ: 2013 hoặc 2019–20.' ) ),
						$moc( 'dong_phu', 'Ngày hoặc địa điểm', 'text', 'translate', array( 'instructions' => 'Ví dụ: 19.08.2013 hoặc Viêng Chăn, Lào.' ) ),
						$moc( 'tieu_de', 'Tiêu đề', 'text', 'translate' ),
						$moc( 'mo_ta', 'Mô tả', 'textarea', 'translate', array( 'rows' => 3, 'new_lines' => '' ) ),
					),
				)
			),

			vgarco_page_tab( $b, 'tab_cach_lam', 'Cách làm việc' ),
			vgarco_page_heading_field( $b, 'cach_lam_tieu_de', 'Tiêu đề' ),
			$b( 'cach_lam_anh', 'Ảnh', 'image', 'copy', array( 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'instructions' => 'Không chọn ảnh thì ẩn ảnh.' ) ),
			vgarco_page_text_field( $b, 'cach_lam_chu_thich', 'Chú thích ảnh', 'Cũng dùng làm mô tả ảnh cho người dùng trình đọc màn hình.' ),
			$b(
				'cach_lam_muc',
				'Các nguyên tắc',
				'repeater',
				'translate',
				array(
					'layout'       => 'block',
					'min'          => 0,
					'max'          => 6,
					'button_label' => 'Thêm nguyên tắc',
					'instructions' => 'Theo đặc tả có 3 nguyên tắc. Để trống toàn bộ thì dùng 3 nguyên tắc mặc định.',
					'sub_fields'   => array(
						$nt( 'tieu_de', 'Tiêu đề', 'text', 'translate' ),
						$nt( 'mo_ta', 'Mô tả', 'textarea', 'translate', array( 'rows' => 3, 'new_lines' => '' ) ),
					),
				)
			),

			vgarco_page_tab( $b, 'tab_pham_vi', 'Phạm vi hoạt động' ),
			vgarco_page_heading_field( $b, 'pham_vi_tieu_de', 'Tiêu đề' ),
			vgarco_page_area_field( $b, 'pham_vi_mo_ta', 'Mô tả', '', 3 ),
			vgarco_page_area_field( $b, 'pham_vi_quoc_gia', 'Các quốc gia', 'Mỗi dòng một quốc gia. Mặc định: Việt Nam, Lào, CHLB Đức.', 4 ),

			vgarco_page_tab( $b, 'tab_ghi_nhan', 'Sự ghi nhận' ),
			$b(
				'ghi_nhan_anh',
				'Ảnh tư liệu',
				'image',
				'copy',
				array(
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'instructions'  => 'Không có ảnh và không có tiêu đề thì ẩn cả khối "Sự ghi nhận" (UC-02 2c).',
				)
			),
			vgarco_page_text_field( $b, 'ghi_nhan_anh_alt', 'Mô tả ảnh tư liệu', 'Cho người dùng trình đọc màn hình. Ví dụ: Bằng khen trong tư liệu dự án Bảo tàng Quân đội nhân dân Lào.' ),
			vgarco_page_area_field( $b, 'ghi_nhan_tieu_de', 'Tiêu đề', 'Xuống dòng ở chỗ muốn ngắt.' ),
			vgarco_page_area_field( $b, 'ghi_nhan_mo_ta', 'Mô tả', '', 3 ),
			vgarco_page_text_field( $b, 'ghi_nhan_chu_thich', 'Ghi chú nhỏ', 'Ví dụ: Tư liệu mang tên 3SEVEN, tên gọi trước đây của VGARCO.' ),
			$b(
				'ghi_nhan_bai_viet',
				'Bài viết tư liệu',
				'post_object',
				'copy',
				array(
					'post_type'     => array( 'tin_tuc' ),
					'return_format' => 'id',
					'allow_null'    => 1,
					'ui'            => 1,
					'instructions'  => 'Nút "Xem tư liệu ghi nhận" dẫn tới bài này. Không chọn, hoặc bài chưa xuất bản, thì ẩn nút.',
				)
			),
		)
	);
}

/**
 * Trang Ban lãnh đạo: danh sách thành viên theo thứ tự nhập (UC-02 4a) và các nguyên tắc làm việc.
 */
function vgarco_fields_page_leadership(): array {
	$b  = vgarco_field_builder( 'lanh_dao' );
	$tv = vgarco_field_builder( 'lanh_dao_tv' );
	$nt = vgarco_field_builder( 'lanh_dao_nt' );

	return vgarco_page_group(
		'group_vg_page_leadership',
		'Nội dung trang Ban lãnh đạo',
		'templates/leadership.php',
		array(
			$b(
				'thanh_vien',
				'Thành viên ban lãnh đạo',
				'repeater',
				'translate',
				array(
					'layout'       => 'block',
					'min'          => 0,
					'button_label' => 'Thêm thành viên',
					'instructions' => 'Hiển thị theo đúng thứ tự ở đây. Kéo thả để đổi thứ tự. Để trống toàn bộ thì dùng thành viên mặc định trong hồ sơ.',
					'sub_fields'   => array(
						$tv( 'anh', 'Ảnh chân dung', 'image', 'copy', array( 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'instructions' => 'Không bắt buộc. Không có ảnh thì hiển thị chữ cái đầu của họ tên.' ) ),
						$tv( 'chuc_danh', 'Chức danh', 'text', 'translate', array( 'instructions' => 'Ví dụ: Giám đốc.' ) ),
						$tv( 'ho_ten', 'Họ và tên', 'text', 'copy' ),
						$tv( 'don_vi', 'Đơn vị', 'text', 'translate', array( 'instructions' => 'Để trống thì dùng tên công ty trong Cấu hình VGARCO.' ) ),
						$tv( 'vai_tro', 'Vai trò', 'textarea', 'translate', array( 'rows' => 3, 'new_lines' => '' ) ),
						$tv( 'trong_tam', 'Trọng tâm điều hành', 'textarea', 'translate', array( 'rows' => 3, 'new_lines' => '' ) ),
					),
				)
			),
			$b(
				'nguyen_tac',
				'Nguyên tắc làm việc',
				'repeater',
				'translate',
				array(
					'layout'       => 'block',
					'min'          => 0,
					'max'          => 8,
					'button_label' => 'Thêm nguyên tắc',
					'instructions' => 'Theo đặc tả có 4 nguyên tắc. Để trống toàn bộ thì dùng 4 nguyên tắc mặc định.',
					'sub_fields'   => array(
						$nt( 'tieu_de', 'Tiêu đề', 'text', 'translate' ),
						$nt( 'mo_ta', 'Mô tả', 'textarea', 'translate', array( 'rows' => 2, 'new_lines' => '' ) ),
					),
				)
			),
		)
	);
}

/**
 * Trang Phòng ban: mở đầu, 4 nhãn, 6 bộ môn.
 */
function vgarco_fields_page_departments(): array {
	$b  = vgarco_field_builder( 'phong_ban' );
	$bm = vgarco_field_builder( 'phong_ban_bm' );

	return vgarco_page_group(
		'group_vg_page_departments',
		'Nội dung trang Phòng ban',
		'templates/departments.php',
		array(
			vgarco_page_text_field( $b, 'hero_nhan', 'Nhãn đầu trang', 'Mặc định: Hệ chuyên môn.' ),
			vgarco_page_heading_field( $b, 'hero_tieu_de', 'Tiêu đề' ),
			vgarco_page_area_field( $b, 'hero_mo_ta', 'Mô tả', '', 4 ),
			vgarco_page_area_field( $b, 'nhan', 'Bốn nhóm', 'Mỗi dòng một nhãn. Mặc định: Thiết kế, Kỹ thuật, Chi phí, Triển khai.', 5 ),
			$b(
				'bo_mon',
				'Các bộ môn',
				'repeater',
				'translate',
				array(
					'layout'       => 'block',
					'min'          => 0,
					'max'          => 12,
					'button_label' => 'Thêm bộ môn',
					'instructions' => 'Theo đặc tả có 6 bộ môn. Để trống toàn bộ thì dùng 6 bộ môn mặc định.',
					'sub_fields'   => array(
						$bm( 'tieu_de', 'Tên bộ môn', 'text', 'translate' ),
						$bm( 'mo_ta', 'Mô tả', 'textarea', 'translate', array( 'rows' => 2, 'new_lines' => '' ) ),
					),
				)
			),
		)
	);
}

/**
 * Trang Lĩnh vực hoạt động: chỉ phần mở đầu. Danh sách lĩnh vực nhập ở mục "Lĩnh vực hoạt động" (mỗi lĩnh vực một bài).
 */
function vgarco_fields_page_services(): array {
	$b = vgarco_field_builder( 'lv_trang' );

	return vgarco_page_group(
		'group_vg_page_services',
		'Nội dung trang Lĩnh vực hoạt động',
		'templates/services.php',
		array(
			vgarco_page_tab( $b, 'tab_hero', 'Đầu trang' ),
			vgarco_page_heading_field( $b, 'hero_tieu_de', 'Tiêu đề' ),
			vgarco_page_area_field( $b, 'hero_mo_ta', 'Câu mô tả', '', 3 ),
		)
	);
}

/**
 * Trang Liên hệ: chỉ phần mở đầu. Điện thoại, email, mạng xã hội lấy từ Cấu hình VGARCO.
 */
function vgarco_fields_page_contact(): array {
	$b = vgarco_field_builder( 'lien_he_trang' );

	return vgarco_page_group(
		'group_vg_page_contact',
		'Nội dung trang Liên hệ',
		'templates/contact.php',
		array(
			vgarco_page_text_field( $b, 'hero_nhan', 'Nhãn đầu trang', 'Mặc định: Bắt đầu một dự án.' ),
			vgarco_page_heading_field( $b, 'hero_tieu_de', 'Tiêu đề' ),
			vgarco_page_text_field( $b, 'form_tieu_de', 'Tiêu đề biểu mẫu', 'Mặc định: Chia sẻ nhu cầu của bạn.' ),
		)
	);
}

/**
 * Tất cả nhóm trường của trang tĩnh.
 *
 * @return array[]
 */
function vgarco_fields_trang(): array {
	return array(
		vgarco_fields_page_about(),
		vgarco_fields_page_leadership(),
		vgarco_fields_page_departments(),
		vgarco_fields_page_services(),
		vgarco_fields_page_contact(),
	);
}

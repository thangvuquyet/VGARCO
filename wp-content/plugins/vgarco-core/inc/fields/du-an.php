<?php

defined( 'ABSPATH' ) || exit;

function vgarco_fields_du_an(): array {
	$f = vgarco_field_builder( 'du_an' );
	$g = vgarco_field_builder( 'du_an_thu_vien' );

	$kind = array(
		'thiet_ke'   => 'Phối cảnh thiết kế',
		'cong_trinh' => 'Ảnh công trình',
	);
	$img  = array(
		'return_format' => 'id',
		'preview_size'  => 'medium',
		'library'       => 'all',
	);

	return array(
		'key'                   => 'group_vg_du_an',
		'title'                 => 'Thông tin dự án',
		'fields'                => array(
			$f(
				'ma',
				'Mã dự án',
				'text',
				'copy',
				array(
					'required'     => 1,
					'maxlength'    => 60,
					'instructions' => 'Mã ngắn, chữ thường không dấu. Dùng làm khóa khi nhập dữ liệu hàng loạt và làm tiền tố tên tệp ảnh. Ví dụ: bao-tang-qdnd-lao',
				)
			),
			$f(
				'thoi_gian',
				'Thời gian thực hiện',
				'text',
				'copy',
				array(
					'maxlength'    => 20,
					'instructions' => 'Ví dụ: 2019–2020. Để trống thì thẻ dự án tự ẩn trường này.',
				)
			),
			$f(
				'dia_diem',
				'Địa điểm',
				'text',
				'translate',
				array( 'instructions' => 'Ví dụ: Viêng Chăn, Lào.' )
			),
			$f(
				'quoc_gia',
				'Quốc gia',
				'select',
				'copy',
				array(
					'choices'       => array(
						'vn' => 'Việt Nam',
						'la' => 'CHDCND Lào',
						'de' => 'CHLB Đức',
					),
					'allow_null'    => 1,
					'return_format' => 'value',
					'instructions'  => 'Dùng để đếm số dự án theo quốc gia ở trang Về công ty.',
				)
			),
			$f(
				'vai_tro',
				'Vai trò của VGARCO',
				'text',
				'translate',
				array( 'instructions' => 'Ví dụ: Thiết kế Concept – TKCS – TKBVTC. Dùng đúng thuật ngữ trong bảng thuật ngữ Việt–Đức.' )
			),
			$f(
				'pham_vi',
				'Phạm vi tham gia',
				'textarea',
				'translate',
				array(
					'rows'         => 3,
					'instructions' => 'Mô tả ngắn phạm vi công việc VGARCO thực hiện. Để trống thì ẩn khối "Phạm vi tham gia".',
				)
			),
			$f( 'chu_dau_tu', 'Chủ đầu tư', 'text', 'translate' ),
			$f( 'doi_tac', 'Đối tác', 'text', 'translate' ),
			$f(
				'cong_bo_chu_dau_tu',
				'Được phép công bố chủ đầu tư và đối tác',
				'true_false',
				'copy',
				array(
					'ui'            => 1,
					'default_value' => 0,
					'instructions'  => 'Chỉ bật khi chủ đầu tư đã đồng ý công bố. Tắt thì website không hiển thị hai trường trên.',
				)
			),
			$f(
				'anh_dau_trang',
				'Ảnh đầu trang (21:9)',
				'image',
				'copy',
				array_merge(
					$img,
					array(
						'required'     => 1,
						'instructions' => 'Tỉ lệ 21:9, xuất 2560 × 1097. Ưu tiên ảnh phối cảnh mặt đứng chính. Không dùng ảnh nếu cắt 21:9 làm mất mái hoặc nền công trình.',
					)
				)
			),
			$f(
				'anh_the',
				'Ảnh thẻ dự án (4:3)',
				'image',
				'copy',
				array_merge(
					$img,
					array(
						'required'     => 1,
						'instructions' => 'Tỉ lệ 4:3, xuất 1200 × 900. Hiển thị ở danh mục, tìm kiếm và trang chủ.',
					)
				)
			),
			$f(
				'anh_trinh_chieu',
				'Ảnh trình chiếu trang chủ (16:9)',
				'image',
				'copy',
				array_merge(
					$img,
					array( 'instructions' => 'Tỉ lệ 16:9, xuất 1600 × 900. Chỉ cần khi dự án được đánh dấu "Nổi bật".' )
				)
			),
			$f(
				'loai_anh_dai_dien',
				'Loại ảnh đại diện',
				'select',
				'copy',
				array(
					'choices'       => $kind,
					'default_value' => 'thiet_ke',
					'return_format' => 'value',
					'instructions'  => 'Nhãn hiển thị trên thẻ và ảnh đầu trang, để người xem phân biệt phương án thiết kế với công trình thực tế.',
				)
			),
			$f(
				'thu_vien',
				'Thư viện ảnh',
				'repeater',
				'translate',
				array(
					'layout'       => 'block',
					'min'          => 0,
					'max'          => 15,
					'button_label' => 'Thêm ảnh',
					'instructions' => 'Tối đa 15 ảnh, kéo để đổi thứ tự. Ảnh ngang 3:2 xuất 1800 × 1200; ảnh dọc 2:3 xuất 1200 × 1800.',
					'sub_fields'   => array(
						$g( 'anh', 'Ảnh', 'image', 'copy', array_merge( $img, array( 'required' => 1 ) ) ),
						$g(
							'loai_anh',
							'Loại ảnh',
							'select',
							'copy',
							array(
								'choices'       => $kind,
								'default_value' => 'thiet_ke',
								'return_format' => 'value',
							)
						),
						$g(
							'chu_thich',
							'Chú thích',
							'text',
							'translate',
							array(
								'required'     => 1,
								'instructions' => 'Mô tả nội dung thật của ảnh. Dùng làm văn bản thay thế (alt) cho người dùng trình đọc màn hình.',
							)
						),
					),
				)
			),
			$f(
				'noi_bat',
				'Nổi bật trên trang chủ',
				'true_false',
				'copy',
				array(
					'ui'            => 1,
					'default_value' => 0,
					'instructions'  => 'Đưa vào trình chiếu đầu trang chủ. Tối đa 5 dự án; trang chủ chỉ hiển thị 5 dự án đầu theo thứ tự.',
				)
			),
			$f(
				'tieu_bieu',
				'Dự án tiêu biểu trên trang chủ',
				'true_false',
				'copy',
				array(
					'ui'            => 1,
					'default_value' => 0,
					'instructions'  => 'Đưa vào khối "Dự án tiêu biểu" (3 thẻ). Trang chủ chỉ hiển thị 3 dự án đầu theo thứ tự.',
				)
			),
			$f(
				'mo_ta_seo',
				'Mô tả cho công cụ tìm kiếm',
				'text',
				'translate',
				array(
					'maxlength'    => 160,
					'instructions' => 'Tối đa 160 ký tự. Để trống thì lấy đoạn đầu của phần mô tả.',
				)
			),
		),
		'location'              => vgarco_location_post_type( 'du_an' ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	);
}

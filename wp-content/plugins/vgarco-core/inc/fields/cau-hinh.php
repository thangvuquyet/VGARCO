<?php

defined( 'ABSPATH' ) || exit;

function vgarco_location_options_page(): array {
	return array(
		array(
			array(
				'param'    => 'options_page',
				'operator' => '==',
				'value'    => 'vgarco-cau-hinh',
			),
		),
	);
}

function vgarco_fields_cau_hinh(): array {
	$o = vgarco_field_builder( 'opt' );

	$common = array(
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
		'menu_order'            => 0,
		'location'              => vgarco_location_options_page(),
	);

	$phap_nhan = vgarco_field_builder( 'opt_phap_nhan' );
	$lien_he   = vgarco_field_builder( 'opt_lien_he' );
	$mxh       = vgarco_field_builder( 'opt_mxh' );

	$group_lien_he = array_merge(
		array(
			'key'    => 'group_vg_opt_lien_he',
			'title'  => 'Pháp nhân và liên hệ',
			'fields' => array(
				$o(
					'phap_nhan',
					'Pháp nhân',
					'group',
					'translate',
					array(
						'layout'     => 'block',
						'sub_fields' => array(
							$phap_nhan( 'ten', 'Tên công ty', 'text', 'copy' ),
							$phap_nhan( 'ma_so_thue', 'Mã số thuế', 'text', 'copy' ),
							$phap_nhan( 'dia_chi_dang_ky', 'Địa chỉ đăng ký kinh doanh', 'text', 'translate' ),
							$phap_nhan(
								'dia_chi_van_phong',
								'Địa chỉ văn phòng',
								'textarea',
								'translate',
								array(
									'rows'         => 2,
									'new_lines'    => '',
									'instructions' => 'Hiển thị ở chân trang, mỗi dòng một dòng (thường xuống dòng sau tên phố). Cũng dùng để tạo liên kết "Chỉ đường" — khi đó các dòng được nối lại.',
								)
							),
						),
					)
				),
				$o(
					'lien_he',
					'Liên hệ',
					'group',
					'copy',
					array(
						'layout'     => 'block',
						'sub_fields' => array(
							$lien_he( 'dien_thoai', 'Số điện thoại hiển thị', 'text', 'copy', array( 'instructions' => 'Ví dụ: 0988 961 666.' ) ),
							$lien_he(
								'dien_thoai_tel',
								'Số điện thoại để gọi',
								'text',
								'copy',
								array( 'instructions' => 'Dạng quốc tế, không dấu cách, dùng cho liên kết bấm để gọi. Ví dụ: +84988961666.' )
							),
							$lien_he( 'email_chung', 'Email nhận yêu cầu tư vấn', 'email', 'copy' ),
							$lien_he(
								'email_tuyen_dung',
								'Email tuyển dụng',
								'email',
								'copy',
								array( 'instructions' => 'Để trống thì dùng email nhận yêu cầu tư vấn.' )
							),
						),
					)
				),
				$o(
					'mang_xa_hoi',
					'Mạng xã hội',
					'group',
					'copy',
					array(
						'layout'       => 'block',
						'instructions' => 'Để trống thì website hiển thị "Đang cập nhật".',
						'sub_fields'   => array(
							$mxh( 'zalo', 'Zalo', 'url', 'copy' ),
							$mxh( 'facebook', 'Facebook', 'url', 'copy' ),
						),
					)
				),
				$o(
					'chi_duong_url',
					'Liên kết "Chỉ đường"',
					'url',
					'copy',
					array( 'instructions' => 'Để trống thì tự tạo liên kết Google Maps từ địa chỉ văn phòng. Website không nhúng bản đồ.' )
				),
			),
		),
		$common
	);

	$m_home  = vgarco_field_builder( 'opt_cs_home' );
	$m_abt   = vgarco_field_builder( 'opt_cs_about' );
	$partner = vgarco_field_builder( 'opt_doi_tac' );
	$story   = vgarco_field_builder( 'opt_cau_chuyen' );

	$metric_sub = static function ( Closure $b ): array {
		return array(
			$b( 'so', 'Con số', 'text', 'copy', array( 'instructions' => 'Ví dụ: 13' ) ),
			$b( 'hau_to', 'Hậu tố', 'text', 'copy', array( 'instructions' => 'Ví dụ: + (để trống nếu không có)', 'maxlength' => 3 ) ),
			$b( 'nhan', 'Nhãn', 'text', 'translate', array( 'instructions' => 'Ví dụ: Năm hình thành và phát triển' ) ),
		);
	};

	$group_trang = array_merge(
		array(
			'key'    => 'group_vg_opt_trang',
			'title'  => 'Trang chủ và giới thiệu',
			'fields' => array(
				$o(
					'chi_so_trang_chu',
					'Chỉ số năng lực — trang chủ',
					'repeater',
					'translate',
					array(
						'layout'       => 'table',
						'min'          => 3,
						'max'          => 3,
						'button_label' => 'Thêm chỉ số',
						'instructions' => 'Đúng 3 chỉ số. Để trống toàn bộ thì dùng giá trị mặc định trong mã.',
						'sub_fields'   => $metric_sub( $m_home ),
					)
				),
				$o(
					'chi_so_gioi_thieu',
					'Chỉ số năng lực — trang Về công ty',
					'repeater',
					'translate',
					array(
						'layout'       => 'table',
						'min'          => 0,
						'max'          => 4,
						'button_label' => 'Thêm chỉ số',
						'instructions' => 'Đúng 4 chỉ số theo đặc tả.',
						'sub_fields'   => $metric_sub( $m_abt ),
					)
				),
				$o(
					'dai_doi_tac',
					'Khách hàng và đối tác',
					'repeater',
					'translate',
					array(
						'layout'       => 'table',
						'min'          => 0,
						'button_label' => 'Thêm đối tác',
						'instructions' => 'Hiển thị ở dải chạy ngang trên trang chủ. Chỉ đưa tên đơn vị đã đồng ý công bố (mục 3.2 #8).',
						'sub_fields'   => array(
							$partner( 'ten', 'Tên đơn vị', 'text', 'copy' ),
							$partner( 'viet_tat', 'Viết tắt', 'text', 'copy', array( 'maxlength' => 4, 'instructions' => 'Hiển thị trong vòng tròn khi chưa có logo.' ) ),
							$partner( 'mo_ta', 'Mô tả ngắn', 'text', 'translate' ),
							$partner( 'logo', 'Logo', 'image', 'copy', array( 'return_format' => 'id', 'preview_size' => 'thumbnail', 'library' => 'all' ) ),
						),
					)
				),
				$o(
					'anh_hero_mac_dinh',
					'Ảnh đầu trang mặc định',
					'image',
					'copy',
					array(
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
						'instructions'  => 'Dùng khi chưa có dự án nào được đánh dấu nổi bật (UC-01 3c).',
					)
				),
				$o(
					'cau_chuyen',
					'Câu chuyện một công trình',
					'group',
					'copy',
					array(
						'layout'       => 'block',
						'instructions' => 'Khối ở trang Danh mục dự án, chuyển giữa ảnh phối cảnh và ảnh công trình thực tế (UC-04). Chọn hai ảnh CÙNG một công trình, tốt nhất cùng góc nhìn.',
						'sub_fields'   => array(
							$story( 'du_an', 'Dự án', 'post_object', 'copy', array( 'post_type' => array( 'du_an' ), 'return_format' => 'id', 'allow_null' => 1, 'ui' => 1 ) ),
							$story( 'tieu_de', 'Tiêu đề', 'textarea', 'translate', array( 'rows' => 2, 'new_lines' => '', 'instructions' => 'Hai dòng, xuống dòng giữa hai dòng. Đặt <em>...</em> quanh cụm nhấn. Ví dụ: Giữ lại ký ức.⏎<em>Viết tiếp giá trị.</em> Để trống thì dùng tên dự án.' ) ),
							$story( 'mo_ta', 'Mô tả ngắn', 'textarea', 'translate', array( 'rows' => 3, 'instructions' => 'Một hai câu về hành trình từ phương án thiết kế đến công trình thực tế.' ) ),
							$story( 'anh_thiet_ke', 'Ảnh phối cảnh thiết kế', 'image', 'copy', array( 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all' ) ),
							$story( 'anh_thuc_te', 'Ảnh công trình thực tế', 'image', 'copy', array( 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all' ) ),
						),
					)
				),
			),
		),
		$common
	);

	$mail = vgarco_field_builder( 'opt_mau_thu' );
	$hint = 'Chỗ điền: {ten} {vi_tri} {email} {dien_thoai} {thoi_diem} {ngon_ngu} {lien_ket}. Để trống thì dùng mẫu mặc định.';

	$group_thu = array_merge(
		array(
			'key'    => 'group_vg_opt_mau_thu',
			'title'  => 'Mẫu thư ứng tuyển',
			'fields' => array(
				$o(
					'mau_thu',
					'Mẫu thư',
					'group',
					'translate',
					array(
						'layout'       => 'block',
						'instructions' => 'Thư gửi khi có hồ sơ ứng tuyển mới. Thư báo cho bộ phận nhân sự luôn bằng tiếng Việt và KHÔNG đính kèm CV (xem CV trong trang quản trị). Thư xác nhận gửi ứng viên theo ngôn ngữ họ đã dùng để nộp hồ sơ.',
						'sub_fields'   => array(
							$mail( 'hr_tieu_de', 'Thư báo nhân sự — tiêu đề', 'text', 'copy', array( 'instructions' => $hint ) ),
							$mail( 'hr_noi_dung', 'Thư báo nhân sự — nội dung', 'textarea', 'copy', array( 'rows' => 8, 'new_lines' => '', 'instructions' => $hint ) ),
							$mail( 'ung_vien_tieu_de', 'Thư xác nhận ứng viên — tiêu đề', 'text', 'translate', array( 'instructions' => $hint ) ),
							$mail( 'ung_vien_noi_dung', 'Thư xác nhận ứng viên — nội dung', 'textarea', 'translate', array( 'rows' => 8, 'new_lines' => '', 'instructions' => $hint ) ),
						),
					)
				),
			),
		),
		$common
	);

	return array( $group_lien_he, $group_trang, $group_thu );
}

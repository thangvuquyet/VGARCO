<?php
/**
 * Trường của trang "Cấu hình VGARCO" (mục 6.5 của phương án, yêu cầu 34).
 * Thông tin công ty, liên hệ, chỉ số, đối tác nhập một chỗ — không viết cứng số điện thoại vào mẫu trang.
 *
 * Giá trị mặc định nằm trong vgarco_option_defaults() (inc/helpers.php), không đặt ở đây:
 * ACF không áp giá trị mặc định cho trang cấu hình chưa từng được lưu.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quy tắc vị trí: chỉ hiện ở trang cấu hình VGARCO.
 */
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

/**
 * @return array[] Hai nhóm trường.
 */
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

	/* --- Nhóm 1: pháp nhân, liên hệ, mạng xã hội --- */

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

	/* --- Nhóm 2: chỉ số, đối tác, ảnh mặc định, câu chuyện một công trình --- */

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
					'doi_tac',
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

	return array( $group_lien_he, $group_trang );
}

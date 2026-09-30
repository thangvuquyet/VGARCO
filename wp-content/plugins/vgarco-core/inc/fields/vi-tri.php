<?php
/**
 * Trường của kiểu nội dung "Vị trí tuyển dụng" (mục 6.4 của phương án, UC-07).
 * Nơi làm việc và Phòng ban là hai phân loại riêng (hộp bên phải).
 *
 * Ba danh sách (công việc, yêu cầu, quyền lợi) nhập mỗi dòng một mục: dễ nhập hàng loạt từ bảng tính
 * và không phát sinh trường lặp cần đồng bộ giữa hai ngôn ngữ.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

function vgarco_fields_vi_tri(): array {
	$f    = vgarco_field_builder( 'vi_tri' );
	$list = array(
		'rows'         => 6,
		'instructions' => 'Mỗi dòng một mục.',
	);

	return array(
		'key'                   => 'group_vg_vi_tri',
		'title'                 => 'Thông tin vị trí',
		'fields'                => array(
			$f(
				'loai_hinh',
				'Loại hình',
				'select',
				'copy',
				array(
					'choices'       => array(
						'toan_thoi_gian' => 'Toàn thời gian',
						'ban_thoi_gian'  => 'Bán thời gian',
						'thuc_tap'       => 'Thực tập',
					),
					'default_value' => 'toan_thoi_gian',
					'return_format' => 'value',
				)
			),
			$f(
				'trang_thai',
				'Trạng thái',
				'select',
				'copy',
				array(
					'choices'       => array(
						'dang_nhan' => 'Đang nhận hồ sơ',
						'da_dong'   => 'Đã đóng',
					),
					'default_value' => 'dang_nhan',
					'return_format' => 'value',
					'instructions'  => 'Chọn "Đã đóng" để ngừng nhận hồ sơ ngay, không cần chờ hạn nộp.',
				)
			),
			$f(
				'han_nop',
				'Hạn nộp hồ sơ',
				'date_picker',
				'copy',
				array(
					'display_format' => 'd/m/Y',
					'return_format'  => 'Ymd',
					'first_day'      => 1,
					'instructions'   => 'Để trống nếu nhận đến khi đóng thủ công. Sau ngày này website tự ngừng nhận hồ sơ.',
				)
			),
			$f(
				'tom_tat',
				'Tóm tắt một dòng',
				'textarea',
				'translate',
				array(
					'rows'         => 2,
					'instructions' => 'Hiển thị trên thẻ vị trí và cột tóm tắt.',
				)
			),
			$f( 'mo_ta_chung', 'Mô tả chung', 'textarea', 'translate', array( 'rows' => 4 ) ),
			$f( 'cong_viec', 'Công việc chính', 'textarea', 'translate', $list ),
			$f( 'yeu_cau', 'Yêu cầu', 'textarea', 'translate', $list ),
			$f( 'quyen_loi', 'Quyền lợi', 'textarea', 'translate', $list ),
		),
		'location'              => vgarco_location_post_type( 'vi_tri' ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	);
}

<?php
/**
 * Trường của kiểu nội dung "Lĩnh vực hoạt động" (mục 6.3 của phương án).
 * Đặc tả có 6 nhóm dịch vụ, 3 nhóm hiển thị ở trang chủ.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

function vgarco_fields_linh_vuc(): array {
	$f = vgarco_field_builder( 'linh_vuc' );

	return array(
		'key'                   => 'group_vg_linh_vuc',
		'title'                 => 'Thông tin lĩnh vực',
		'fields'                => array(
			$f(
				'neo',
				'Mã neo',
				'text',
				'copy',
				array(
					'required'     => 1,
					'maxlength'    => 60,
					'instructions' => 'Dùng làm đường dẫn neo trên trang Lĩnh vực hoạt động, ví dụ: thiet-ke-kien-truc. Đổi mã này sẽ làm hỏng các liên kết đã chia sẻ.',
				)
			),
			$f(
				'tom_tat_trang_chu',
				'Dòng tóm tắt ở trang chủ',
				'text',
				'translate',
				array(
					'maxlength'    => 40,
					'instructions' => 'Ví dụ: Ý tưởng · Hồ sơ. Ngắn: bản tiếng Đức dài hơn tiếng Việt khoảng 40%.',
				)
			),
			$f(
				'hien_trang_chu',
				'Hiển thị ở trang chủ',
				'true_false',
				'copy',
				array(
					'ui'            => 1,
					'default_value' => 0,
					'instructions'  => 'Trang chủ hiển thị 3 lĩnh vực đầu theo thứ tự.',
				)
			),
			$f(
				'du_an_tham_khao',
				'Dự án tham khảo',
				'post_object',
				'copy',
				array(
					'post_type'     => array( 'du_an' ),
					'return_format' => 'id',
					'allow_null'    => 1,
					'multiple'      => 0,
					'ui'            => 1,
					'instructions'  => 'Ảnh và tên dự án này hiển thị cạnh mô tả lĩnh vực, ghi rõ là hình ảnh dự án tham khảo (không khẳng định VGARCO thực hiện mọi vai trò tại công trình).',
				)
			),
		),
		'location'              => vgarco_location_post_type( 'linh_vuc' ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	);
}

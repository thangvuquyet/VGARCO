<?php

defined( 'ABSPATH' ) || exit;

function vgarco_fields_tin_tuc(): array {
	$f = vgarco_field_builder( 'tin_tuc' );

	return array(
		'key'                   => 'group_vg_tin_tuc',
		'title'                 => 'Thông tin bài viết',
		'fields'                => array(
			$f(
				'nhan_chu_de',
				'Nhãn chủ đề',
				'text',
				'translate',
				array(
					'maxlength'    => 40,
					'instructions' => 'Nhãn nhỏ viết hoa trên tiêu đề, ví dụ: Góc nhìn chuyên môn.',
				)
			),
			$f(
				'chu_thich_anh',
				'Chú thích ảnh đầu bài',
				'text',
				'translate',
				array( 'instructions' => 'Hiển thị dưới ảnh đầu bài và dùng làm văn bản thay thế (alt).' )
			),
			$f(
				'doan_dan',
				'Đoạn dẫn',
				'textarea',
				'translate',
				array(
					'rows'         => 3,
					'instructions' => 'Đoạn mở đầu in lớn, đặt trước nội dung chính.',
				)
			),
		),
		'location'              => vgarco_location_post_type( 'tin_tuc' ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	);
}

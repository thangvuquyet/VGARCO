<?php

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
defined( 'ROOT' ) || define( 'ROOT', dirname( __DIR__, 2 ) );

function remove_accents( $s ) {
	static $map = null;
	$map ??= array_merge(
		array_combine(
			mb_str_split( 'àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸĐ' ),
			mb_str_split( 'aaaaaaaaaaaaaaaaaeeeeeeeeeeeiiiiiooooooooooooooooouuuuuuuuuuuyyyyydAAAAAAAAAAAAAAAAAEEEEEEEEEEEIIIIIOOOOOOOOOOOOOOOOOUUUUUUUUUUUYYYYYD' )
		),
		array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss' )
	);
	return strtr( (string) $s, $map );
}

$plugin = ROOT . '/wp-content/plugins/vgarco-core/inc/';
require_once $plugin . 'careers/jobs.php';
require_once $plugin . 'search-lib.php';

function fx_search_index( bool $local = false ): array {
	$type_names = array(
		'diplomatic'     => 'Ngoại giao & công quyền',
		'security'       => 'Quân sự & an ninh',
		'education'      => 'Giáo dục & đào tạo',
		'culture'        => 'Văn hóa & di tích',
		'healthcare'     => 'Y tế',
		'infrastructure' => 'Công nghiệp & hạ tầng',
		'residential'    => 'Nhà ở & công trình dân dụng',
	);

	$rows  = json_decode( (string) file_get_contents( __DIR__ . '/data/projects.json' ), true );
	$index = array();
	foreach ( $rows as $row ) {
		$index[] = vgarco_search_prepare(
			array(
				'title'    => $row['title'],
				'location' => $row['location'],
				'years'    => $row['year'],
				'types'    => array( $type_names[ $row['category'] ] ?? '' ),
				'url'      => $local ? '/' . $row['key'] . '/' : 'https://vgarco.vn/du-an/' . $row['key'] . '/',
				'thumb'    => $local ? '/img/' . $row['image'] : 'https://vgarco.vn/x/' . $row['key'] . '-150.jpg',
			)
		);
	}
	return $index;
}

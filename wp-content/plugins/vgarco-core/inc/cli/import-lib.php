<?php
/**
 * Nhập liệu bằng bảng tính (mục 8.7) — phần THUẦN: đọc CSV, kiểm tra từng dòng, dựng "kế hoạch" nhập và so sánh để xem trước (--dry-run).
 * Không gọi WordPress nên kiểm thử được bằng tệp CSV thật (tests/php/import.php). Phần ghi vào WordPress/WPML nằm ở import.php.
 *
 * Quy tắc: cập nhật theo khóa `ma` (chạy lại không tạo bản trùng); mọi lỗi của cả tệp được gom và báo MỘT LƯỢT trước khi ghi bất cứ thứ gì.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * CSV
 * ---------------------------------------------------------------------- */

/**
 * Tự nhận dấu phân cách của dòng tiêu đề (Excel tiếng Việt xuất ";", bản tiếng Anh xuất ","; bảng dán từ web là tab).
 * Đếm bên ngoài dấu nháy; dấu xuất hiện nhiều nhất thắng, hòa thì ưu tiên ";".
 */
function vgarco_csv_detect_delimiter( string $first_line ): string {
	$counts = array( ';' => 0, ',' => 0, "\t" => 0 );
	$quoted = false;
	foreach ( str_split( $first_line ) as $char ) {
		if ( '"' === $char ) {
			$quoted = ! $quoted;
		} elseif ( ! $quoted && isset( $counts[ $char ] ) ) {
			++$counts[ $char ];
		}
	}
	arsort( $counts );
	return array_key_first( $counts ) && reset( $counts ) > 0 ? (string) array_key_first( $counts ) : ';';
}

/**
 * Đọc CSV: ô có dấu nháy kép chứa được dấu phân cách, xuống dòng và "" (nháy kép thoát). Bỏ BOM, chấp nhận CRLF/LF/CR, bỏ dòng trống.
 *
 * @param string      $text      Nội dung (UTF-8).
 * @param string|null $delimiter Null để tự nhận.
 * @return array{headers: string[], rows: array[], lines: int[], errors: string[]} rows: mảng kết hợp theo tiêu đề (đã cắt khoảng trắng);
 *         lines: số dòng gốc của từng hàng (để báo lỗi).
 */
function vgarco_csv_parse( string $text, ?string $delimiter = null ): array {
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text ) ?? '';
	if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
		return array( 'headers' => array(), 'rows' => array(), 'lines' => array(), 'errors' => array( 'Tệp không phải UTF-8. Lưu lại bằng "CSV UTF-8" trong Excel.' ) );
	}
	$delimiter ??= vgarco_csv_detect_delimiter( strtok( $text, "\r\n" ) ?: '' );

	// Tách thành các bản ghi, mỗi bản ghi là mảng ô; theo dõi số dòng bắt đầu.
	$records  = array();
	$record   = array();
	$cell     = '';
	$quoted   = false;
	$line     = 1;
	$start    = 1;
	$length   = strlen( $text );

	for ( $i = 0; $i < $length; $i++ ) {
		$c = $text[ $i ];
		if ( $quoted ) {
			if ( '"' === $c ) {
				if ( '"' === ( $text[ $i + 1 ] ?? '' ) ) {
					$cell .= '"';
					++$i;
				} else {
					$quoted = false;
				}
			} else {
				$cell .= $c;
				if ( "\n" === $c ) {
					++$line;
				}
			}
			continue;
		}
		if ( '"' === $c && '' === $cell ) {
			$quoted = true;
		} elseif ( $delimiter === $c ) {
			$record[] = $cell;
			$cell     = '';
		} elseif ( "\r" === $c || "\n" === $c ) {
			if ( "\r" === $c && "\n" === ( $text[ $i + 1 ] ?? '' ) ) {
				++$i;
			}
			$record[] = $cell;
			if ( array_filter( $record, static fn( string $x ): bool => '' !== trim( $x ) ) ) {
				$records[] = array( $start, $record ); // dòng toàn ô trống (Excel hay xuất thừa ";;;") bị bỏ
			}
			$record = array();
			$cell   = '';
			++$line;
			$start = $line;
		} else {
			$cell .= $c;
		}
	}
	if ( $quoted ) {
		return array( 'headers' => array(), 'rows' => array(), 'lines' => array(), 'errors' => array( "Dấu nháy kép mở ở dòng {$start} không được đóng." ) );
	}
	$record[] = $cell;
	if ( array_filter( $record, static fn( string $x ): bool => '' !== trim( $x ) ) ) {
		$records[] = array( $start, $record );
	}
	if ( ! $records ) {
		return array( 'headers' => array(), 'rows' => array(), 'lines' => array(), 'errors' => array( 'Tệp trống.' ) );
	}

	[ , $head ] = array_shift( $records );
	$headers    = array_map( static fn( string $h ): string => strtolower( trim( $h ) ), $head );
	$errors     = array();
	foreach ( array_count_values( array_filter( $headers, static fn( $h ) => '' !== $h ) ) as $name => $n ) {
		if ( $n > 1 ) {
			$errors[] = "Cột \"{$name}\" xuất hiện {$n} lần ở dòng tiêu đề.";
		}
	}

	$rows  = array();
	$lines = array();
	foreach ( $records as [ $at, $cells ] ) {
		if ( count( $cells ) > count( $headers ) && '' !== trim( implode( '', array_slice( $cells, count( $headers ) ) ) ) ) {
			$errors[] = "Dòng {$at}: có " . count( $cells ) . ' ô nhưng tiêu đề chỉ có ' . count( $headers ) . ' cột (có dấu ; trong nội dung mà chưa đặt trong dấu nháy kép?).';
			continue;
		}
		$assoc = array();
		foreach ( $headers as $index => $name ) {
			if ( '' !== $name ) {
				$assoc[ $name ] = trim( (string) ( $cells[ $index ] ?? '' ) );
			}
		}
		$rows[]  = $assoc;
		$lines[] = $at;
	}
	return array( 'headers' => array_values( array_filter( $headers ) ), 'rows' => $rows, 'lines' => $lines, 'errors' => $errors );
}

/* -------------------------------------------------------------------------
 * Giá trị
 * ---------------------------------------------------------------------- */

/** Đúng/sai trong bảng tính: 1/0, true/false, yes/no, có/không, x. Để trống = 0. Giá trị lạ → null. */
function vgarco_import_bool( string $value ): ?string {
	$v = vgarco_fold( trim( $value ) );
	if ( in_array( $v, array( '', '0', 'false', 'no', 'khong', 'n', 'nein' ), true ) ) {
		return '0';
	}
	if ( in_array( $v, array( '1', 'true', 'yes', 'co', 'x', 'y', 'ja' ), true ) ) {
		return '1';
	}
	return null;
}

/** Quốc gia: nhận mã (vn/la/de) hoặc tên thường gặp. Rỗng → ''. Lạ → null. */
function vgarco_import_country( string $value ): ?string {
	$v = vgarco_fold( trim( $value ) );
	if ( '' === $v ) {
		return '';
	}
	$map = array(
		'vn' => 'vn', 'viet nam' => 'vn', 'vietnam' => 'vn',
		'la' => 'la', 'lao' => 'la', 'laos' => 'la', 'chdcnd lao' => 'la', 'cong hoa dan chu nhan dan lao' => 'la',
		'de' => 'de', 'duc' => 'de', 'germany' => 'de', 'deutschland' => 'de', 'chlb duc' => 'de',
	);
	return $map[ $v ] ?? null;
}

/** Văn bản thuần trong ô → HTML: thoát mọi thẻ, dòng trống tách đoạn <p>, xuống dòng đơn thành <br>. */
function vgarco_import_paragraphs( string $text ): string {
	$text = trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
	if ( '' === $text ) {
		return '';
	}
	$html = '';
	foreach ( preg_split( '/\n{2,}/', $text ) ?: array() as $paragraph ) {
		$html .= '<p>' . str_replace( "\n", '<br>', htmlspecialchars( trim( $paragraph ), ENT_QUOTES, 'UTF-8' ) ) . "</p>\n";
	}
	return rtrim( $html, "\n" );
}

/** Mã hợp lệ: chữ thường không dấu, số, gạch ngang (cùng quy tắc với ô "Mã dự án" trong trang quản trị). */
function vgarco_import_slug_valid( string $slug ): bool {
	return 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
}

/* -------------------------------------------------------------------------
 * Dự án
 * ---------------------------------------------------------------------- */

/** Cột của projects.csv: bắt buộc và tùy chọn. */
function vgarco_import_project_columns(): array {
	return array(
		'required' => array( 'ma', 'ten_vi' ),
		'optional' => array(
			'thu_tu', 'ten_de', 'slug_vi', 'slug_de', 'loai', 'quoc_gia', 'dia_diem_vi', 'dia_diem_de', 'thoi_gian', 'vai_tro_vi', 'vai_tro_de',
			'pham_vi_vi', 'pham_vi_de', 'chu_dau_tu_vi', 'chu_dau_tu_de', 'doi_tac', 'doi_tac_vi', 'doi_tac_de', 'cong_bo_chu_dau_tu', 'mo_ta_vi', 'mo_ta_de',
			'loai_anh_dai_dien', 'noi_bat', 'tieu_bieu', 'mo_ta_seo_vi', 'mo_ta_seo_de',
		),
	);
}

/**
 * Kiểm tra dòng tiêu đề của projects.csv.
 *
 * @param string[] $headers
 * @return string[] Lỗi.
 */
function vgarco_import_project_header_errors( array $headers ): array {
	$cols   = vgarco_import_project_columns();
	$errors = array();
	foreach ( array_diff( $cols['required'], $headers ) as $missing ) {
		$errors[] = "Thiếu cột bắt buộc \"{$missing}\".";
	}
	foreach ( array_diff( $headers, $cols['required'], $cols['optional'] ) as $unknown ) {
		$errors[] = "Cột lạ \"{$unknown}\" (gõ sai tên cột?). Cột hợp lệ: " . implode( ', ', array_merge( $cols['required'], $cols['optional'] ) ) . '.';
	}
	return $errors;
}

/**
 * Chuyển một dòng projects.csv thành kế hoạch nhập, kèm mọi lỗi của dòng (không dừng ở lỗi đầu tiên).
 *
 * @param array<string,string> $row
 * @param array{terms: array<string,string>} $allowed terms: slug => tên của các loại công trình hợp lệ.
 * @return array{plan: ?array, errors: string[]}
 */
function vgarco_import_project_plan( array $row, int $line, array $allowed ): array {
	$errors = array();
	$e      = static function ( string $msg ) use ( &$errors, $line ): void {
		$errors[] = "Dòng {$line}: {$msg}";
	};
	$get = static fn( string $k ): string => trim( (string) ( $row[ $k ] ?? '' ) );

	$ma = $get( 'ma' );
	if ( '' === $ma ) {
		$e( 'thiếu "ma".' );
	} elseif ( ! vgarco_import_slug_valid( $ma ) ) {
		$e( "\"ma\" = \"{$ma}\" không hợp lệ: chỉ chữ thường không dấu, chữ số, gạch ngang (ví dụ bao-tang-qdnd-lao)." );
	}
	if ( '' === $get( 'ten_vi' ) ) {
		$e( 'thiếu "ten_vi".' );
	}

	$order = $get( 'thu_tu' );
	if ( '' !== $order && ! ctype_digit( $order ) ) {
		$e( "\"thu_tu\" = \"{$order}\" phải là số nguyên không âm." );
	}

	foreach ( array( 'slug_vi', 'slug_de' ) as $k ) {
		if ( '' !== $get( $k ) && ! vgarco_import_slug_valid( $get( $k ) ) ) {
			$e( "\"{$k}\" = \"{$get( $k )}\" không hợp lệ (chữ thường không dấu, chữ số, gạch ngang)." );
		}
	}
	if ( '' === $get( 'ten_de' ) ) {
		foreach ( array( 'slug_de', 'dia_diem_de', 'vai_tro_de', 'pham_vi_de', 'chu_dau_tu_de', 'doi_tac_de', 'mo_ta_de', 'mo_ta_seo_de' ) as $k ) {
			if ( '' !== $get( $k ) ) {
				$e( "có \"{$k}\" nhưng thiếu \"ten_de\" — không tạo được bản tiếng Đức."  );
				break;
			}
		}
	}

	$country = vgarco_import_country( $get( 'quoc_gia' ) );
	if ( null === $country ) {
		$e( "\"quoc_gia\" = \"{$get( 'quoc_gia' )}\" không nhận ra. Dùng vn, la hoặc de (hoặc Việt Nam, Lào, Đức)." );
	}

	$bools = array();
	foreach ( array( 'cong_bo_chu_dau_tu', 'noi_bat', 'tieu_bieu' ) as $k ) {
		$b = vgarco_import_bool( $get( $k ) );
		if ( null === $b ) {
			$e( "\"{$k}\" = \"{$get( $k )}\" không phải đúng/sai. Dùng 1/0, có/không, true/false." );
		}
		$bools[ $k ] = $b ?? '0';
	}

	$kind = $get( 'loai_anh_dai_dien' ) ?: 'thiet_ke';
	if ( ! in_array( $kind, array( 'thiet_ke', 'cong_trinh' ), true ) ) {
		$e( "\"loai_anh_dai_dien\" = \"{$kind}\" phải là thiet_ke hoặc cong_trinh." );
	}

	$years = $get( 'thoi_gian' );
	if ( mb_strlen( $years ) > 20 ) {
		$e( '"thoi_gian" dài quá 20 ký tự.' );
	}
	foreach ( array( 'mo_ta_seo_vi', 'mo_ta_seo_de' ) as $k ) {
		if ( mb_strlen( $get( $k ) ) > 160 ) {
			$e( "\"{$k}\" dài " . mb_strlen( $get( $k ) ) . ' ký tự, tối đa 160.' );
		}
	}

	// Loại công trình: nhiều giá trị ngăn cách bằng | hoặc dấu phẩy; nhận slug hoặc tên.
	$terms  = array();
	$by_key = array();
	foreach ( $allowed['terms'] ?? array() as $slug => $name ) {
		$by_key[ vgarco_fold( (string) $slug ) ] = (string) $slug;
		$by_key[ vgarco_fold( (string) $name ) ] = (string) $slug;
	}
	foreach ( preg_split( '/[|,]/', $get( 'loai' ) ) ?: array() as $raw ) {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			continue;
		}
		$slug = $by_key[ vgarco_fold( $raw ) ] ?? null;
		if ( null === $slug ) {
			$e( "\"loai\" có giá trị không biết: \"{$raw}\". Hợp lệ: " . implode( ', ', array_keys( $allowed['terms'] ?? array() ) ) . '.' );
		} elseif ( ! in_array( $slug, $terms, true ) ) {
			$terms[] = $slug;
		}
	}

	if ( $errors ) {
		return array( 'plan' => null, 'errors' => $errors );
	}

	$lang = static fn( string $l ): array => array(
		'title'   => $get( "ten_{$l}" ),
		'slug'    => $get( "slug_{$l}" ),
		'content' => vgarco_import_paragraphs( $get( "mo_ta_{$l}" ) ),
		'meta'    => array(
			'dia_diem'  => $get( "dia_diem_{$l}" ),
			'vai_tro'   => $get( "vai_tro_{$l}" ),
			'pham_vi'   => $get( "pham_vi_{$l}" ),
			'chu_dau_tu' => $get( "chu_dau_tu_{$l}" ),
			'doi_tac'   => $get( "doi_tac_{$l}" ) ?: $get( 'doi_tac' ), // tên đối tác là danh từ riêng: một cột chung là đủ
			'mo_ta_seo' => $get( "mo_ta_seo_{$l}" ),
		),
	);

	return array(
		'plan'   => array(
			'ma'     => $ma,
			'order'  => '' === $order ? 0 : (int) $order,
			'vi'     => $lang( 'vi' ),
			'de'     => '' === $get( 'ten_de' ) ? null : $lang( 'de' ),
			'shared' => array(
				'thoi_gian'          => $years,
				'quoc_gia'           => (string) $country,
				'cong_bo_chu_dau_tu' => $bools['cong_bo_chu_dau_tu'],
				'loai_anh_dai_dien'  => $kind,
				'noi_bat'            => $bools['noi_bat'],
				'tieu_bieu'          => $bools['tieu_bieu'],
			),
			'terms'  => $terms,
		),
		'errors' => array(),
	);
}

/**
 * Đọc cả tệp projects.csv: kiểm tra tiêu đề, từng dòng, trùng `ma`, vượt giới hạn trang chủ (5 nổi bật, 3 tiêu biểu).
 *
 * @return array{plans: array[], errors: string[]} Có lỗi thì plans rỗng (không ghi gì cả khi tệp còn lỗi).
 */
function vgarco_import_projects( string $csv, array $allowed ): array {
	$parsed = vgarco_csv_parse( $csv );
	$errors = $parsed['errors'];
	if ( $parsed['headers'] ) {
		$errors = array_merge( $errors, vgarco_import_project_header_errors( $parsed['headers'] ) );
	}
	if ( $errors ) {
		return array( 'plans' => array(), 'errors' => $errors );
	}

	$plans = array();
	$seen  = array();
	foreach ( $parsed['rows'] as $index => $row ) {
		$line   = $parsed['lines'][ $index ];
		$result = vgarco_import_project_plan( $row, $line, $allowed );
		$errors = array_merge( $errors, $result['errors'] );
		if ( $result['plan'] ) {
			$ma = $result['plan']['ma'];
			if ( isset( $seen[ $ma ] ) ) {
				$errors[] = "Dòng {$line}: \"ma\" = \"{$ma}\" trùng với dòng {$seen[ $ma ]}.";
			}
			$seen[ $ma ] = $line;
			$plans[]     = $result['plan'];
		}
	}

	foreach ( array( 'noi_bat' => 5, 'tieu_bieu' => 3 ) as $flag => $max ) {
		$count = count( array_filter( $plans, static fn( array $p ): bool => '1' === $p['shared'][ $flag ] ) );
		if ( $count > $max ) {
			$errors[] = "Có {$count} dự án đánh dấu \"{$flag}\" nhưng trang chủ chỉ hiển thị tối đa {$max}.";
		}
	}
	if ( ! $plans && ! $errors ) {
		$errors[] = 'Tệp không có dòng dữ liệu nào.';
	}
	return $errors ? array( 'plans' => array(), 'errors' => $errors ) : array( 'plans' => $plans, 'errors' => array() );
}

/**
 * Các trường của một ngôn ngữ trong kế hoạch, dạng phẳng để so sánh với bài đang có.
 *
 * @param string $lang vi | de
 * @return array<string,string>
 */
function vgarco_import_flatten( array $plan, string $lang ): array {
	$part = $plan[ $lang ] ?? null;
	if ( ! $part ) {
		return array();
	}
	$flat = array(
		'post_title'   => $part['title'],
		'post_name'    => $part['slug'],
		'post_content' => $part['content'],
		'menu_order'   => (string) $plan['order'],
	);
	foreach ( $part['meta'] as $key => $value ) {
		$flat[ 'meta.' . $key ] = $value;
	}
	foreach ( $plan['shared'] as $key => $value ) {
		$flat[ 'meta.' . $key ] = $value;
	}
	$flat['terms.loai_cong_trinh'] = implode( ',', $plan['terms'] );
	return $flat;
}

/**
 * So sánh để xem trước: chỉ các trường KHÁC. Ô trống trong bảng không xóa dữ liệu đã có (biên tập viên có thể đã nhập tay):
 * trường có giá trị mới rỗng bị bỏ qua.
 *
 * @param array<string,string> $current Giá trị hiện có (cùng khóa với vgarco_import_flatten). Rỗng = chưa có bài.
 * @param array<string,string> $planned
 * @return array<string,array{0:string,1:string}> khóa → [cũ, mới]
 */
function vgarco_import_diff( array $current, array $planned ): array {
	$diff = array();
	foreach ( $planned as $key => $new ) {
		$new = trim( (string) $new );
		$old = trim( (string) ( $current[ $key ] ?? '' ) );
		if ( '' === $new && '' !== $old && 'menu_order' !== $key ) {
			continue; // không xóa dữ liệu đã có bằng ô trống
		}
		if ( $old !== $new ) {
			$diff[ $key ] = array( $old, $new );
		}
	}
	return $diff;
}

/* -------------------------------------------------------------------------
 * Ảnh
 * ---------------------------------------------------------------------- */

/** Vai trò của ảnh: ô nào của dự án. */
function vgarco_import_image_roles(): array {
	return array(
		'hero'  => 'anh_dau_trang',
		'the'   => 'anh_the',
		'slide' => 'anh_trinh_chieu',
		'tv'    => 'thu_vien',
	);
}

/**
 * Đường dẫn tệp ảnh trong thư mục --images: tương đối, không "..", không ổ đĩa, không thoát thư mục; chỉ jpg/jpeg/png/webp.
 */
function vgarco_import_image_path_valid( string $path ): bool {
	if ( '' === $path || str_contains( $path, '..' ) || str_contains( $path, '\\' ) || str_starts_with( $path, '/' ) || preg_match( '/^[a-z]:/i', $path ) || str_contains( $path, "\0" ) ) {
		return false;
	}
	return 1 === preg_match( '/\.(jpe?g|png|webp)$/i', $path );
}

/**
 * Đọc images.csv thành ảnh theo từng dự án.
 *
 * @param string   $csv
 * @param string[] $known_codes Các `ma` hợp lệ (từ projects.csv hoặc đã có trong CSDL).
 * @return array{images: array<string,array>, errors: string[]} images[ma] = [hero, the, slide (đường dẫn hoặc ''), tv => [file, kind, caption_vi, caption_de]].
 */
function vgarco_import_images( string $csv, array $known_codes ): array {
	$parsed = vgarco_csv_parse( $csv );
	$errors = $parsed['errors'];
	$need   = array( 'ma', 'tep', 'vai_tro' );
	$opt    = array( 'thu_tu', 'loai_anh', 'chu_thich_vi', 'chu_thich_de' );
	if ( $parsed['headers'] ) {
		foreach ( array_diff( $need, $parsed['headers'] ) as $m ) {
			$errors[] = "images.csv thiếu cột bắt buộc \"{$m}\".";
		}
		foreach ( array_diff( $parsed['headers'], $need, $opt ) as $u ) {
			$errors[] = "images.csv có cột lạ \"{$u}\".";
		}
	}
	if ( $errors ) {
		return array( 'images' => array(), 'errors' => $errors );
	}

	$images = array();
	foreach ( $parsed['rows'] as $index => $row ) {
		$line = $parsed['lines'][ $index ];
		$ma   = trim( $row['ma'] ?? '' );
		$file = trim( $row['tep'] ?? '' );
		$role = strtolower( trim( $row['vai_tro'] ?? '' ) );
		$kind = trim( $row['loai_anh'] ?? '' ) ?: 'thiet_ke';
		$ord  = trim( $row['thu_tu'] ?? '' );

		if ( ! in_array( $ma, $known_codes, true ) ) {
			$errors[] = "images.csv dòng {$line}: \"ma\" = \"{$ma}\" không có trong danh sách dự án.";
			continue;
		}
		if ( ! vgarco_import_image_path_valid( $file ) ) {
			$errors[] = "images.csv dòng {$line}: \"tep\" = \"{$file}\" không hợp lệ (đường dẫn tương đối, không \"..\", đuôi jpg/jpeg/png/webp).";
		}
		if ( ! isset( vgarco_import_image_roles()[ $role ] ) ) {
			$errors[] = "images.csv dòng {$line}: \"vai_tro\" = \"{$role}\" phải là hero, the, slide hoặc tv.";
			continue;
		}
		if ( ! in_array( $kind, array( 'thiet_ke', 'cong_trinh' ), true ) ) {
			$errors[] = "images.csv dòng {$line}: \"loai_anh\" = \"{$kind}\" phải là thiet_ke hoặc cong_trinh.";
		}
		if ( '' !== $ord && ! ctype_digit( $ord ) ) {
			$errors[] = "images.csv dòng {$line}: \"thu_tu\" = \"{$ord}\" phải là số nguyên không âm.";
		}
		if ( 'tv' === $role && '' === trim( $row['chu_thich_vi'] ?? '' ) ) {
			$errors[] = "images.csv dòng {$line}: ảnh thư viện cần \"chu_thich_vi\" (dùng làm văn bản thay thế cho người khiếm thị).";
		}

		$images[ $ma ] ??= array( 'hero' => '', 'the' => '', 'slide' => '', 'tv' => array() );
		if ( 'tv' === $role ) {
			$images[ $ma ]['tv'][] = array(
				'file'       => $file,
				'kind'       => $kind,
				'caption_vi' => trim( $row['chu_thich_vi'] ?? '' ),
				'caption_de' => trim( $row['chu_thich_de'] ?? '' ),
				'order'      => '' === $ord ? PHP_INT_MAX : (int) $ord,
				'line'       => $line,
			);
		} elseif ( '' !== $images[ $ma ][ $role ] ) {
			$errors[] = "images.csv dòng {$line}: dự án \"{$ma}\" đã có ảnh vai trò \"{$role}\" ({$images[ $ma ][ $role ]}).";
		} else {
			$images[ $ma ][ $role ] = $file;
		}
	}

	foreach ( $images as $ma => &$set ) {
		if ( count( $set['tv'] ) > 15 ) {
			$errors[] = "Dự án \"{$ma}\" có " . count( $set['tv'] ) . ' ảnh thư viện, tối đa 15.';
		}
		// Sắp theo thu_tu; ô trống đứng cuối; cùng thứ tự giữ thứ tự dòng.
		usort( $set['tv'], static fn( array $a, array $b ): int => array( $a['order'], $a['line'] ) <=> array( $b['order'], $b['line'] ) );
		$set['tv'] = array_map( static fn( array $i ): array => array_diff_key( $i, array( 'order' => 1, 'line' => 1 ) ), $set['tv'] );
	}
	unset( $set );

	return $errors ? array( 'images' => array(), 'errors' => $errors ) : array( 'images' => $images, 'errors' => array() );
}

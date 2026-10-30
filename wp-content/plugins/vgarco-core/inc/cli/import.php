<?php

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once VGARCO_CORE_DIR . 'inc/cli/import-lib.php';

const VGARCO_IMPORT_MAX_IMAGE_BYTES = 15 * 1024 * 1024;

class VGARCO_CLI_Command {
	public function languages(): void {
		$result = vgarco_setup_languages();
		foreach ( $result['log'] as $line ) {
			WP_CLI::log( $line );
		}
		if ( ! $result['ok'] ) {
			WP_CLI::error( 'Chưa cấu hình xong ngôn ngữ.' );
		}
		WP_CLI::success( 'Ngôn ngữ: tiếng Việt (mặc định, không có tiền tố) và tiếng Đức (/de/).' );
	}

	public function import( array $args, array $assoc ): void {
		[ $entity, $file ] = array_pad( $args, 2, '' );
		if ( 'projects' !== $entity ) {
			WP_CLI::error( 'Chỉ hỗ trợ: projects.' );
		}
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( "Không đọc được tệp: {$file}" );
		}
		$lang    = (string) ( $assoc['lang'] ?? '' );
		$dry     = isset( $assoc['dry-run'] );
		$status  = 'publish' === ( $assoc['status'] ?? 'draft' ) ? 'publish' : 'draft';
		if ( '' !== $lang && ! in_array( $lang, array( 'vi', 'de' ), true ) ) {
			WP_CLI::error( '--lang chỉ nhận vi hoặc de.' );
		}
		$has_de = vgarco_language_available( 'de' );
		if ( 'de' === $lang && ! $has_de ) {
			WP_CLI::error( 'Chưa bật Polylang với tiếng Đức: chạy wp vgarco languages trước.' );
		}

		$result = vgarco_import_projects( (string) file_get_contents( $file ), array( 'terms' => vgarco_seed_term_map()['loai_cong_trinh'] ) );
		$images = $this->read_images( $file, $assoc, array_column( $result['plans'], 'ma' ), $result['errors'] );
		if ( $result['errors'] ) {
			WP_CLI::error_multi_line( $result['errors'] );
			WP_CLI::error( 'Tệp còn lỗi — KHÔNG ghi gì cả. Sửa hết rồi chạy lại.' );
		}

		$counts = array( 'created' => 0, 'updated' => 0, 'unchanged' => 0 );
		kses_remove_filters();
		foreach ( $result['plans'] as $plan ) {
			$vi_id = $this->find( $plan['ma'], 'vi' );

			if ( '' === $lang || 'vi' === $lang ) {
				$vi_id = $this->upsert( $plan, 'vi', $vi_id, 0, $status, $dry, $counts );
			}
			if ( ( '' === $lang || 'de' === $lang ) && $plan['de'] ) {
				if ( ! $has_de ) {
					WP_CLI::warning( "{$plan['ma']}: bỏ qua bản tiếng Đức vì Polylang chưa bật." );
				} elseif ( ! $vi_id && ! $dry ) {
					WP_CLI::warning( "{$plan['ma']}: chưa có bản tiếng Việt nên bỏ qua bản tiếng Đức." );
				} else {
					$this->upsert( $plan, 'de', $this->find( $plan['ma'], 'de' ), (int) $vi_id, $status, $dry, $counts );
				}
			}

			if ( $vi_id && ! $dry && isset( $images[ $plan['ma'] ] ) ) {
				$this->apply_images( $plan['ma'], $images[ $plan['ma'] ], (int) $vi_id, (string) ( $assoc['images'] ?? '' ) );
			}
		}

		WP_CLI::success( sprintf( '%s: %d tạo mới, %d cập nhật, %d không đổi.', $dry ? 'Xem trước' : 'Xong', $counts['created'], $counts['updated'], $counts['unchanged'] ) );
	}

	public function report( array $args, array $assoc ): void {
		if ( 'images' === ( $args[0] ?? '' ) ) {
			$this->report_images();
			return;
		}
		if ( 'translations' !== ( $args[0] ?? '' ) ) {
			WP_CLI::error( 'Chỉ hỗ trợ: translations, images.' );
		}
		$lang = (string) ( $assoc['lang'] ?? 'de' );
		$rows = array();
		foreach ( array( 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri', 'page' ) as $type ) {
			foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ) as $id ) {
				if ( 'vi' !== vgarco_post_language( (int) $id ) ) {
					continue;
				}
				if ( ! vgarco_translation_id( (int) $id, $lang ) ) {
					$rows[] = array( 'ID' => $id, 'Loại' => $type, 'Tiêu đề' => get_the_title( $id ) );
				}
			}
		}
		if ( ! $rows ) {
			WP_CLI::success( "Mọi nội dung đã có bản {$lang}." );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'Loại', 'Tiêu đề' ) );
		WP_CLI::warning( count( $rows ) . " mục chưa có bản {$lang}." );
	}

	private function report_images(): void {
		$rows = array();
		$over = 0;
		foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_vgarco_avif_quality' ) ) as $id ) {
			$m = get_post_meta( $id, '_vgarco_avif_quality', true );
			if ( ! is_array( $m ) || ( (int) ( $m['quality'] ?? VGARCO_AVIF_QUALITY ) >= VGARCO_AVIF_QUALITY && false !== ( $m['fits'] ?? null ) ) ) {
				continue;
			}
			$still = false === ( $m['fits'] ?? null );
			$over += $still ? 1 : 0;
			$rows[] = array(
				'ID' => $id,
				'Tệp' => wp_basename( (string) get_attached_file( $id ) ),
				'Chất lượng' => (int) $m['quality'],
				'KB' => isset( $m['bytes'] ) ? round( $m['bytes'] / 1024 ) : '?',
				'Trần KB' => round( ( (int) ( $m['ceiling'] ?? 0 ) ) / 1024 ),
				'Kết quả' => $still ? 'VẪN VƯỢT TRẦN — chọn lại ảnh' : 'đã hạ để vừa trần',
			);
		}
		if ( ! $rows ) {
			WP_CLI::success( 'Không có ảnh nào bị hạ chất lượng AVIF hoặc vượt trần.' );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'Tệp', 'Chất lượng', 'KB', 'Trần KB', 'Kết quả' ) );
		if ( $over ) {
			WP_CLI::warning( "{$over} ảnh vẫn vượt trần ở chất lượng thấp nhất: nên chọn lại ảnh hoặc xin chủ đầu tư nới trần cho ảnh đó." );
		}
	}

	private function read_images( string $file, array $assoc, array $codes, array &$errors ): array {
		$dir = (string) ( $assoc['images'] ?? '' );
		$csv = (string) ( $assoc['images-csv'] ?? dirname( $file ) . '/images.csv' );
		if ( '' === $dir || ! is_readable( $csv ) ) {
			return array();
		}
		if ( ! is_dir( $dir ) ) {
			$errors[] = "Không có thư mục ảnh: {$dir}";
			return array();
		}
		$parsed = vgarco_import_images( (string) file_get_contents( $csv ), $codes );
		$errors = array_merge( $errors, $parsed['errors'] );

		foreach ( $parsed['images'] as $ma => $set ) {
			$files = array_filter( array( $set['hero'], $set['the'], $set['slide'] ) );
			foreach ( $set['tv'] as $tv ) {
				$files[] = $tv['file'];
			}
			foreach ( $files as $relative ) {
				$path = rtrim( $dir, '/\\' ) . '/' . $relative;
				if ( ! is_file( $path ) ) {
					$errors[] = "Dự án \"{$ma}\": không thấy tệp ảnh {$relative} trong {$dir}.";
				} elseif ( filesize( $path ) > VGARCO_IMPORT_MAX_IMAGE_BYTES ) {
					$errors[] = "Dự án \"{$ma}\": {$relative} nặng hơn 15 MB — xuất sang JPEG trước (mục 9.1).";
				}
			}
		}
		return $parsed['images'];
	}

	private function find( string $ma, string $lang ): int {
		$ids = get_posts(
			array(
				'post_type'        => 'du_an',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => 'ma',
				'meta_value'       => $ma,
			)
		);
		foreach ( $ids as $id ) {
			$code = vgarco_post_language( (int) $id );
			if ( null === $code || $lang === $code ) {
				return (int) $id;
			}
		}
		return 0;
	}

	private function current( int $post_id ): array {
		if ( ! $post_id ) {
			return array();
		}
		$post = get_post( $post_id );
		$flat = array(
			'post_title'   => (string) $post->post_title,
			'post_name'    => (string) $post->post_name,
			'post_content' => (string) $post->post_content,
			'menu_order'   => (string) $post->menu_order,
		);
		foreach ( array( 'dia_diem', 'vai_tro', 'pham_vi', 'chu_dau_tu', 'doi_tac', 'mo_ta_seo', 'thoi_gian', 'quoc_gia', 'cong_bo_chu_dau_tu', 'loai_anh_dai_dien', 'noi_bat', 'tieu_bieu' ) as $key ) {
			$value = get_field( $key, $post_id );
			$is_flag                = in_array( $key, array( 'cong_bo_chu_dau_tu', 'noi_bat', 'tieu_bieu' ), true );
			$flat[ 'meta.' . $key ] = $is_flag ? ( $value && '0' !== $value ? '1' : '0' ) : (string) $value;
		}
		$flat['terms.loai_cong_trinh'] = implode( ',', vgarco_term_slugs_in_default_language( $post_id, 'loai_cong_trinh' ) );
		return $flat;
	}

	private function upsert( array $plan, string $lang, int $post_id, int $source_id, string $status, bool $dry, array &$counts ): int {
		$planned = vgarco_import_flatten( $plan, $lang );
		$terms   = vgarco_term_ids_in_language( $plan['terms'], 'loai_cong_trinh', $lang );
		$label   = "{$plan['ma']} [{$lang}]";
		if ( $terms['missing'] ) {
			$kept = array_values( array_diff( $plan['terms'], $terms['missing'] ) );
			sort( $kept );
			$planned['terms.loai_cong_trinh'] = implode( ',', $kept );
			WP_CLI::warning( "{$label}: loại công trình chưa có bản dịch, chưa gắn: " . implode( ', ', $terms['missing'] ) );
		}
		$diff = vgarco_import_diff( $this->current( $post_id ), $planned );

		if ( ! $diff ) {
			++$counts['unchanged'];
			WP_CLI::log( "= {$label}: không đổi" );
			return $post_id;
		}

		WP_CLI::log( ( $post_id ? '~ ' : '+ ' ) . $label . ( $post_id ? " (ID {$post_id})" : ' (mới)' ) );
		foreach ( $diff as $field => [ $old, $new ] ) {
			WP_CLI::log( sprintf( '    %s: %s → %s', $field, '' === $old ? '(trống)' : mb_strimwidth( $old, 0, 40, '…' ), mb_strimwidth( $new, 0, 60, '…' ) ) );
		}
		$counts[ $post_id ? 'updated' : 'created' ]++;
		if ( $dry ) {
			return $post_id;
		}

		do_action( 'wpml_switch_language', $lang );
		$args = array(
			'post_type'    => 'du_an',
			'post_title'   => $planned['post_title'],
			'post_content' => $planned['post_content'],
			'menu_order'   => (int) $planned['menu_order'],
		);
		if ( '' !== $planned['post_name'] ) {
			$args['post_name'] = $planned['post_name'];
		}
		if ( $post_id ) {
			$args['ID'] = $post_id;
			$result     = wp_update_post( $args, true );
		} else {
			$args['post_status'] = $status;
			$result              = wp_insert_post( $args, true );
		}
		if ( is_wp_error( $result ) ) {
			do_action( 'wpml_switch_language', null );
			WP_CLI::error( "{$label}: {$result->get_error_message()}" );
		}
		$post_id = (int) $result;

		vgarco_set_post_language( $post_id, $lang );
		if ( 'de' === $lang && $source_id ) {
			vgarco_link_translation( $source_id, $post_id, $lang );
		}

		update_field( 'ma', $plan['ma'], $post_id );
		foreach ( $planned as $key => $value ) {
			if ( str_starts_with( $key, 'meta.' ) && '' !== $value ) {
				$is_flag = in_array( $key, array( 'meta.cong_bo_chu_dau_tu', 'meta.noi_bat', 'meta.tieu_bieu' ), true );
				update_field( substr( $key, 5 ), $is_flag ? '1' === $value : $value, $post_id );
			}
		}
		if ( $terms['ids'] ) {
			wp_set_object_terms( $post_id, $terms['ids'], 'loai_cong_trinh' );
		}
		do_action( 'wpml_switch_language', null );
		return $post_id;
	}

	private function apply_images( string $ma, array $set, int $vi_id, string $dir ): void {
		$ids = array();
		foreach ( array( 'hero' => 'anh_dau_trang', 'the' => 'anh_the', 'slide' => 'anh_trinh_chieu' ) as $role => $field ) {
			if ( '' !== $set[ $role ] ) {
				$ids[ $role ] = $this->sideload( rtrim( $dir, '/\\' ) . '/' . $set[ $role ], $vi_id, '' );
				update_field( $field, $ids[ $role ], $vi_id );
			}
		}

		$rows_vi = array();
		$rows_de = array();
		foreach ( $set['tv'] as $tv ) {
			$attachment = $this->sideload( rtrim( $dir, '/\\' ) . '/' . $tv['file'], $vi_id, $tv['caption_vi'] );
			$rows_vi[]  = array( 'anh' => $attachment, 'loai_anh' => $tv['kind'], 'chu_thich' => $tv['caption_vi'] );
			$rows_de[]  = array( 'anh' => $attachment, 'loai_anh' => $tv['kind'], 'chu_thich' => '' !== $tv['caption_de'] ? $tv['caption_de'] : $tv['caption_vi'] );
		}
		if ( $rows_vi ) {
			update_field( 'thu_vien', $rows_vi, $vi_id );
		}

		$de_id = vgarco_translation_id( $vi_id, 'de' );
		if ( $de_id && $de_id !== $vi_id ) {
			foreach ( array( 'hero' => 'anh_dau_trang', 'the' => 'anh_the', 'slide' => 'anh_trinh_chieu' ) as $role => $field ) {
				if ( isset( $ids[ $role ] ) ) {
					update_field( $field, $ids[ $role ], $de_id );
				}
			}
			if ( $rows_de ) {
				update_field( 'thu_vien', $rows_de, $de_id );
			}
		}
		WP_CLI::log( "  ảnh {$ma}: " . count( $ids ) . ' ảnh chính, ' . count( $rows_vi ) . ' ảnh thư viện' );
	}

	private function sideload( string $path, int $post_id, string $alt ): int {
		$sha   = (string) sha1_file( $path );
		$found = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => '_vg_import_sha1',
				'meta_value'       => $sha,
			)
		);
		if ( $found ) {
			return (int) $found[0];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( basename( $path ) );
		copy( $path, $tmp );
		$id = media_handle_sideload( array( 'name' => basename( $path ), 'tmp_name' => $tmp ), $post_id );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			WP_CLI::error( 'Không nạp được ảnh ' . basename( $path ) . ': ' . $id->get_error_message() );
		}
		update_post_meta( $id, '_vg_import_sha1', $sha );
		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		return (int) $id;
	}
}

WP_CLI::add_command( 'vgarco', 'VGARCO_CLI_Command' );

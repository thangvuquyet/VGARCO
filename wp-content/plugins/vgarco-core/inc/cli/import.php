<?php
/**
 * Lệnh WP-CLI nhập liệu bằng bảng tính (mục 8.7 của phương án):
 *
 *   wp vgarco import projects content/projects.csv --images=content/images --dry-run   # xem trước thay đổi
 *   wp vgarco import projects content/projects.csv --images=content/images
 *   wp vgarco import projects content/projects.csv --lang=de                            # chỉ cột tiếng Đức (bản VI đã có)
 *   wp vgarco report translations --lang=de                                             # liệt kê nội dung chưa dịch
 *
 * Toàn bộ kiểm tra dữ liệu nằm ở import-lib.php (có kiểm thử bằng tệp CSV thật). Tệp này chỉ ghi vào WordPress/WPML/ACF.
 * CHƯA CHẠY TRÊN WORDPRESS THẬT: kiểm ở spike WPML (phương án mục 6.7) trước khi dùng cho nội dung thật.
 *
 * Quy tắc: cập nhật theo khóa `ma` (chạy lại không tạo bản trùng); bài mới ở trạng thái nháp trừ khi có --status=publish;
 * ô trống trong bảng không xóa dữ liệu đã nhập tay; ảnh nạp một lần (so khớp SHA-1), bản DE dùng lại ảnh của bản VI.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once VGARCO_CORE_DIR . 'inc/cli/import-lib.php';

/** Ảnh tối đa (byte): PNG render 20–30 MB phải đổi sang JPEG trước khi nhập (mục 9.1). */
const VGARCO_IMPORT_MAX_IMAGE_BYTES = 15 * 1024 * 1024;

/**
 * Quản lý nội dung VGARCO từ dòng lệnh.
 */
class VGARCO_CLI_Command {

	/**
	 * Nhập dữ liệu từ tệp CSV.
	 *
	 * ## OPTIONS
	 *
	 * <entity>
	 * : Loại dữ liệu. Hiện hỗ trợ: projects.
	 *
	 * <file>
	 * : Đường dẫn tệp CSV (UTF-8, phân cách ; , hoặc tab).
	 *
	 * [--images=<dir>]
	 * : Thư mục chứa ảnh; đọc images.csv cạnh tệp CSV (hoặc --images-csv) để biết ảnh nào của dự án nào.
	 *
	 * [--images-csv=<file>]
	 * : Đường dẫn images.csv nếu không nằm cạnh tệp CSV dự án.
	 *
	 * [--lang=<lang>]
	 * : vi = chỉ bản tiếng Việt; de = chỉ bản tiếng Đức (bản VI phải có sẵn). Bỏ trống = cả hai.
	 *
	 * [--status=<status>]
	 * : Trạng thái của bài MỚI: draft (mặc định) hoặc publish.
	 *
	 * [--dry-run]
	 * : Chỉ in thay đổi, không ghi gì.
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args  entity, file.
	 * @param array<string,string> $assoc Tùy chọn.
	 */
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

		$result = vgarco_import_projects( (string) file_get_contents( $file ), array( 'terms' => vgarco_seed_term_map()['loai_cong_trinh'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$images = $this->read_images( $file, $assoc, array_column( $result['plans'], 'ma' ), $result['errors'] );
		if ( $result['errors'] ) {
			WP_CLI::error_multi_line( $result['errors'] );
			WP_CLI::error( 'Tệp còn lỗi — KHÔNG ghi gì cả. Sửa hết rồi chạy lại.' );
		}

		$counts = array( 'created' => 0, 'updated' => 0, 'unchanged' => 0 );
		foreach ( $result['plans'] as $plan ) {
			$vi_id = $this->find( $plan['ma'], 'vi' );

			if ( '' === $lang || 'vi' === $lang ) {
				$vi_id = $this->upsert( $plan, 'vi', $vi_id, 0, $status, $dry, $counts );
			}
			// Bản tiếng Đức chỉ khi có tên tiếng Đức. KHÔNG dùng continue ở đây: phần ảnh bên dưới phải chạy cho cả dự án chưa có bản Đức.
			if ( ( '' === $lang || 'de' === $lang ) && $plan['de'] ) {
				if ( ! $vi_id && ! $dry ) {
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

	/**
	 * Báo cáo nội dung.
	 *
	 * ## OPTIONS
	 *
	 * <report>
	 * : translations — liệt kê nội dung đã xuất bản bằng tiếng Việt mà chưa có bản dịch.
	 *
	 * [--lang=<lang>]
	 * : Ngôn ngữ đích (mặc định de).
	 *
	 * @param string[]             $args
	 * @param array<string,string> $assoc
	 */
	public function report( array $args, array $assoc ): void {
		if ( 'translations' !== ( $args[0] ?? '' ) ) {
			WP_CLI::error( 'Chỉ hỗ trợ: translations.' );
		}
		$lang = (string) ( $assoc['lang'] ?? 'de' );
		$rows = array();
		foreach ( array( 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri', 'page' ) as $type ) {
			foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ) as $id ) {
				if ( 'vi' !== apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => 'post_' . $type ) ) ) {
					continue;
				}
				if ( ! apply_filters( 'wpml_object_id', $id, $type, false, $lang ) ) {
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

	/* ---------------------------------------------------------------- nội bộ */

	/**
	 * Đọc images.csv (nếu có) và kiểm tra tệp ảnh tồn tại, không quá nặng. Lỗi nối vào $errors.
	 *
	 * @param string[] $codes
	 * @param string[] $errors
	 * @return array<string,array>
	 */
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
		$parsed = vgarco_import_images( (string) file_get_contents( $csv ), $codes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
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

	/** Tìm bài dự án theo `ma` trong một ngôn ngữ. */
	private function find( string $ma, string $lang ): int {
		$ids = get_posts(
			array(
				'post_type'        => 'du_an',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => 'ma', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $ma, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $ids as $id ) {
			$code = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => 'post_du_an' ) );
			if ( null === $code || $lang === $code ) { // không có WPML: mọi bài là tiếng Việt
				return (int) $id;
			}
		}
		return 0;
	}

	/** Giá trị hiện có của bài, cùng khóa với vgarco_import_flatten(). */
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
			// Cờ đúng/sai: ACF trả bool, 0/1, "0"/"1" hoặc rỗng (chưa từng lưu) — mọi dạng "sai" đều là '0', nếu không mỗi lần chạy lại sẽ báo thay đổi giả.
			$is_flag                = in_array( $key, array( 'cong_bo_chu_dau_tu', 'noi_bat', 'tieu_bieu' ), true );
			$flat[ 'meta.' . $key ] = $is_flag ? ( $value && '0' !== $value ? '1' : '0' ) : (string) $value;
		}
		$flat['terms.loai_cong_trinh'] = implode( ',', wp_get_object_terms( $post_id, 'loai_cong_trinh', array( 'fields' => 'slugs' ) ) );
		return $flat;
	}

	/**
	 * Tạo hoặc cập nhật một bài ở một ngôn ngữ.
	 *
	 * @param int   $source_id ID bài tiếng Việt khi tạo bản dịch (để nối nhóm dịch WPML).
	 * @param array $counts    Bộ đếm kết quả (tham chiếu).
	 * @return int ID bài (0 nếu --dry-run và bài chưa có).
	 */
	private function upsert( array $plan, string $lang, int $post_id, int $source_id, string $status, bool $dry, array &$counts ): int {
		$planned = vgarco_import_flatten( $plan, $lang );
		$diff    = vgarco_import_diff( $this->current( $post_id ), $planned );
		$label   = "{$plan['ma']} [{$lang}]";

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

		if ( 'de' === $lang && $source_id && ! apply_filters( 'wpml_element_has_translations', null, $post_id, 'post_du_an' ) ) {
			// Gắn vào nhóm dịch của bản tiếng Việt (lần chạy đầu của bản DE).
			do_action(
				'wpml_set_element_language_details',
				array(
					'element_id'           => $post_id,
					'element_type'         => 'post_du_an',
					'trid'                 => apply_filters( 'wpml_element_trid', null, $source_id, 'post_du_an' ),
					'language_code'        => 'de',
					'source_language_code' => 'vi',
				)
			);
		}

		update_field( 'ma', $plan['ma'], $post_id );
		foreach ( $planned as $key => $value ) {
			if ( str_starts_with( $key, 'meta.' ) && '' !== $value ) {
				$is_flag = in_array( $key, array( 'meta.cong_bo_chu_dau_tu', 'meta.noi_bat', 'meta.tieu_bieu' ), true );
				update_field( substr( $key, 5 ), $is_flag ? '1' === $value : $value, $post_id );
			}
		}
		if ( $plan['terms'] ) {
			wp_set_object_terms( $post_id, $plan['terms'], 'loai_cong_trinh' );
		}
		do_action( 'wpml_switch_language', null );
		return $post_id;
	}

	/**
	 * Gắn ảnh vào dự án tiếng Việt và cả bản tiếng Đức (nếu có): ảnh dùng chung, chú thích theo ngôn ngữ.
	 */
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

		$de_id = (int) apply_filters( 'wpml_object_id', $vi_id, 'du_an', false, 'de' );
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

	/**
	 * Nạp một tệp ảnh vào thư viện phương tiện, một lần duy nhất (so khớp SHA-1 của nội dung).
	 */
	private function sideload( string $path, int $post_id, string $alt ): int {
		$sha   = (string) sha1_file( $path );
		$found = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => '_vg_import_sha1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $sha, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $found ) {
			return (int) $found[0];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( basename( $path ) );
		copy( $path, $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		$id = media_handle_sideload( array( 'name' => basename( $path ), 'tmp_name' => $tmp ), $post_id );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
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

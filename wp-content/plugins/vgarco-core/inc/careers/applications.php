<?php
/**
 * Quản lý hồ sơ ứng tuyển trong trang quản trị (mục 6.4, 8.4, 10.4): kiểu nội dung riêng tư `ho_so_ut`, vai trò nhân sự tuyển dụng,
 * danh sách có bộ lọc, xem hồ sơ, tải CV có ghi nhật ký, xuất .xlsx, xóa (xóa thật kèm tệp CV) và tự xóa sau 12 tháng.
 *
 * Hồ sơ KHÔNG có URL công khai và không có trong tìm kiếm/sơ đồ site/REST mặc định.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Kiểu nội dung và quyền
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function (): void {
		register_post_type(
			'ho_so_ut',
			array(
				'labels'              => array_merge(
					vgarco_cpt_labels( 'Hồ sơ ứng tuyển' ),
					array(
						'add_new'   => 'Thêm mới',
						'not_found' => 'Chưa có hồ sơ nào',
					)
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false, // Công cụ Xuất của WordPress không được lấy dữ liệu cá nhân.
				'menu_icon'           => 'dashicons-portfolio',
				'menu_position'       => 9,
				'supports'            => array( 'title' ),
				'capability_type'     => array( 'ho_so_ut', 'ho_so_uts' ),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_posts'             => 'vg_view_applications',
					'edit_others_posts'      => 'vg_view_applications',
					'edit_private_posts'     => 'vg_view_applications',
					'edit_published_posts'   => 'vg_view_applications',
					'read_private_posts'     => 'vg_view_applications',
					'delete_posts'           => 'vg_delete_applications',
					'delete_others_posts'    => 'vg_delete_applications',
					'delete_private_posts'   => 'vg_delete_applications',
					'delete_published_posts' => 'vg_delete_applications',
					'publish_posts'          => 'do_not_allow',
					'create_posts'           => 'do_not_allow', // hồ sơ chỉ đến từ biểu mẫu, không tạo tay
				),
			)
		);
	}
);

/**
 * Vai trò "Nhân sự tuyển dụng" và quyền của Quản trị viên. Chạy một lần mỗi phiên bản (không chạy mỗi lần tải trang).
 */
function vgarco_apply_ensure_roles(): void {
	$version = '1';
	if ( get_option( 'vgarco_roles_version' ) === $version ) {
		return;
	}

	$caps = vgarco_recruit_caps();
	add_role( 'vg_tuyen_dung', 'Nhân sự tuyển dụng', array( 'read' => true ) + $caps );
	$role = get_role( 'vg_tuyen_dung' );
	foreach ( $caps as $cap => $grant ) {
		$role && $role->add_cap( $cap, $grant );
	}
	$admin = get_role( 'administrator' );
	foreach ( $caps as $cap => $grant ) {
		$admin && $admin->add_cap( $cap, $grant );
	}
	update_option( 'vgarco_roles_version', $version );
}
add_action( 'admin_init', 'vgarco_apply_ensure_roles' );

/* -------------------------------------------------------------------------
 * Danh sách
 * ---------------------------------------------------------------------- */

add_filter(
	'manage_ho_so_ut_posts_columns',
	static function (): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'title'      => 'Ứng viên',
			'vi_tri'     => 'Vị trí',
			'lien_lac'   => 'Liên hệ',
			'ngay_nop'   => 'Ngày nộp',
			'thu'        => 'Thư',
			'cv'         => 'CV',
		);
	}
);

add_action(
	'manage_ho_so_ut_posts_custom_column',
	static function ( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'vi_tri':
				echo esc_html( get_the_title( (int) get_post_meta( $post_id, 'vi_tri_id', true ) ) );
				break;
			case 'lien_lac':
				printf(
					'%s<br>%s',
					esc_html( (string) get_post_meta( $post_id, 'email', true ) ),
					esc_html( (string) get_post_meta( $post_id, 'dien_thoai', true ) )
				);
				break;
			case 'ngay_nop':
				echo esc_html( wp_date( 'd/m/Y H:i', (int) get_post_meta( $post_id, 'dong_y_luc', true ) ) );
				break;
			case 'thu':
				$hr        = (string) get_post_meta( $post_id, 'thu_hr', true );
				$candidate = (string) get_post_meta( $post_id, 'thu_ung_vien', true );
				echo esc_html( 'HR: ' . vgarco_apply_mail_status_label( $hr ) ), '<br>', esc_html( 'Ứng viên: ' . vgarco_apply_mail_status_label( $candidate ) );
				break;
			case 'cv':
				echo esc_html( strtoupper( vgarco_cv_ext_for_mime( (string) get_post_meta( $post_id, 'cv_mime', true ) ) ) );
				break;
		}
	},
	10,
	2
);

function vgarco_apply_mail_status_label( string $status ): string {
	return array(
		'da_gui' => 'đã gửi',
		'loi'    => 'LỖI — chờ thử lại',
		'cho_gui' => 'đang chờ',
	)[ $status ] ?? '—';
}

/** Bộ lọc trên danh sách: vị trí, thư lỗi. */
add_action(
	'restrict_manage_posts',
	static function ( string $post_type ): void {
		if ( 'ho_so_ut' !== $post_type ) {
			return;
		}
		$jobs = get_posts( array( 'post_type' => 'vi_tri', 'post_status' => 'any', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
		$sel  = isset( $_GET['vi_tri_id'] ) ? absint( $_GET['vi_tri_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="vi_tri_id"><option value="">Tất cả vị trí</option>';
		foreach ( $jobs as $job ) {
			printf( '<option value="%d"%s>%s</option>', (int) $job->ID, selected( $sel, $job->ID, false ), esc_html( get_the_title( $job ) ) );
		}
		echo '</select>';
		printf( '<label style="margin-left:6px"><input type="checkbox" name="thu_loi" value="1"%s> Chỉ hồ sơ lỗi thư</label>', checked( ! empty( $_GET['thu_loi'] ), true, false ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
);

add_action(
	'pre_get_posts',
	static function ( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || 'ho_so_ut' !== $query->get( 'post_type' ) ) {
			return;
		}
		$meta = array();
		if ( ! empty( $_GET['vi_tri_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$meta[] = array( 'key' => 'vi_tri_id', 'value' => absint( $_GET['vi_tri_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( ! empty( $_GET['thu_loi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$meta[] = array(
				'relation' => 'OR',
				array( 'key' => 'thu_hr', 'value' => 'loi' ),
				array( 'key' => 'thu_ung_vien', 'value' => 'loi' ),
			);
		}
		if ( $meta ) {
			$query->set( 'meta_query', $meta );
		}
		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
		}
	}
);

/** Tìm theo tên, email hoặc số điện thoại. */
add_filter(
	'posts_search',
	static function ( string $search, WP_Query $query ): string {
		global $wpdb;
		if ( '' === $search || ! is_admin() || ! $query->is_main_query() || 'ho_so_ut' !== $query->get( 'post_type' ) ) {
			return $search;
		}
		$like = '%' . $wpdb->esc_like( (string) $query->get( 's' ) ) . '%';
		return (string) $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} vgm WHERE vgm.post_id = {$wpdb->posts}.ID AND vgm.meta_key IN ( 'email', 'dien_thoai' ) AND vgm.meta_value LIKE %s ) )",
			$like,
			$like
		);
	},
	10,
	2
);

// Không có hành động "Sửa nhanh" và "Xem": hồ sơ chỉ đọc.
add_filter(
	'post_row_actions',
	static function ( array $actions, WP_Post $post ): array {
		if ( 'ho_so_ut' !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'], $actions['view'], $actions['clone'] );
		if ( isset( $actions['trash'] ) ) {
			$actions['trash'] = str_replace( '>Bỏ vào thùng rác<', '>Xóa hồ sơ và CV<', $actions['trash'] );
		}
		return $actions;
	},
	10,
	2
);

// Xóa hồ sơ là xóa thật (không qua thùng rác): ứng viên yêu cầu xóa dữ liệu thì dữ liệu phải mất, cả tệp CV.
add_filter(
	'pre_trash_post',
	static function ( $check, WP_Post $post ) {
		if ( 'ho_so_ut' !== $post->post_type ) {
			return $check;
		}
		return (bool) wp_delete_post( $post->ID, true );
	},
	10,
	2
);

add_action(
	'before_delete_post',
	static function ( int $post_id ): void {
		if ( 'ho_so_ut' !== get_post_type( $post_id ) ) {
			return;
		}
		vgarco_apply_delete_cv( (string) get_post_meta( $post_id, 'cv_duong_dan', true ) );
	}
);

/* -------------------------------------------------------------------------
 * Trang xem hồ sơ (chỉ đọc)
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_ho_so_ut',
	static function (): void {
		remove_meta_box( 'submitdiv', 'ho_so_ut', 'side' );
		remove_meta_box( 'slugdiv', 'ho_so_ut', 'normal' );
		add_meta_box( 'vg_ho_so', 'Hồ sơ', 'vgarco_apply_render_record', 'ho_so_ut', 'normal', 'high' );
	}
);

// Tiêu đề chỉ đọc: không có nút Cập nhật, tránh sửa nhầm dữ liệu ứng viên.
add_action(
	'admin_head-post.php',
	static function (): void {
		if ( 'ho_so_ut' === get_post_type() ) {
			echo '<style>#titlediv{display:none}</style>';
		}
	}
);

function vgarco_apply_render_record( WP_Post $post ): void {
	$m = static fn( string $key ) => get_post_meta( $post->ID, $key, true );

	$rows = array(
		'Vị trí ứng tuyển' => get_the_title( (int) $m( 'vi_tri_id' ) ),
		'Họ tên'           => (string) $m( 'ho_ten' ),
		'Email'            => (string) $m( 'email' ),
		'Điện thoại'       => (string) $m( 'dien_thoai' ),
		'Ngôn ngữ'         => strtoupper( (string) $m( 'ngon_ngu' ) ),
		'Nộp lúc'          => wp_date( 'd/m/Y H:i', (int) $m( 'dong_y_luc' ) ),
		'Đồng ý (phiên bản)' => (string) $m( 'dong_y_phien_ban' ),
		'Tệp CV'           => sprintf( '%s (%s KB)', (string) $m( 'cv_ten_goc' ), number_format_i18n( (int) $m( 'cv_kich_thuoc' ) / 1024, 0 ) ),
		'Thư tới HR'       => vgarco_apply_mail_status_label( (string) $m( 'thu_hr' ) ),
		'Thư tới ứng viên' => vgarco_apply_mail_status_label( (string) $m( 'thu_ung_vien' ) ),
	);

	echo '<table class="widefat striped" style="max-width:760px"><tbody>';
	foreach ( $rows as $label => $value ) {
		printf( '<tr><th style="width:190px">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( (string) $value ) );
	}
	echo '</tbody></table><p>';

	if ( current_user_can( 'vg_download_cv' ) ) {
		printf(
			'<a class="button button-primary" href="%s">Tải CV</a> ',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vgarco_download_cv&id=' . $post->ID ), 'vgarco_download_cv_' . $post->ID ) )
		);
	}
	if ( current_user_can( 'vg_delete_applications' ) ) {
		printf(
			'<a class="button" style="color:#b32d2e" href="%s" onclick="return confirm(\'Xóa hồ sơ này và tệp CV? Không thể khôi phục.\')">Xóa hồ sơ và CV</a>',
			esc_url( get_delete_post_link( $post->ID, '', true ) )
		);
	}
	echo '</p>';

	$log = (array) $m( 'nhat_ky_tai' );
	if ( $log ) {
		echo '<h4>Nhật ký tải CV</h4><ul>';
		foreach ( array_reverse( $log ) as $entry ) {
			$user = get_userdata( (int) ( $entry['user'] ?? 0 ) );
			printf( '<li>%s — %s</li>', esc_html( wp_date( 'd/m/Y H:i', (int) ( $entry['time'] ?? 0 ) ) ), esc_html( $user ? $user->display_name : 'không rõ' ) );
		}
		echo '</ul>';
	}
}

/* -------------------------------------------------------------------------
 * Tải CV
 * ---------------------------------------------------------------------- */

add_action(
	'admin_post_vgarco_download_cv',
	static function (): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'vgarco_download_cv_' . $id );
		if ( ! current_user_can( 'vg_download_cv' ) || 'ho_so_ut' !== get_post_type( $id ) ) {
			wp_die( 'Bạn không có quyền tải CV này.', 'Không có quyền', array( 'response' => 403 ) );
		}

		$path = vgarco_apply_cv_file( (string) get_post_meta( $id, 'cv_duong_dan', true ) );
		if ( '' === $path || ! is_file( $path ) ) {
			wp_die( 'Không tìm thấy tệp CV (có thể đã bị xóa theo thời hạn lưu trữ).', 'Không có tệp', array( 'response' => 404 ) );
		}

		// Ghi nhật ký TRƯỚC khi gửi tệp: ai tải, lúc nào.
		$log   = (array) get_post_meta( $id, 'nhat_ky_tai', true );
		$log[] = array( 'user' => get_current_user_id(), 'time' => time() );
		update_post_meta( $id, 'nhat_ky_tai', array_slice( $log, -50 ) );

		$mime = (string) get_post_meta( $id, 'cv_mime', true );
		$ext  = vgarco_cv_ext_for_mime( $mime );
		$name = sanitize_file_name( remove_accents( (string) get_post_meta( $id, 'ho_ten', true ) ) ) ?: 'CV';

		nocache_headers();
		header( 'Content-Type: ' . ( 'bin' === $ext ? 'application/octet-stream' : $mime ) );
		header( 'Content-Length: ' . (int) filesize( $path ) );
		header( 'Content-Disposition: attachment; filename="CV-' . $name . '.' . $ext . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Xuất .xlsx
 * ---------------------------------------------------------------------- */

add_action(
	'admin_notices',
	static function (): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-ho_so_ut' !== $screen->id ) {
			return;
		}
		if ( current_user_can( 'vg_export_applications' ) ) {
			$args = array( 'action' => 'vgarco_export_applications' );
			foreach ( array( 'vi_tri_id', 'thu_loi', 's' ) as $key ) {
				if ( ! empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
			}
			printf(
				'<div class="notice notice-info"><p><a class="button" href="%s">Xuất danh sách hồ sơ (.xlsx)</a> Xuất theo bộ lọc đang chọn. Tệp chứa dữ liệu cá nhân — không gửi qua kênh công khai.</p></div>',
				esc_url( wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'vgarco_export_applications' ) )
			);
		}
		$check = vgarco_private_dir_check();
		if ( ! $check['ok'] && current_user_can( 'manage_options' ) ) {
			printf( '<div class="notice notice-error"><p><strong>CV chưa lưu được:</strong> %s</p></div>', esc_html( $check['message'] ) );
		}
	}
);

add_action(
	'admin_post_vgarco_export_applications',
	static function (): void {
		check_admin_referer( 'vgarco_export_applications' );
		if ( ! current_user_can( 'vg_export_applications' ) ) {
			wp_die( 'Bạn không có quyền xuất hồ sơ.', 'Không có quyền', array( 'response' => 403 ) );
		}

		$args = array(
			'post_type'      => 'ho_so_ut',
			'post_status'    => 'any',
			'posts_per_page' => 5000,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		$meta = array();
		if ( ! empty( $_GET['vi_tri_id'] ) ) {
			$meta[] = array( 'key' => 'vi_tri_id', 'value' => absint( $_GET['vi_tri_id'] ) );
		}
		if ( ! empty( $_GET['thu_loi'] ) ) {
			$meta[] = array( 'relation' => 'OR', array( 'key' => 'thu_hr', 'value' => 'loi' ), array( 'key' => 'thu_ung_vien', 'value' => 'loi' ) );
		}
		if ( $meta ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		if ( ! empty( $_GET['s'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}

		$rows = array();
		foreach ( get_posts( $args ) as $post ) {
			$m      = static fn( string $key ) => get_post_meta( $post->ID, $key, true );
			$rows[] = array(
				(int) $post->ID,
				wp_date( 'd/m/Y H:i', (int) $m( 'dong_y_luc' ) ),
				get_the_title( (int) $m( 'vi_tri_id' ) ),
				(string) $m( 'ho_ten' ),
				(string) $m( 'email' ),
				(string) $m( 'dien_thoai' ),
				strtoupper( (string) $m( 'ngon_ngu' ) ),
				(string) $m( 'cv_ten_goc' ),
				vgarco_apply_mail_status_label( (string) $m( 'thu_hr' ) ),
			);
		}

		try {
			$binary = vgarco_xlsx_build(
				array( 'Mã hồ sơ', 'Nộp lúc', 'Vị trí', 'Họ tên', 'Email', 'Điện thoại', 'Ngôn ngữ', 'Tệp CV', 'Thư tới HR' ),
				$rows,
				array( 10, 18, 34, 26, 32, 16, 10, 34, 18 )
			);
		} catch ( Throwable $e ) {
			wp_die( 'Không tạo được tệp xuất: ' . esc_html( $e->getMessage() ), 'Lỗi', array( 'response' => 500 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Length: ' . strlen( $binary ) );
		header( 'Content-Disposition: attachment; filename="ho-so-ung-tuyen-' . wp_date( 'Ymd' ) . '.xlsx"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Tự xóa sau 12 tháng
 * ---------------------------------------------------------------------- */

/**
 * Xóa hồ sơ nộp quá 12 tháng, cả tệp CV. Mỗi lần tối đa 200 hồ sơ để không quá tải; phần còn lại xử lý ở lần sau.
 *
 * @return int Số hồ sơ đã xóa.
 */
function vgarco_apply_run_retention( int $now = 0 ): int {
	$now    = $now ?: time();
	$cutoff = vgarco_apply_retention_cutoff( $now );

	$ids = get_posts(
		array(
			'post_type'      => 'ho_so_ut',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'date_query'     => array( array( 'column' => 'post_date_gmt', 'before' => gmdate( 'Y-m-d H:i:s', $cutoff ) ) ),
		)
	);
	$deleted = 0;
	foreach ( $ids as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) { // before_delete_post xóa tệp CV
			++$deleted;
		}
	}
	return $deleted;
}
add_action( 'vgarco_apply_retention', 'vgarco_apply_run_retention' );

add_action(
	'init',
	static function (): void {
		if ( ! wp_next_scheduled( 'vgarco_apply_retention' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 02:30' ), 'daily', 'vgarco_apply_retention' );
		}
	}
);

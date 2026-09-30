<?php
/**
 * Phần WordPress của luồng nộp hồ sơ: bộ nhớ token/mã bảo mật (transient), bộ đếm tần suất, lưu CV ngoài thư mục web,
 * bản ghi ho_so_ut, thư thông báo + thư xác nhận (thử lại khi lỗi), cảnh báo, và hai đường REST.
 * Logic quyết định nằm ở apply-process.php (thuần, có kiểm thử); tệp này chỉ nối các phụ thuộc vào WordPress.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Danh tính người gửi
 * ---------------------------------------------------------------------- */

/**
 * IP người gửi. Sau Cloudflare mọi kết nối đến từ IP Cloudflare nên phải đọc CF-Connecting-IP; máy chủ gốc chỉ nhận
 * kết nối từ Cloudflare (Authenticated Origin Pulls, mục 10.1) nên header này không giả mạo được từ ngoài.
 */
function vgarco_client_ip(): string {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $key ) {
		$value = isset( $_SERVER[ $key ] ) ? trim( (string) wp_unslash( $_SERVER[ $key ] ) ) : '';
		if ( '' !== $value && false !== filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return $value;
		}
	}
	return '0.0.0.0';
}

/**
 * IP đã băm có muối: đủ để giới hạn tần suất, không lưu IP gốc (mục 6.4).
 */
function vgarco_apply_ip_hash( string $ip = '' ): string {
	return hash_hmac( 'sha256', '' !== $ip ? $ip : vgarco_client_ip(), wp_salt( 'auth' ) );
}

/* -------------------------------------------------------------------------
 * Token và mã bảo mật
 * ---------------------------------------------------------------------- */

/**
 * Cấp token mới cùng ảnh mã bảo mật. Chỉ lưu BĂM của mã; token sống 2 giờ (khớp giới hạn thời gian điền biểu mẫu).
 *
 * @return array{token: string, image: string}
 */
function vgarco_apply_token_issue(): array {
	$code  = vgarco_captcha_code();
	$token = bin2hex( random_bytes( 16 ) );

	set_transient(
		'vg_apply_' . $token,
		array(
			'issued' => time(),
			'hash'   => vgarco_captcha_hash( $code, wp_salt( 'auth' ) ),
		),
		VGARCO_APPLY_MAX_SECONDS + 60
	);

	return array(
		'token' => $token,
		'image' => vgarco_captcha_image( $code ),
	);
}

/**
 * Đọc token mà chưa hủy. Trả null nếu không có, đã dùng hoặc hết hạn.
 */
function vgarco_apply_token_peek( string $token ): ?array {
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
		return null;
	}
	$value = get_transient( 'vg_apply_' . $token );
	return is_array( $value ) && isset( $value['issued'], $value['hash'] ) ? $value : null;
}

/**
 * Hủy token (dùng một lần). Trả false nếu token đã bị bên khác tiêu thụ trước.
 */
function vgarco_apply_token_consume( string $token ): bool {
	if ( null === vgarco_apply_token_peek( $token ) ) {
		return false;
	}
	return (bool) delete_transient( 'vg_apply_' . $token );
}

/* -------------------------------------------------------------------------
 * Giới hạn tần suất (mục 8.3 bước 4)
 * ---------------------------------------------------------------------- */

/**
 * Bộ đếm theo IP đã băm và một cửa sổ thời gian. Khóa chứa số thứ tự cửa sổ nên tự hết hạn, không cần dọn.
 *
 * @param string $kind   attempts | submissions_hour | submissions_day
 * @param int    $window Độ dài cửa sổ (giây).
 */
function vgarco_apply_counter_key( string $ip_hash, string $kind, int $window ): string {
	return 'vg_rate_' . substr( $ip_hash, 0, 24 ) . '_' . $kind . '_' . (int) floor( time() / $window );
}

function vgarco_apply_counter( string $ip_hash, string $kind, int $window ): int {
	return (int) get_transient( vgarco_apply_counter_key( $ip_hash, $kind, $window ) );
}

function vgarco_apply_counter_hit( string $ip_hash, string $kind, int $window ): void {
	set_transient( vgarco_apply_counter_key( $ip_hash, $kind, $window ), vgarco_apply_counter( $ip_hash, $kind, $window ) + 1, $window + 60 );
}

/** Còn được thử: tối đa 30 lần gửi mỗi giờ (chống dò mã bảo mật). */
function vgarco_apply_rate_attempts( string $ip_hash ): bool {
	return vgarco_apply_counter( $ip_hash, 'attempts', HOUR_IN_SECONDS ) < 30;
}

/** Còn được nộp: tối đa 5 hồ sơ mỗi giờ và 20 hồ sơ mỗi ngày. */
function vgarco_apply_rate_submissions( string $ip_hash ): bool {
	return vgarco_apply_counter( $ip_hash, 'submissions_hour', HOUR_IN_SECONDS ) < 5
		&& vgarco_apply_counter( $ip_hash, 'submissions_day', DAY_IN_SECONDS ) < 20;
}

function vgarco_apply_rate_record( string $ip_hash, bool $accepted ): void {
	if ( $accepted ) {
		vgarco_apply_counter_hit( $ip_hash, 'submissions_hour', HOUR_IN_SECONDS );
		vgarco_apply_counter_hit( $ip_hash, 'submissions_day', DAY_IN_SECONDS );
		return;
	}
	vgarco_apply_counter_hit( $ip_hash, 'attempts', HOUR_IN_SECONDS );
}

/* -------------------------------------------------------------------------
 * Lưu CV
 * ---------------------------------------------------------------------- */

/**
 * Thư mục riêng tư CHỨA CV: ngoài thư mục web, không có URL công khai. Đặt hằng số VGARCO_PRIVATE_DIR trong wp-config.php
 * (production: /srv/vgarco/private). Mặc định nằm cạnh thư mục WordPress, không nằm dưới nó.
 */
function vgarco_private_dir(): string {
	return rtrim( defined( 'VGARCO_PRIVATE_DIR' ) ? (string) VGARCO_PRIVATE_DIR : dirname( untrailingslashit( ABSPATH ) ) . '/vgarco-private', '/' );
}

/**
 * Kiểm tra nơi lưu CV: phải nằm NGOÀI thư mục web của WordPress. Thư mục nằm trong web root có thể bị tải trực tiếp
 * bằng đường dẫn — vì vậy hệ thống từ chối lưu chứ không lưu tạm.
 *
 * @return array{ok: bool, message: string}
 */
function vgarco_private_dir_check(): array {
	$dir = vgarco_private_dir();
	if ( vgarco_path_inside( $dir, untrailingslashit( ABSPATH ) ) || vgarco_path_inside( $dir, WP_CONTENT_DIR ) ) {
		return array( 'ok' => false, 'message' => 'Thư mục lưu CV đang nằm trong thư mục web. Đặt VGARCO_PRIVATE_DIR trỏ ra ngoài (ví dụ /srv/vgarco/private) trong wp-config.php.' );
	}
	if ( ! wp_mkdir_p( $dir ) || ! wp_is_writable( $dir ) ) {
		return array( 'ok' => false, 'message' => 'Không ghi được vào thư mục lưu CV: ' . $dir );
	}
	return array( 'ok' => true, 'message' => '' );
}

/**
 * Lưu tệp CV vào cv/YYYY/MM/<32 ký tự hex> (quyền 0640, không đuôi — không thể thực thi hay bị máy chủ web phục vụ).
 *
 * @return array{path: string} Đường dẫn TƯƠNG ĐỐI so với vgarco_private_dir().
 * @throws RuntimeException Khi nơi lưu không an toàn hoặc không ghi được tệp.
 */
function vgarco_apply_save_cv( string $tmp, string $ext ): array {
	unset( $ext ); // giữ tham số để đồng bộ với bản kiểm thử; tệp lưu không đuôi

	$check = vgarco_private_dir_check();
	if ( ! $check['ok'] ) {
		vgarco_apply_alert( 'Không nhận được CV: ' . $check['message'], true );
		throw new RuntimeException( $check['message'] );
	}

	$relative_dir = 'cv/' . gmdate( 'Y' ) . '/' . gmdate( 'm' );
	$dir          = vgarco_private_dir() . '/' . $relative_dir;
	if ( ! wp_mkdir_p( $dir ) ) {
		throw new RuntimeException( 'Không tạo được thư mục lưu CV.' );
	}
	@chmod( $dir, 0750 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod

	$name = bin2hex( random_bytes( 16 ) );
	$dest = $dir . '/' . $name;
	$ok   = is_uploaded_file( $tmp ) ? move_uploaded_file( $tmp, $dest ) : copy( $tmp, $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
	if ( ! $ok ) {
		throw new RuntimeException( 'Không ghi được tệp CV.' );
	}
	@chmod( $dest, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod

	return array( 'path' => $relative_dir . '/' . $name );
}

/**
 * Đường dẫn tuyệt đối của CV đã lưu, hoặc '' nếu đường dẫn tương đối không hợp lệ (chặn ".." và mọi thứ ngoài cv/).
 */
function vgarco_apply_cv_file( string $relative ): string {
	return vgarco_apply_cv_path_valid( $relative ) ? vgarco_private_dir() . '/' . $relative : '';
}

/**
 * Xóa tệp CV theo đường dẫn tương đối. Chỉ xóa bên trong thư mục riêng tư.
 */
function vgarco_apply_delete_cv( string $relative ): bool {
	$path = vgarco_apply_cv_file( $relative );
	if ( '' === $path || ! is_file( $path ) ) {
		return false;
	}
	wp_delete_file( $path );
	return ! file_exists( $path );
}

/* -------------------------------------------------------------------------
 * Bản ghi hồ sơ
 * ---------------------------------------------------------------------- */

/**
 * Tạo bản ghi ho_so_ut. Tên bài = "Họ tên — Vị trí" để nhận diện nhanh trong danh sách quản trị.
 *
 * @throws RuntimeException Khi không ghi được.
 */
function vgarco_apply_insert( array $data ): int {
	$id = wp_insert_post(
		array(
			'post_type'   => 'ho_so_ut',
			'post_status' => 'publish',
			'post_title'  => wp_strip_all_tags( $data['name'] ) . ' — ' . get_the_title( (int) $data['job_id'] ),
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		throw new RuntimeException( 'Không tạo được bản ghi hồ sơ.' );
	}

	$meta = array(
		'vi_tri_id'        => (int) $data['job_id'],
		'ngon_ngu'         => $data['lang'],
		'ho_ten'           => sanitize_text_field( $data['name'] ),
		'email'            => sanitize_email( $data['email'] ),
		'dien_thoai'       => sanitize_text_field( $data['phone'] ),
		'cv_duong_dan'     => $data['cv_path'],
		'cv_ten_goc'       => $data['cv_name'],
		'cv_mime'          => $data['cv_mime'],
		'cv_kich_thuoc'    => (int) $data['cv_size'],
		'dong_y_luc'       => (int) $data['consent_at'],
		'dong_y_phien_ban' => $data['consent_ver'],
		'ip_bam'           => $data['ip_hash'],
		'thu_hr'           => 'cho_gui',
		'thu_ung_vien'     => 'cho_gui',
		'thu_thu_lai'      => 0,
		'nhat_ky_tai'      => array(),
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return (int) $id;
}

/* -------------------------------------------------------------------------
 * Thư (mục 8.3 bước 12 và mục 10a)
 * ---------------------------------------------------------------------- */

/**
 * Chạy $fn trong một ngôn ngữ (WPML và locale của WordPress), rồi trả về như cũ. Mẫu thư mặc định và giá trị đã dịch
 * phải theo ngôn ngữ của người nhận, không theo ngôn ngữ của yêu cầu hiện tại (cron chạy ở ngôn ngữ mặc định).
 */
function vgarco_with_language( string $lang, callable $fn ) {
	do_action( 'wpml_switch_language', $lang );
	$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( 'de' === $lang ? 'de_DE' : 'vi' );
	try {
		return $fn();
	} finally {
		if ( $switched ) {
			restore_previous_locale();
		}
		do_action( 'wpml_switch_language', null );
	}
}

/**
 * Mẫu thư: giá trị nhập ở trang Cấu hình (nhóm "Mẫu thư"), thiếu thì dùng mặc định trong mã.
 *
 * @param string $kind hr | ung_vien
 * @return array{subject: string, body: string}
 */
function vgarco_apply_mail_template( string $kind ): array {
	$pick = static fn( string $key, string $default ): string => (string) vgarco_option( 'mau_thu.' . $key, $default );

	if ( 'hr' === $kind ) {
		return array(
			'subject' => $pick( 'hr_tieu_de', __( '[VGARCO] Hồ sơ ứng tuyển: {vi_tri} — {ten}', 'vgarco-core' ) ),
			'body'    => $pick( 'hr_noi_dung', __( "Có hồ sơ ứng tuyển mới.

Vị trí: {vi_tri}
Họ tên: {ten}
Email: {email}
Điện thoại: {dien_thoai}
Thời điểm: {thoi_diem}
Ngôn ngữ: {ngon_ngu}

Xem hồ sơ và tải CV (cần đăng nhập): {lien_ket}

Bấm \"Trả lời\" để trả lời thẳng ứng viên.", 'vgarco-core' ) ),
		);
	}
	return array(
		'subject' => $pick( 'ung_vien_tieu_de', __( 'VGARCO đã nhận hồ sơ ứng tuyển của bạn — {vi_tri}', 'vgarco-core' ) ),
		'body'    => $pick( 'ung_vien_noi_dung', __( "Chào {ten},

VGARCO đã nhận hồ sơ ứng tuyển vị trí {vi_tri} của bạn. Bộ phận nhân sự sẽ xem hồ sơ và liên hệ nếu có bước tiếp theo.

Nếu cần bổ sung thông tin, bạn có thể trả lời trực tiếp email này.

Trân trọng,
VGARCO", 'vgarco-core' ) ),
	);
}

/**
 * Gửi thư thông báo tới hộp tuyển dụng và thư xác nhận tới ứng viên (đúng ngôn ngữ của ứng viên). Thư gửi HR KHÔNG đính kèm CV:
 * càng ít bản sao CV trôi nổi càng tốt, và Brevo giới hạn tệp đính kèm. Kết quả ghi vào meta để thử lại khi lỗi.
 *
 * @return array{hr: bool, candidate: bool}
 */
function vgarco_apply_mail( int $record_id ): array {
	$lang = (string) get_post_meta( $record_id, 'ngon_ngu', true );
	$lang = in_array( $lang, array( 'vi', 'de' ), true ) ? $lang : 'vi';

	// Thư cho HR luôn bằng tiếng Việt; thư xác nhận cho ứng viên theo ngôn ngữ ứng viên đã dùng khi nộp.
	$tpl_hr        = vgarco_with_language( 'vi', static fn(): array => vgarco_apply_mail_template( 'hr' ) );
	$tpl_candidate = vgarco_with_language( $lang, static fn(): array => vgarco_apply_mail_template( 'ung_vien' ) );
	$hr_to  = (string) ( vgarco_option( 'lien_he.email_tuyen_dung' ) ?: vgarco_option( 'lien_he.email_chung' ) );
	$values = array(
		'ten'        => vgarco_mail_line( (string) get_post_meta( $record_id, 'ho_ten', true ) ),
		'vi_tri'     => vgarco_mail_line( get_the_title( (int) get_post_meta( $record_id, 'vi_tri_id', true ) ) ),
		'email'      => vgarco_mail_line( (string) get_post_meta( $record_id, 'email', true ) ),
		'dien_thoai' => vgarco_mail_line( (string) get_post_meta( $record_id, 'dien_thoai', true ) ),
		'thoi_diem'  => wp_date( 'd/m/Y H:i', (int) get_post_meta( $record_id, 'dong_y_luc', true ) ),
		'ngon_ngu'   => strtoupper( $lang ),
		'lien_ket'   => admin_url( 'post.php?post=' . $record_id . '&action=edit' ),
	);
	$candidate = $values['email'];
	$text_type = array( 'Content-Type: text/plain; charset=UTF-8' );

	// Thư đã gửi được từ lần trước thì không gửi lại khi thử lại (HR không nhận hai bản, ứng viên không nhận hai xác nhận).
	$result = array(
		'hr'        => 'da_gui' === get_post_meta( $record_id, 'thu_hr', true ),
		'candidate' => 'da_gui' === get_post_meta( $record_id, 'thu_ung_vien', true ),
	);

	if ( ! $result['hr'] && '' !== $hr_to ) {
		$headers      = is_email( $candidate ) ? array_merge( $text_type, array( 'Reply-To: ' . $candidate ) ) : $text_type;
		$result['hr'] = (bool) wp_mail( $hr_to, vgarco_mail_line( vgarco_apply_fill( $tpl_hr['subject'], $values ) ), vgarco_apply_fill( $tpl_hr['body'], $values ), $headers );
	}
	if ( ! $result['candidate'] && is_email( $candidate ) ) {
		$headers             = '' !== $hr_to ? array_merge( $text_type, array( 'Reply-To: ' . $hr_to ) ) : $text_type;
		$result['candidate'] = (bool) wp_mail( $candidate, vgarco_mail_line( vgarco_apply_fill( $tpl_candidate['subject'], $values ) ), vgarco_apply_fill( $tpl_candidate['body'], $values ), $headers );
	}

	update_post_meta( $record_id, 'thu_hr', $result['hr'] ? 'da_gui' : 'loi' );
	update_post_meta( $record_id, 'thu_ung_vien', $result['candidate'] ? 'da_gui' : 'loi' );

	if ( ! $result['hr'] || ! $result['candidate'] ) {
		vgarco_apply_alert(
			sprintf(
				/* translators: 1: ID hồ sơ, 2: tên ứng viên */
				__( 'Hồ sơ #%1$d (%2$s) đã lưu nhưng thư chưa gửi được. Hệ thống sẽ tự thử lại mỗi giờ (tối đa 3 lần).', 'vgarco-core' ),
				$record_id,
				$values['ten']
			)
		);
		vgarco_apply_schedule_retry();
	}

	return $result;
}

/** Lên lịch chạy lại thư mỗi giờ nếu chưa có. */
function vgarco_apply_schedule_retry(): void {
	if ( ! wp_next_scheduled( 'vgarco_apply_mail_retry' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'vgarco_apply_mail_retry' );
	}
}

/**
 * Thử lại thư của các hồ sơ lỗi thư, tối đa 3 lần mỗi hồ sơ. Quá 3 lần: cảnh báo và ngừng thử.
 */
function vgarco_apply_retry_mails(): void {
	$ids = get_posts(
		array(
			'post_type'      => 'ho_so_ut',
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => 'thu_hr', 'value' => 'loi' ),
				array( 'key' => 'thu_ung_vien', 'value' => 'loi' ),
			),
		)
	);

	foreach ( $ids as $id ) {
		$tries = (int) get_post_meta( $id, 'thu_thu_lai', true );
		if ( $tries >= 3 ) {
			continue;
		}
		update_post_meta( $id, 'thu_thu_lai', $tries + 1 );
		$result = vgarco_apply_mail( (int) $id );
		if ( ( ! $result['hr'] || ! $result['candidate'] ) && $tries + 1 >= 3 ) {
			vgarco_apply_alert(
				sprintf(
					/* translators: %d: ID hồ sơ */
					__( 'Hồ sơ #%d: thư vẫn chưa gửi được sau 3 lần thử. Cần kiểm tra cấu hình thư (FluentSMTP/Brevo) và liên hệ ứng viên thủ công.', 'vgarco-core' ),
					(int) $id
				),
				true
			);
		}
	}
}
add_action( 'vgarco_apply_mail_retry', 'vgarco_apply_retry_mails' );

/* -------------------------------------------------------------------------
 * Cảnh báo
 * ---------------------------------------------------------------------- */

/**
 * Ghi cảnh báo để quản trị viên thấy ở trang quản trị và, nếu đã cấu hình, gửi Telegram (mục 10.6).
 *
 * @param bool $urgent Gửi cả email cho quản trị viên.
 */
function vgarco_apply_alert( string $message, bool $urgent = false ): void {
	$alerts   = (array) get_option( 'vgarco_alerts', array() );
	$alerts[] = array( 'time' => time(), 'message' => $message );
	update_option( 'vgarco_alerts', array_slice( $alerts, -20 ), false );

	if ( defined( 'VGARCO_TELEGRAM_TOKEN' ) && defined( 'VGARCO_TELEGRAM_CHAT' ) && VGARCO_TELEGRAM_TOKEN && VGARCO_TELEGRAM_CHAT ) {
		wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( (string) VGARCO_TELEGRAM_TOKEN ) . '/sendMessage',
			array(
				'timeout' => 5,
				'body'    => array( 'chat_id' => (string) VGARCO_TELEGRAM_CHAT, 'text' => '[VGARCO] ' . $message ),
			)
		);
	}
	if ( $urgent ) {
		wp_mail( (string) get_option( 'admin_email' ), '[VGARCO] Cảnh báo hệ thống tuyển dụng', $message );
	}
}

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$alerts = (array) get_option( 'vgarco_alerts', array() );
		if ( ! $alerts ) {
			return;
		}
		$last = end( $alerts );
		printf(
			'<div class="notice notice-warning"><p><strong>VGARCO:</strong> %s <a href="%s">Đã xem, ẩn cảnh báo</a></p></div>',
			esc_html( (string) $last['message'] ),
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vgarco_dismiss_alerts' ), 'vgarco_dismiss_alerts' ) )
		);
	}
);

add_action(
	'admin_post_vgarco_dismiss_alerts',
	static function (): void {
		check_admin_referer( 'vgarco_dismiss_alerts' );
		if ( current_user_can( 'manage_options' ) ) {
			delete_option( 'vgarco_alerts' );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Đường REST
 * ---------------------------------------------------------------------- */

/** Các hàm phụ thuộc thật, truyền cho vgarco_apply_process(). */
function vgarco_apply_deps(): array {
	return array(
		'rate_attempts'    => 'vgarco_apply_rate_attempts',
		'rate_submissions' => 'vgarco_apply_rate_submissions',
		'rate_record'      => 'vgarco_apply_rate_record',
		'token_peek'       => 'vgarco_apply_token_peek',
		'token_consume'    => 'vgarco_apply_token_consume',
		'token_issue'      => 'vgarco_apply_token_issue',
		'hash'             => static fn( string $code ): string => vgarco_captcha_hash( $code, wp_salt( 'auth' ) ),
		'job_open'         => 'vgarco_job_is_open',
		'save_cv'          => 'vgarco_apply_save_cv',
		'insert'           => 'vgarco_apply_insert',
		'delete_cv'        => 'vgarco_apply_delete_cv',
		'mail'             => 'vgarco_apply_mail',
	);
}

add_action(
	'rest_api_init',
	static function (): void {
		// Cấp mã bảo mật. Không cache (mỗi lần một mã) và có giới hạn tần suất riêng để không bị dùng làm đòn bẩy tốn CPU vẽ ảnh.
		register_rest_route(
			'vgarco/v1',
			'/apply/token',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$ip = vgarco_apply_ip_hash();
					if ( ! vgarco_apply_rate_attempts( $ip ) ) {
						return new WP_REST_Response( array( 'ok' => false, 'code' => 'rate_limited' ), 429, array( 'Cache-Control' => 'no-store' ) );
					}
					vgarco_apply_rate_record( $ip, false );
					return new WP_REST_Response( vgarco_apply_token_issue(), 200, array( 'Cache-Control' => 'no-store' ) );
				},
			)
		);

		// Nhận hồ sơ.
		register_rest_route(
			'vgarco/v1',
			'/apply',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $request ) {
					$result = vgarco_apply_process(
						array_map( static fn( $v ) => is_scalar( $v ) ? (string) $v : '', (array) $request->get_body_params() ),
						(array) $request->get_file_params(),
						array(
							'method'  => $request->get_method(),
							'origin'  => (string) $request->get_header( 'origin' ),
							'referer' => (string) $request->get_header( 'referer' ),
							'host'    => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
							'ip_hash' => vgarco_apply_ip_hash(),
							'now'     => time(),
						),
						vgarco_apply_deps()
					);
					return new WP_REST_Response( $result['body'], $result['status'], array( 'Cache-Control' => 'no-store' ) );
				},
			)
		);
	}
);

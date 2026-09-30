<?php
/**
 * Điều phối các bước nhận hồ sơ ứng tuyển (mục 8.3 của phương án). Hàm thuần: mọi thứ phụ thuộc WordPress (bộ nhớ token,
 * bộ đếm tần suất, lưu tệp, ghi bản ghi, gửi thư, đồng hồ) được truyền vào qua $deps — nhờ vậy toàn bộ luồng (kể cả thứ tự
 * các bước và mã trả về) kiểm thử được bằng tệp thật mà không cần WordPress.
 *
 * Thứ tự và mã trả về:
 *   3  Origin/Referer không đúng tên miền                      → 403 forbidden
 *   4  vượt giới hạn tần suất (theo IP đã băm)                  → 429 rate_limited
 *   5  trường bẫy có chữ, hoặc điền quá nhanh (< 4 giây) hay quá lâu (> 2 giờ) → 400 rejected (KHÔNG lưu, không gợi ý lý do)
 *   6  mã bảo mật sai/hết hạn/đã dùng                           → 400 validation.captcha + mã mới, giữ dữ liệu
 *   7  vị trí đã ngừng nhận                                    → 409 closed
 *   8  trường sai                                              → 400 validation.errors
 *   9  tệp sai                                                 → 400 validation.errors.cv
 *   10 lưu tệp / ghi bản ghi lỗi                               → 500 server
 *   12 gửi thư lỗi                                             → 200 ok (hồ sơ VẪN lưu; thư đánh dấu để thử lại)
 *
 * Mã bảo mật CHỈ bị tiêu thụ khi mọi thứ khác đã hợp lệ (hoặc khi nó sai): người dùng sửa lỗi ở trường khác không phải gõ lại mã.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

const VGARCO_APPLY_MIN_SECONDS      = 4;
const VGARCO_APPLY_MAX_SECONDS      = 7200;
const VGARCO_APPLY_CONSENT_VERSION  = '2026-09';

/**
 * @param array $in    Trường gửi lên (job_id, name, email, phone, consent, captcha, token, website, lang).
 * @param array $files $_FILES-like: ['cv' => [name, tmp_name, error, size]].
 * @param array $ctx   method, origin, referer, host (tên miền website), ip_hash, now (timestamp).
 * @param array $deps  Các hàm phụ thuộc (xem apply-wp.php cho bản thật, tests/php/apply.php cho bản giả):
 *                     rate_attempts(ip): bool  còn được thử · rate_submissions(ip): bool  còn được nộp · rate_record(ip, bool $accepted)
 *                     token_peek(token): ?array{issued:int, hash:string} · token_consume(token): bool · token_issue(): array{token,image}
 *                     hash(code): string · job_open(int): bool · save_cv(tmp, ext): array{path,...} · insert(array): int · delete_cv(path)
 *                     mail(int $record_id): array{hr:bool, candidate:bool}
 * @return array{status: int, body: array}
 */
function vgarco_apply_process( array $in, array $files, array $ctx, array $deps ): array {
	$reply = static fn( int $status, array $body ): array => array( 'status' => $status, 'body' => $body );

	// 3. Phương thức và nguồn gốc yêu cầu.
	if ( 'POST' !== strtoupper( (string) ( $ctx['method'] ?? '' ) ) || ! vgarco_apply_same_origin( (string) ( $ctx['origin'] ?? '' ), (string) ( $ctx['referer'] ?? '' ), (string) ( $ctx['host'] ?? '' ) ) ) {
		return $reply( 403, array( 'ok' => false, 'code' => 'forbidden' ) );
	}

	// 4. Tần suất theo IP đã băm.
	$ip = (string) ( $ctx['ip_hash'] ?? '' );
	if ( ! $deps['rate_attempts']( $ip ) || ! $deps['rate_submissions']( $ip ) ) {
		return $reply( 429, array( 'ok' => false, 'code' => 'rate_limited' ) );
	}
	$deps['rate_record']( $ip, false ); // mỗi lần gửi tính là một lần thử (chống dò mã bảo mật)

	// 5. Trường bẫy và thời gian điền. Từ chối im lặng: không nói lý do để máy gửi tự động không điều chỉnh được.
	if ( '' !== trim( (string) ( $in['website'] ?? '' ) ) ) {
		return $reply( 400, array( 'ok' => false, 'code' => 'rejected' ) );
	}
	$token = (string) ( $in['token'] ?? '' );
	$entry = '' !== $token ? $deps['token_peek']( $token ) : null;
	if ( $entry ) {
		$elapsed = (int) $ctx['now'] - (int) $entry['issued'];
		if ( $elapsed < VGARCO_APPLY_MIN_SECONDS || $elapsed > VGARCO_APPLY_MAX_SECONDS ) {
			return $reply( 400, array( 'ok' => false, 'code' => 'rejected' ) );
		}
	}

	// 6. Mã bảo mật (chỉ đọc, chưa tiêu thụ).
	$captcha_ok = $entry && hash_equals( (string) $entry['hash'], $deps['hash']( (string) ( $in['captcha'] ?? '' ) ) );
	if ( ! $captcha_ok ) {
		if ( $entry ) {
			$deps['token_consume']( $token ); // mã sai: hủy token, buộc phải dùng mã mới
		}
		return $reply(
			400,
			array(
				'ok'      => false,
				'code'    => 'validation',
				'errors'  => array( 'captcha' => 'captcha' ),
				'captcha' => $deps['token_issue'](),
			)
		);
	}

	// 7. Vị trí còn nhận hồ sơ (kiểm tra lại ở máy chủ, không tin trang có thể đang nằm trong cache).
	$job_id = (int) ( $in['job_id'] ?? 0 );
	if ( $job_id <= 0 || ! $deps['job_open']( $job_id ) ) {
		return $reply( 409, array( 'ok' => false, 'code' => 'closed' ) );
	}

	// 8–9. Trường và tệp — gom mọi lỗi để người dùng sửa một lượt.
	$errors = vgarco_apply_validate_fields( $in );
	$cv     = vgarco_apply_validate_cv( $files['cv'] ?? null );
	if ( ! $cv['ok'] ) {
		$errors['cv'] = $cv['error'];
	}
	if ( $errors ) {
		return $reply( 400, array( 'ok' => false, 'code' => 'validation', 'errors' => $errors ) );
	}

	// Mọi thứ hợp lệ: tiêu thụ token (dùng một lần). Hai yêu cầu song song cùng token: chỉ một bên thắng.
	if ( ! $deps['token_consume']( $token ) ) {
		return $reply( 400, array( 'ok' => false, 'code' => 'rejected' ) );
	}

	// 10. Lưu tệp với tên ngẫu nhiên ngoài thư mục web.
	try {
		$stored = $deps['save_cv']( (string) $files['cv']['tmp_name'], $cv['ext'] );
	} catch ( Throwable $e ) {
		return $reply( 500, array( 'ok' => false, 'code' => 'server' ) );
	}

	// 11. Ghi bản ghi; lỗi thì xóa tệp vừa lưu để không để lại tệp mồ côi.
	try {
		$record_id = $deps['insert'](
			array(
				'job_id'       => $job_id,
				'lang'         => in_array( $in['lang'] ?? '', array( 'vi', 'de' ), true ) ? $in['lang'] : 'vi',
				'name'         => trim( (string) $in['name'] ),
				'email'        => trim( (string) $in['email'] ),
				'phone'        => trim( (string) $in['phone'] ),
				'cv_path'      => $stored['path'],
				'cv_name'      => $cv['name'],
				'cv_mime'      => $cv['mime'],
				'cv_size'      => $cv['size'],
				'consent_at'   => (int) $ctx['now'],
				'consent_ver'  => VGARCO_APPLY_CONSENT_VERSION,
				'ip_hash'      => $ip,
			)
		);
	} catch ( Throwable $e ) {
		$deps['delete_cv']( $stored['path'] );
		return $reply( 500, array( 'ok' => false, 'code' => 'server' ) );
	}

	$deps['rate_record']( $ip, true ); // chỉ hồ sơ nhận được mới tính vào giới hạn 5/giờ và 20/ngày

	// 12. Thư. Lỗi thư KHÔNG làm mất hồ sơ: bản ghi giữ trạng thái để thử lại (mục 10a).
	$mail = array( 'hr' => false, 'candidate' => false );
	try {
		$mail = $deps['mail']( $record_id );
	} catch ( Throwable $e ) {
		unset( $e ); // đã có bản ghi; cron thử lại thư
	}

	return $reply( 200, array( 'ok' => true, 'id' => $record_id, 'mail' => $mail ) );
}

/**
 * Yêu cầu phải đến từ chính website: Origin (nếu có) phải trùng tên miền; không có Origin thì Referer phải trùng.
 * Cả hai đều thiếu → từ chối (trình duyệt luôn gửi Origin cho POST bằng fetch).
 */
function vgarco_apply_same_origin( string $origin, string $referer, string $host ): bool {
	if ( '' === $host ) {
		return false;
	}
	foreach ( array( $origin, $referer ) as $candidate ) {
		if ( '' !== $candidate ) {
			$found = parse_url( $candidate, PHP_URL_HOST );
			return is_string( $found ) && 0 === strcasecmp( $found, $host );
		}
	}
	return false;
}

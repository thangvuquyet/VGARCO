<?php
/**
 * Xóa cache Cloudflare (mục 8.8) — phần THUẦN: dựng yêu cầu API, quyết định khi nào cần xóa, tính giờ cron. Kiểm thử bằng dữ liệu giả
 * (tests/php/security.php). Phần gọi WordPress (hook, cron, wp_remote_post) nằm ở cache.php.
 *
 * Site nhỏ nên xóa TOÀN BỘ cache thay vì theo từng URL (phải tính cả bản dịch, trang danh mục, trang chủ) — đơn giản hơn và ít lỗi hơn.
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/** Sau bao nhiêu giây kể từ thay đổi đầu tiên thì xóa cache (gom nhiều lần lưu liên tiếp thành một lần xóa). */
const VGARCO_CACHE_PURGE_DELAY = 30;

/** Các kiểu nội dung công khai: đổi chúng thì HTML ở biên đã cũ. */
function vgarco_cache_public_types(): array {
	return array( 'page', 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri' );
}

/**
 * Chuyển trạng thái này có làm HTML công khai đổi không? Có khi nội dung đang/đã xuất bản thay đổi:
 * xuất bản mới, cập nhật bài đã xuất bản, gỡ khỏi xuất bản, xóa. Bản nháp → bản nháp, bản tự động lưu, hồ sơ ứng tuyển thì không.
 */
function vgarco_cache_relevant_change( string $post_type, string $new_status, string $old_status ): bool {
	if ( ! in_array( $post_type, vgarco_cache_public_types(), true ) ) {
		return false;
	}
	return 'publish' === $new_status || 'publish' === $old_status;
}

/**
 * Yêu cầu API "xóa toàn bộ cache" của Cloudflare. Token chỉ cần quyền Zone → Cache Purge trên một zone.
 *
 * @return array{url: string, args: array}|null null nếu thiếu zone hoặc token.
 */
function vgarco_cf_purge_request( string $zone, string $token ): ?array {
	$zone  = trim( $zone );
	$token = trim( $token );
	if ( ! preg_match( '/^[a-f0-9]{32}$/i', $zone ) || '' === $token || preg_match( '/[\s\r\n]/', $token ) ) {
		return null; // ID zone của Cloudflare là 32 ký tự hex; token có khoảng trắng/xuống dòng là cấu hình hỏng (và có thể chèn header)
	}
	return array(
		'url'  => 'https://api.cloudflare.com/client/v4/zones/' . strtolower( $zone ) . '/purge_cache',
		'args' => array(
			'method'  => 'POST',
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => '{"purge_everything":true}',
		),
	);
}

/**
 * Đọc kết quả trả về của Cloudflare.
 *
 * @param int    $status HTTP status.
 * @param string $body   Thân phản hồi.
 * @return array{ok: bool, message: string}
 */
function vgarco_cf_parse_response( int $status, string $body ): array {
	$data = json_decode( $body, true );
	if ( 200 === $status && is_array( $data ) && ! empty( $data['success'] ) ) {
		return array( 'ok' => true, 'message' => '' );
	}
	$error = is_array( $data ) && ! empty( $data['errors'][0]['message'] ) ? (string) $data['errors'][0]['message'] : '';
	return array( 'ok' => false, 'message' => "HTTP {$status}" . ( '' !== $error ? ': ' . $error : '' ) );
}

/**
 * Mốc 00:10 kế tiếp theo múi giờ của website (cron hằng ngày: hạn nộp hồ sơ đổi ngày nên HTML trong cache phải xóa).
 * Nếu đang đúng 00:10 thì lấy ngày hôm sau.
 *
 * @return int Timestamp UTC.
 */
function vgarco_next_daily_run( int $now, string $timezone, int $hour = 0, int $minute = 10 ): int {
	$tz    = new DateTimeZone( $timezone );
	$local = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz );
	$next  = $local->setTime( $hour, $minute, 0 );
	if ( $next->getTimestamp() <= $now ) {
		$next = $next->modify( '+1 day' )->setTime( $hour, $minute, 0 );
	}
	return $next->getTimestamp();
}

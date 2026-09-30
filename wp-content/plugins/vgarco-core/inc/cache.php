<?php
/**
 * Xóa cache Cloudflare (mục 8.8): khi nội dung công khai thay đổi hoặc lưu trang Cấu hình → sau 30 giây xóa TOÀN BỘ cache;
 * cron 00:10 mỗi ngày cũng xóa (hạn nộp hồ sơ đổi ngày nên HTML cũ phải biến mất). Quyết định và yêu cầu API nằm ở cache-lib.php (có kiểm thử).
 *
 * Cấu hình trong wp-config.php (không lưu ở CSDL, không vào Git):
 *   define( 'VGARCO_CF_ZONE',  '<zone id 32 ký tự>' );
 *   define( 'VGARCO_CF_TOKEN', '<API token chỉ có quyền Zone → Cache Purge>' );
 * Thiếu một trong hai thì không làm gì (local, staging).
 *
 * @package vgarco-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lên lịch xóa cache sau 30 giây. Nhiều thay đổi liên tiếp (lưu nhiều bài, lưu trang Cấu hình nhiều ô) chỉ tạo MỘT lần xóa:
 * đã có lịch chưa chạy thì không đặt thêm.
 */
function vgarco_cache_schedule_purge(): void {
	if ( ! defined( 'VGARCO_CF_ZONE' ) || ! defined( 'VGARCO_CF_TOKEN' ) ) {
		return;
	}
	if ( ! wp_next_scheduled( 'vgarco_cf_purge' ) ) {
		wp_schedule_single_event( time() + VGARCO_CACHE_PURGE_DELAY, 'vgarco_cf_purge' );
	}
}

add_action(
	'transition_post_status',
	static function ( string $new_status, string $old_status, WP_Post $post ): void {
		if ( vgarco_cache_relevant_change( $post->post_type, $new_status, $old_status ) ) {
			vgarco_cache_schedule_purge();
		}
	},
	10,
	3
);

// Sửa bài đã xuất bản (không đổi trạng thái) vẫn là transition_post_status publish→publish; xóa bài thì bắt ở deleted_post.
add_action(
	'deleted_post',
	static function ( int $post_id, WP_Post $post ): void {
		if ( vgarco_cache_relevant_change( $post->post_type, 'trash', 'publish' ) ) {
			vgarco_cache_schedule_purge();
		}
	},
	10,
	2
);

// Lưu trang Cấu hình (ACF options) và đổi loại công trình/chuyên mục đều làm HTML công khai đổi.
add_action(
	'acf/save_post',
	static function ( $post_id ): void {
		if ( 'options' === $post_id ) {
			vgarco_cache_schedule_purge();
		}
	},
	30
);
add_action(
	'edited_term',
	static function ( $term_id, $tt_id, string $taxonomy ): void {
		if ( in_array( $taxonomy, array( 'loai_cong_trinh', 'chuyen_muc', 'noi_lam_viec', 'phong_ban' ), true ) ) {
			vgarco_cache_schedule_purge();
		}
	},
	10,
	3
);

/**
 * Gọi API Cloudflare xóa toàn bộ cache. Lỗi thì ghi cảnh báo quản trị (không ném ngoại lệ: chạy trong cron).
 *
 * @return bool Có xóa được không. false cũng khi chưa cấu hình.
 */
function vgarco_cf_purge(): bool {
	$request = vgarco_cf_purge_request( defined( 'VGARCO_CF_ZONE' ) ? (string) VGARCO_CF_ZONE : '', defined( 'VGARCO_CF_TOKEN' ) ? (string) VGARCO_CF_TOKEN : '' );
	if ( ! $request ) {
		return false;
	}

	$response = wp_remote_post( $request['url'], $request['args'] );
	if ( is_wp_error( $response ) ) {
		vgarco_alert( 'Xóa cache Cloudflare thất bại: ' . $response->get_error_message() );
		return false;
	}

	$result = vgarco_cf_parse_response( (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ) );
	if ( ! $result['ok'] ) {
		vgarco_alert( 'Xóa cache Cloudflare thất bại (' . $result['message'] . '). Kiểm tra token có quyền Cache Purge và ID zone.' );
	}
	return $result['ok'];
}
add_action( 'vgarco_cf_purge', 'vgarco_cf_purge' );

// Cron 00:10 hằng ngày theo múi giờ website: hạn nộp hồ sơ đổi ngày, HTML trong cache phải xóa.
add_action( 'vgarco_cf_purge_daily', 'vgarco_cf_purge' );
add_action(
	'init',
	static function (): void {
		if ( defined( 'VGARCO_CF_ZONE' ) && defined( 'VGARCO_CF_TOKEN' ) && ! wp_next_scheduled( 'vgarco_cf_purge_daily' ) ) {
			wp_schedule_event( vgarco_next_daily_run( time(), wp_timezone_string() ), 'daily', 'vgarco_cf_purge_daily' );
		}
	}
);

<?php

defined( 'ABSPATH' ) || exit;

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

add_action( 'vgarco_cf_purge_daily', 'vgarco_cf_purge' );
add_action(
	'init',
	static function (): void {
		if ( defined( 'VGARCO_CF_ZONE' ) && defined( 'VGARCO_CF_TOKEN' ) && ! wp_next_scheduled( 'vgarco_cf_purge_daily' ) ) {
			wp_schedule_event( vgarco_next_daily_run( time(), wp_timezone_string() ), 'daily', 'vgarco_cf_purge_daily' );
		}
	}
);

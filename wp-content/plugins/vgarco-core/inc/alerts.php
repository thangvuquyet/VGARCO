<?php

defined( 'ABSPATH' ) || exit;

function vgarco_alert( string $message, bool $urgent = false ): void {
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
		wp_mail( (string) get_option( 'admin_email' ), '[VGARCO] Cảnh báo hệ thống', $message );
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

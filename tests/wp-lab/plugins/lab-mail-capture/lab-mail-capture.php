<?php
/**
 * Plugin Name: LAB — bắt thư và gọi Cloudflare giả
 * Description: CHỈ DÙNG TRONG MÔI TRƯỜNG THỬ. Không gửi thư thật: ghi mọi thư của wp_mail() vào mail.log (option lab_mail_fail = 1 giả lập SMTP hỏng).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_wp_mail',
	static function ( $short, array $atts ) {
		$entry = array(
			'time'    => gmdate( 'c' ),
			'to'      => $atts['to'],
			'subject' => $atts['subject'],
			'message' => $atts['message'],
			'headers' => $atts['headers'],
			'attach'  => $atts['attachments'],
		);
		file_put_contents( LAB_DIR . '/mail.log', json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n", FILE_APPEND );
		return get_option( 'lab_mail_fail' ) ? false : true;
	},
	10,
	2
);

add_filter(
	'pre_http_request',
	static function ( $pre, array $args, string $url ) {
		if ( ! str_contains( $url, 'api.cloudflare.com' ) && ! str_contains( $url, 'api.telegram.org' ) ) {
			return $pre;
		}
		file_put_contents( LAB_DIR . '/http.log', json_encode( array( 'url' => $url, 'args' => $args ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n", FILE_APPEND );
		$fail = (bool) get_option( 'lab_cf_fail' );
		return array(
			'headers'  => array(),
			'body'     => $fail ? '{"success":false,"errors":[{"code":10000,"message":"Authentication error"}]}' : '{"success":true,"result":{"id":"lab"}}',
			'response' => array( 'code' => $fail ? 403 : 200, 'message' => $fail ? 'Forbidden' : 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

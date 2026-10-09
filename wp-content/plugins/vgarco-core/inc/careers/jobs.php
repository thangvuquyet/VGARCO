<?php

defined( 'ABSPATH' ) || exit;

function vgarco_job_is_open( int $post_id ): bool {
	if ( 'vi_tri' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		return false;
	}
	if ( 'da_dong' === (string) get_field( 'trang_thai', $post_id ) ) {
		return false;
	}
	$deadline = trim( (string) get_field( 'han_nop', $post_id ) );
	return '' === $deadline || $deadline >= wp_date( 'Ymd' );
}

function vgarco_job_type_label( string $type ): string {
	$labels = array(
		'toan_thoi_gian' => __( 'Toàn thời gian', 'vgarco-core' ),
		'ban_thoi_gian'  => __( 'Bán thời gian', 'vgarco-core' ),
		'thuc_tap'       => __( 'Thực tập', 'vgarco-core' ),
	);
	return $labels[ $type ] ?? $labels['toan_thoi_gian'];
}

function vgarco_fold( string $text ): string {
	return mb_strtolower( remove_accents( $text ) );
}

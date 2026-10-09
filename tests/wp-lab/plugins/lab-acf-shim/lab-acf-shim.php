<?php
/**
 * Plugin Name: LAB — ACF PRO shim
 * Description: CHỈ DÙNG TRONG MÔI TRƯỜNG THỬ. Giả lập phần nhỏ của ACF PRO mà vgarco-core dùng (get_field, update_field, nhóm trường cục bộ,
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['lab_acf_groups']  = array();
$GLOBALS['lab_acf_options'] = array();

function lab_acf_option_key( string $name ): string {
	return 'lab_acf_option_' . $name;
}

function lab_acf_target( $post_id ): array {
	if ( 'option' === $post_id || 'options' === $post_id ) {
		return array( 'option', 0 );
	}
	if ( false === $post_id || null === $post_id ) {
		$post_id = (int) get_queried_object_id() ?: (int) get_the_ID();
	}
	return array( 'post', (int) $post_id );
}

function get_field( $selector, $post_id = false, $format_value = true ) {
	[ $kind, $id ] = lab_acf_target( $post_id );
	if ( 'option' === $kind ) {
		$value = get_option( lab_acf_option_key( (string) $selector ), null );
		return $value;
	}
	if ( $id <= 0 || ! metadata_exists( 'post', $id, (string) $selector ) ) {
		return null;
	}
	$value = get_post_meta( $id, (string) $selector, true );
	return $value;
}

function update_field( $selector, $value, $post_id = false ) {
	[ $kind, $id ] = lab_acf_target( $post_id );
	if ( 'option' === $kind ) {
		return update_option( lab_acf_option_key( (string) $selector ), $value, false );
	}
	return (bool) update_post_meta( $id, (string) $selector, $value );
}

function acf_add_local_field_group( array $group ) {
	$GLOBALS['lab_acf_groups'][ $group['key'] ?? count( $GLOBALS['lab_acf_groups'] ) ] = $group;
	return true;
}

function acf_add_options_page( array $args ) {
	$GLOBALS['lab_acf_options'][ $args['menu_slug'] ] = $args;
	add_action(
		'admin_menu',
		static function () use ( $args ): void {
			add_menu_page(
				(string) $args['page_title'],
				(string) $args['menu_title'],
				(string) ( $args['capability'] ?? 'manage_options' ),
				(string) $args['menu_slug'],
				static function () use ( $args ): void {
					echo '<div class="wrap"><h1>' . esc_html( $args['page_title'] ) . '</h1><p>LAB: trang cấu hình giả lập (ACF PRO shim). Giá trị đặt bằng update_field(..., \'option\').</p></div>';
				},
				(string) ( $args['icon_url'] ?? '' ),
				$args['position'] ?? null
			);
		}
	);
	return $args;
}

add_action(
	'init',
	static function (): void {
		do_action( 'acf/init' );
		do_action( 'acf/include_fields' );
	},
	5
);

add_action(
	'save_post',
	static function ( int $post_id ): void {
		do_action( 'acf/save_post', $post_id );
	},
	20
);

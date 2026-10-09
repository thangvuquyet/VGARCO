<?php

defined( 'ABSPATH' ) || exit;

function vgarco_field_text( string $name, string $default = '', ?int $post_id = null ): string {
	$value = function_exists( 'get_field' ) ? get_field( $name, $post_id ?? (int) get_queried_object_id() ) : null;
	$value = is_string( $value ) ? trim( $value ) : '';
	return '' !== $value ? $value : $default;
}

function vgarco_field_heading( string $name, string $default, ?int $post_id = null ): string {
	return vgarco_heading( vgarco_field_text( $name, $default, $post_id ) );
}

function vgarco_field_lines( string $name, string $default, ?int $post_id = null ): string {
	return vgarco_lines( vgarco_field_text( $name, $default, $post_id ) );
}

function vgarco_field_image( string $name, ?int $post_id = null ): int {
	$value = function_exists( 'get_field' ) ? get_field( $name, $post_id ?? (int) get_queried_object_id() ) : null;
	return is_numeric( $value ) ? (int) $value : 0;
}

function vgarco_field_rows( string $name, array $keys, array $defaults = array(), ?int $post_id = null ): array {
	$saved = function_exists( 'get_field' ) ? get_field( $name, $post_id ?? (int) get_queried_object_id() ) : null;
	$rows  = array();

	foreach ( is_array( $saved ) ? $saved : array() as $row ) {
		$clean = array();
		foreach ( $keys as $key ) {
			$value         = $row[ $key ] ?? '';
			$clean[ $key ] = is_string( $value ) ? trim( $value ) : $value;
		}
		if ( array_filter( $clean, static fn( $v ) => '' !== $v && null !== $v && 0 !== $v ) ) {
			$rows[] = $clean;
		}
	}

	return $rows ?: $defaults;
}

function vgarco_split_lines( string $text ): array {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ?: array() ), static fn( $l ) => '' !== $l ) );
}

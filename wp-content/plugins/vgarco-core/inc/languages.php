<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_DEFAULT_LANGUAGE = 'vi';

function vgarco_secondary_languages(): array {
	return array( 'de' );
}

function vgarco_current_language(): string {
	$code = apply_filters( 'wpml_current_language', null );
	return is_string( $code ) && in_array( $code, vgarco_secondary_languages(), true ) ? $code : VGARCO_DEFAULT_LANGUAGE;
}

function vgarco_translation_rules(): array {
	static $rules = null;
	if ( null !== $rules ) {
		return $rules;
	}
	$collect = static function ( array $fields ) use ( &$collect ): array {
		$out = array();
		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] ) {
				continue;
			}
			$entry = array(
				'type' => $field['type'],
				'pref' => (int) ( $field['wpml_cf_preferences'] ?? 0 ),
			);
			if ( ! empty( $field['sub_fields'] ) ) {
				$entry['subs'] = $collect( $field['sub_fields'] );
			}
			$out[ $field['name'] ] = $entry;
		}
		return $out;
	};
	$rules = array();
	foreach ( vgarco_field_groups() as $group ) {
		$rules = array_merge( $rules, $collect( $group['fields'] ) );
	}
	return $rules;
}

function vgarco_entry_copies( array $entry, bool $sync ): bool {
	foreach ( $entry['subs'] ?? array() as $sub ) {
		if ( vgarco_entry_copies( $sub, $sync ) ) {
			return true;
		}
	}
	return empty( $entry['subs'] ) && ( 1 === $entry['pref'] || ( ! $sync && 3 === $entry['pref'] ) );
}

function vgarco_entry_translates( array $entry ): bool {
	foreach ( $entry['subs'] ?? array() as $sub ) {
		if ( vgarco_entry_translates( $sub ) ) {
			return true;
		}
	}
	return empty( $entry['subs'] ) && 2 === $entry['pref'];
}

function vgarco_meta_verdict( string $key, array $rules, bool $sync ): ?bool {
	foreach ( $rules as $name => $entry ) {
		if ( $key === $name ) {
			return vgarco_entry_copies( $entry, $sync );
		}
		if ( empty( $entry['subs'] ) || ! str_starts_with( $key, $name . '_' ) ) {
			continue;
		}
		$rest = substr( $key, strlen( $name ) + 1 );
		if ( 'repeater' === $entry['type'] ) {
			if ( ! preg_match( '/^\d+_(.+)$/', $rest, $match ) ) {
				continue;
			}
			$rest = $match[1];
		}
		$verdict = vgarco_meta_verdict( $rest, $entry['subs'], $sync );
		if ( null !== $verdict ) {
			return $verdict;
		}
	}
	return null;
}

function vgarco_copy_post_metas( $keys, $sync = false ): array {
	$rules = vgarco_translation_rules();
	$out   = array();
	foreach ( (array) $keys as $key ) {
		$verdict = vgarco_meta_verdict( (string) $key, $rules, (bool) $sync );
		if ( null === $verdict ) {
			$out[] = $key;
		} elseif ( $verdict ) {
			$out[] = $key;
			$out[] = '_' . $key;
		}
	}
	return array_values( array_unique( $out ) );
}
add_filter( 'pll_copy_post_metas', 'vgarco_copy_post_metas', 30, 2 );

function vgarco_set_post_language( int $post_id, string $lang ): void {
	if ( function_exists( 'pll_set_post_language' ) ) {
		pll_set_post_language( $post_id, $lang );
	}
}

function vgarco_link_translation( int $source_id, int $translated_id, string $lang ): void {
	if ( ! function_exists( 'pll_save_post_translations' ) ) {
		return;
	}
	$group = (array) pll_get_post_translations( $source_id );
	if ( ! $group ) {
		vgarco_set_post_language( $source_id, VGARCO_DEFAULT_LANGUAGE );
		$group = array( VGARCO_DEFAULT_LANGUAGE => $source_id );
	}
	if ( ( $group[ $lang ] ?? 0 ) === $translated_id ) {
		return;
	}
	$group[ $lang ] = $translated_id;
	pll_save_post_translations( $group );
}

function vgarco_translated_blank( array $entry, $value ): bool {
	if ( empty( $entry['subs'] ) ) {
		return ! vgarco_entry_translates( $entry ) || null === $value || '' === $value || false === $value || array() === $value;
	}
	$rows = 'repeater' === $entry['type'] ? (array) $value : array( (array) $value );
	foreach ( $rows as $row ) {
		foreach ( $entry['subs'] as $name => $sub ) {
			if ( ! vgarco_translated_blank( $sub, ( (array) $row )[ $name ] ?? null ) ) {
				return false;
			}
		}
	}
	return true;
}

function vgarco_merge_translation( array $entry, $base, $other ) {
	if ( empty( $entry['subs'] ) ) {
		return vgarco_entry_translates( $entry ) ? $other : $base;
	}
	$merge_row = static function ( array $row_base, array $row_other ) use ( $entry ): array {
		$row = array();
		foreach ( $entry['subs'] as $name => $sub ) {
			$row[ $name ] = vgarco_merge_translation( $sub, $row_base[ $name ] ?? null, $row_other[ $name ] ?? null );
		}
		return $row;
	};
	if ( 'repeater' !== $entry['type'] ) {
		return $merge_row( (array) $base, (array) $other );
	}
	if ( vgarco_translated_blank( $entry, $other ) ) {
		return array();
	}
	$other_rows = array_values( (array) $other );
	$rows       = array();
	foreach ( array_values( (array) $base ) as $i => $row ) {
		$rows[] = $merge_row( (array) $row, (array) ( $other_rows[ $i ] ?? array() ) );
	}
	return $rows;
}

function vgarco_localize_option( string $root, $base ) {
	$lang = vgarco_current_language();
	$rule = vgarco_translation_rules()[ $root ] ?? null;
	if ( VGARCO_DEFAULT_LANGUAGE === $lang || ! $rule || ! vgarco_entry_translates( $rule ) || ! function_exists( 'get_field' ) ) {
		return $base;
	}
	return vgarco_merge_translation( $rule, $base, get_field( $root, 'options_' . $lang ) );
}

function vgarco_prune_translatable( array $fields, string $suffix ): array {
	$out = array();
	foreach ( $fields as $field ) {
		if ( ! empty( $field['sub_fields'] ) ) {
			$subs = vgarco_prune_translatable( $field['sub_fields'], $suffix );
			if ( ! $subs ) {
				continue;
			}
			$field['sub_fields'] = $subs;
		} elseif ( 2 !== (int) ( $field['wpml_cf_preferences'] ?? 0 ) ) {
			continue;
		}
		$field['key'] .= $suffix;
		if ( isset( $field['required'] ) ) {
			$field['required'] = 0;
		}
		$out[] = $field;
	}
	return $out;
}

function vgarco_option_translation_groups( string $lang ): array {
	$groups = array();
	foreach ( vgarco_fields_cau_hinh() as $group ) {
		$fields = vgarco_prune_translatable( $group['fields'], '_' . $lang );
		if ( ! $fields ) {
			continue;
		}
		$group['key']      .= '_' . $lang;
		$group['title']    .= ' — ' . strtoupper( $lang );
		$group['fields']    = $fields;
		$group['location']  = vgarco_location_options_page( 'vgarco-cau-hinh-' . $lang );
		$groups[]           = $group;
	}
	return $groups;
}

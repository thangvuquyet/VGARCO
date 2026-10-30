<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_DEFAULT_LANGUAGE = 'vi';

function vgarco_secondary_languages(): array {
	return array( 'de' );
}

function vgarco_translatable_post_types(): array {
	return array( 'du_an', 'linh_vuc', 'tin_tuc', 'vi_tri' );
}

function vgarco_translatable_taxonomies(): array {
	return array( 'loai_cong_trinh', 'chuyen_muc', 'noi_lam_viec', 'phong_ban' );
}

function vgarco_polylang_types( $types, $hide ) {
	$ours = array_combine( vgarco_translatable_post_types(), vgarco_translatable_post_types() );
	return $hide ? array_diff_key( (array) $types, $ours ) : array_merge( (array) $types, $ours );
}
add_filter( 'pll_get_post_types', 'vgarco_polylang_types', 10, 2 );

function vgarco_polylang_taxonomies( $taxonomies, $hide ) {
	$ours = array_combine( vgarco_translatable_taxonomies(), vgarco_translatable_taxonomies() );
	return $hide ? array_diff_key( (array) $taxonomies, $ours ) : array_merge( (array) $taxonomies, $ours );
}
add_filter( 'pll_get_taxonomies', 'vgarco_polylang_taxonomies', 10, 2 );

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

function vgarco_post_language( int $post_id ): ?string {
	$lang = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post_id ) : null;
	return is_string( $lang ) && '' !== $lang ? $lang : null;
}

function vgarco_translation_id( int $post_id, string $lang ): int {
	return function_exists( 'pll_get_post' ) ? (int) pll_get_post( $post_id, $lang ) : 0;
}

function vgarco_term_ids_in_language( array $slugs, string $taxonomy, string $lang ): array {
	$ids     = array();
	$missing = array();
	foreach ( $slugs as $slug ) {
		$term    = get_term_by( 'slug', $slug, $taxonomy );
		$term_id = $term ? (int) $term->term_id : 0;
		if ( $term_id && VGARCO_DEFAULT_LANGUAGE !== $lang && function_exists( 'pll_get_term' ) ) {
			$term_id = (int) pll_get_term( $term_id, $lang );
		}
		if ( $term_id ) {
			$ids[] = $term_id;
		} else {
			$missing[] = $slug;
		}
	}
	return array( 'ids' => $ids, 'missing' => $missing );
}

function vgarco_term_slugs_in_default_language( int $post_id, string $taxonomy ): array {
	$slugs = array();
	foreach ( (array) wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) ) as $term_id ) {
		$source = function_exists( 'pll_get_term' ) ? (int) pll_get_term( (int) $term_id, VGARCO_DEFAULT_LANGUAGE ) : (int) $term_id;
		$term   = $source ? get_term( $source, $taxonomy ) : null;
		if ( $term instanceof WP_Term ) {
			$slugs[] = $term->slug;
		}
	}
	sort( $slugs );
	return $slugs;
}

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
	return vgarco_merge_translation( $rule, $base, get_field( vgarco_translated_option_name( $root, $lang ), 'option' ) );
}

function vgarco_translated_option_name( string $root, string $lang ): string {
	return $root . '_' . $lang;
}

function vgarco_prune_translatable( array $fields, string $suffix, bool $top = true ): array {
	$out = array();
	foreach ( $fields as $field ) {
		if ( ! empty( $field['sub_fields'] ) ) {
			$subs = vgarco_prune_translatable( $field['sub_fields'], $suffix, false );
			if ( ! $subs ) {
				continue;
			}
			$field['sub_fields'] = $subs;
		} elseif ( 2 !== (int) ( $field['wpml_cf_preferences'] ?? 0 ) ) {
			continue;
		}
		$field['key'] .= $suffix;
		if ( $top ) {
			$field['name'] = vgarco_translated_option_name( $field['name'], ltrim( $suffix, '_' ) );
		}
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

function vgarco_language_presets(): array {
	return array(
		array( 'locale' => 'vi', 'term_group' => 0 ),
		array( 'locale' => 'de_DE', 'term_group' => 1 ),
	);
}

function vgarco_language_settings(): array {
	return array(
		'default_lang'  => VGARCO_DEFAULT_LANGUAGE,
		'hide_default'  => true,
		'force_lang'    => 1,
		'rewrite'       => true,
		'browser'       => false,
		'redirect_lang' => true,
		'media_support' => false,
	);
}

function vgarco_setup_languages(): array {
	if ( ! function_exists( 'PLL' ) || ! PLL() || ! isset( PLL()->model->languages ) ) {
		return array( 'ok' => false, 'log' => array( 'Polylang chưa được kích hoạt.' ) );
	}
	$log   = array();
	$slugs = array_map( static fn( $language ) => $language->slug, (array) PLL()->model->languages->get_list() );
	foreach ( vgarco_language_presets() as $preset ) {
		$slug = strtok( $preset['locale'], '_' );
		if ( in_array( $slug, $slugs, true ) ) {
			$log[] = "= ngôn ngữ {$slug}: đã có";
			continue;
		}
		$result = PLL()->model->languages->add( $preset );
		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'log' => array_merge( $log, array( "Không tạo được {$slug}: " . $result->get_error_message() ) ) );
		}
		$log[] = "+ ngôn ngữ {$slug}";
	}
	foreach ( vgarco_language_settings() as $key => $value ) {
		if ( PLL()->options[ $key ] === $value ) {
			continue;
		}
		$result = PLL()->options->set( $key, $value );
		if ( $result->has_errors() ) {
			return array( 'ok' => false, 'log' => array_merge( $log, array( "Không đặt được {$key}: " . $result->get_error_message() ) ) );
		}
		$log[] = "~ cài đặt {$key}";
	}
	PLL()->options->save();
	PLL()->model->languages->clean_cache();
	PLL()->model->set_language_in_mass();
	$log[] = '~ nội dung chưa có ngôn ngữ được gán tiếng Việt';
	flush_rewrite_rules();
	return array( 'ok' => true, 'log' => $log );
}

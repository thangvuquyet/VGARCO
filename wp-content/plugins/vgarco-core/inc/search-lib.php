<?php

defined( 'ABSPATH' ) || exit;

const VGARCO_SEARCH_MIN_CHARS = 3;
const VGARCO_SEARCH_MAX_CHARS = 100;
const VGARCO_SEARCH_LIMIT     = 10;

function vgarco_search_normalize( string $text ): string {
	$text = preg_replace( '/\s+/u', ' ', trim( $text ) ) ?? '';
	return trim( vgarco_fold( mb_substr( $text, 0, VGARCO_SEARCH_MAX_CHARS ) ) );
}

function vgarco_search_prepare( array $item ): array {
	$item['fold_title'] = vgarco_search_normalize( (string) $item['title'] );
	$item['fold_all']   = vgarco_search_normalize(
		implode( ' ', array_merge( array( (string) $item['title'], (string) $item['location'], (string) $item['years'] ), array_map( 'strval', (array) $item['types'] ) ) )
	);
	return $item;
}

function vgarco_search_rank( array $index, string $query, int $limit = VGARCO_SEARCH_LIMIT ): array {
	$folded = vgarco_search_normalize( $query );
	if ( mb_strlen( $folded ) < VGARCO_SEARCH_MIN_CHARS ) {
		return array();
	}

	$words = explode( ' ', $folded );
	$first = $words[0];

	$scored = array();
	foreach ( array_values( $index ) as $position => $item ) {
		foreach ( $words as $word ) {
			if ( ! str_contains( $item['fold_all'], $word ) ) {
				continue 2;
			}
		}

		$rank = 2;
		if ( str_starts_with( $item['fold_title'], $folded ) ) {
			$rank = 0;
		} else {
			foreach ( explode( ' ', $item['fold_title'] ) as $title_word ) {
				if ( str_starts_with( $title_word, $first ) ) {
					$rank = 1;
					break;
				}
			}
		}
		$scored[] = array( $rank, $position, $item );
	}

	usort(
		$scored,
		static fn( array $a, array $b ): int => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] )
	);

	$results = array();
	foreach ( array_slice( $scored, 0, max( 0, $limit ) ) as $entry ) {
		$item      = $entry[2];
		$results[] = array(
			'title'    => (string) $item['title'],
			'location' => (string) $item['location'],
			'years'    => (string) $item['years'],
			'url'      => (string) $item['url'],
			'thumb'    => (string) $item['thumb'],
		);
	}
	return $results;
}

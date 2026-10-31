<?php
/**
 * Keyword filter: decides which feed entries are worth sending to Claude.
 *
 * Rules:
 * - An exclude keyword (global or the feed's own) anywhere in the title or summary skips the entry.
 * - Include keywords: the feed's own list if it has one, otherwise the global list. At least one must
 *   appear. With no include keywords at all, everything passes.
 * Matching ignores case and accents and only matches whole words or phrases.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Filter {

	/**
	 * @return array{0: bool, 1: string} [passes, reason]
	 */
	public static function check( $title, $summary, array $feed ) {
		$text = self::normalize( $title . ' ' . $summary );

		$exclude = array_merge(
			BHNA_Settings::keyword_list( BHNA_Settings::get( 'exclude_keywords' ) ),
			BHNA_Settings::keyword_list( isset( $feed['exclude'] ) ? $feed['exclude'] : '' )
		);
		foreach ( $exclude as $word ) {
			if ( self::contains( $text, $word ) ) {
				return array( false, sprintf( 'Palabra excluida: "%s"', $word ) );
			}
		}

		// A feed with its own include list uses only that list (e.g. to split one feed into sections).
		$include = BHNA_Settings::keyword_list( isset( $feed['include'] ) ? $feed['include'] : '' );
		if ( empty( $include ) ) {
			$include = BHNA_Settings::keyword_list( BHNA_Settings::get( 'include_keywords' ) );
		}
		if ( empty( $include ) ) {
			return array( true, '' );
		}
		foreach ( $include as $word ) {
			if ( self::contains( $text, $word ) ) {
				return array( true, sprintf( 'Coincide con "%s"', $word ) );
			}
		}
		return array( false, 'Sin palabras clave de interés' );
	}

	public static function normalize( $text ) {
		$text = wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
		$text = remove_accents( $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		return preg_replace( '/\s+/u', ' ', $text );
	}

	public static function contains( $normalized_text, $keyword ) {
		$keyword = self::normalize( $keyword );
		if ( '' === trim( $keyword ) ) {
			return false;
		}
		$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( trim( $keyword ), '/' ) . '(?![\p{L}\p{N}])/u';
		return (bool) preg_match( $pattern, $normalized_text );
	}
}

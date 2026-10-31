<?php
/**
 * Keyword filter: decides which feed entries are worth sending to Claude.
 *
 * Rules (global lists plus the feed's own lists):
 * - An exclude keyword anywhere in the title or summary skips the entry.
 * - If there are include keywords, at least one must appear. With no include keywords, everything passes.
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

		$include = array_merge(
			BHNA_Settings::keyword_list( BHNA_Settings::get( 'include_keywords' ) ),
			BHNA_Settings::keyword_list( isset( $feed['include'] ) ? $feed['include'] : '' )
		);
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

<?php
/**
 * Automatic quality check: flags drafts where figures or proper names from the source
 * do not appear in Claude's rewrite. It never blocks a draft; editors see a warning instead.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Quality {

	/**
	 * @return string[] Figures and names from the source that are missing in the rewrite.
	 */
	public static function missing( $source_text, $rewritten_text ) {
		$source    = wp_strip_all_tags( (string) $source_text );
		$rewritten = BHNA_Filter::normalize( $rewritten_text );
		$digits    = self::numbers( $rewritten );
		$missing   = array();

		foreach ( self::numbers( $source ) as $number ) {
			if ( ! in_array( $number, $digits, true ) ) {
				$missing[] = $number;
			}
		}

		foreach ( self::names( $source ) as $name ) {
			$normalized = BHNA_Filter::normalize( $name );
			if ( false !== strpos( $rewritten, $normalized ) ) {
				continue;
			}
			// Accept the surname alone ("Sánchez" for "Pedro Sánchez").
			$words = explode( ' ', $normalized );
			if ( count( $words ) > 1 && BHNA_Filter::contains( $rewritten, end( $words ) ) ) {
				continue;
			}
			$missing[] = $name;
		}

		return array_values( array_unique( $missing ) );
	}

	/** Numbers with thousand/decimal separators removed, so "1.500" and "1500" match. */
	public static function numbers( $text ) {
		preg_match_all( '/\d+(?:[.,]\d+)*/u', $text, $matches );
		$numbers = array();
		foreach ( $matches[0] as $match ) {
			$numbers[] = str_replace( array( '.', ',' ), '', $match );
		}
		return array_values( array_unique( $numbers ) );
	}

	/** Sequences of two or more capitalised words, allowing Spanish connectors ("Congreso de los Diputados"). */
	public static function names( $text ) {
		$pattern = '/\p{Lu}[\p{Ll}\p{Lu}]+(?:\s+(?:(?:de|del|la|las|los|y)\s+)*\p{Lu}[\p{Ll}\p{Lu}]+)+/u';
		preg_match_all( $pattern, $text, $matches );

		$names = array();
		foreach ( $matches[0] as $name ) {
			// A capitalised word at the start of a sentence ("El Gobierno…") is not part of the name.
			$name = preg_replace( '/^(?:El|La|Los|Las|Un|Una|En|Con|Por|Para|Según|Tras|Desde)\s+/u', '', $name );
			if ( false !== strpos( $name, ' ' ) ) {
				$names[] = $name;
			}
		}
		return array_values( array_unique( $names ) );
	}
}

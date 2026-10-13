<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * Internal links stay correct when the permalink structure changes.
 *
 * Links saved while WordPress used "Plain" permalinks look like https://site/?page_id=12.
 * At render time they are turned into the page's current permalink (/la-maison/), so menus,
 * buttons and text links are always clean and the active-menu underline keeps working.
 */
final class Links {

	public static function url( $url ) {
		$url = (string) $url;
		if ( '' === $url || ! preg_match( '/[?&](page_id|p)=(\d+)/', $url, $m ) ) {
			return $url;
		}
		$u    = wp_parse_url( $url );
		$home = wp_parse_url( home_url( '/' ) );
		if ( ! empty( $u['host'] ) && ( empty( $home['host'] ) || strtolower( $u['host'] ) !== strtolower( $home['host'] ) ) ) {
			return $url; // Another site.
		}
		$path      = isset( $u['path'] ) ? rtrim( $u['path'], '/' ) : '';
		$home_path = isset( $home['path'] ) ? rtrim( $home['path'], '/' ) : '';
		if ( $path !== $home_path && $path !== $home_path . '/index.php' ) {
			return $url;
		}
		$link = get_permalink( (int) $m[2] );
		if ( ! $link ) {
			return $url;
		}
		return $link . ( isset( $u['fragment'] ) ? '#' . $u['fragment'] : '' );
	}

	/** Same, for every href inside a piece of HTML (rich text, privacy notice…). */
	public static function html( $html ) {
		return preg_replace_callback(
			'/href=(["\'])([^"\']*)\1/i',
			function ( $m ) {
				$url = self::url( html_entity_decode( $m[2], ENT_QUOTES ) );
				return 'href=' . $m[1] . esc_url( $url ) . $m[1];
			},
			(string) $html
		);
	}
}

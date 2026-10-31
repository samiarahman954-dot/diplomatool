<?php
/**
 * Admin interface language (Spanish / English), chosen per user.
 *
 * Spanish is the source language: every string in the code, every stored log entry and every
 * status reason is written in Spanish. When a user picks English, strings are looked up in
 * includes/lang/en.php at display time. Messages built with sprintf() (e.g. 'Coincide con "%s"')
 * are matched against the Spanish formats and rebuilt with the English ones, so stored entries
 * translate too.
 *
 * Only the admin interface changes. Content for readers and editors (Claude's articles,
 * attribution, Amazon box, Telegram/email/Slack alerts) stays in Spanish.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_I18N {

	const META      = 'bhna_ui_lang';
	const LANGUAGES = array(
		'es' => 'ES',
		'en' => 'EN',
	);

	private static $lang;
	private static $dictionary;
	private static $patterns;

	public static function init() {
		add_action( 'admin_post_bhna_set_lang', array( __CLASS__, 'handle_set_lang' ) );
	}

	/** Current user's choice; defaults to English only when the user's WordPress language is English. */
	public static function lang() {
		if ( null !== self::$lang ) {
			return self::$lang;
		}
		$lang    = '';
		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		if ( $user_id ) {
			$lang = (string) get_user_meta( $user_id, self::META, true );
		}
		if ( ! array_key_exists( $lang, self::LANGUAGES ) ) {
			$locale = $user_id && function_exists( 'get_user_locale' ) ? get_user_locale( $user_id ) : get_locale();
			$lang   = 0 === strpos( (string) $locale, 'en' ) ? 'en' : 'es';
		}
		// Cron and other requests without a user have no cache-worthy context.
		if ( $user_id ) {
			self::$lang = $lang;
		}
		return $lang;
	}

	public static function set_lang( $lang ) {
		self::$lang = $lang;
	}

	private static function dictionary() {
		if ( null === self::$dictionary ) {
			$file             = BHNA_DIR . 'includes/lang/en.php';
			self::$dictionary = file_exists( $file ) ? (array) include $file : array();
		}
		return self::$dictionary;
	}

	/** Translates a UI string. Unknown strings are returned unchanged. */
	public static function t( $text ) {
		if ( 'es' === self::lang() || ! is_string( $text ) || '' === $text ) {
			return $text;
		}
		$dictionary = self::dictionary();
		if ( defined( 'BHNA_I18N_COLLECT' ) && ! isset( $dictionary[ $text ] ) ) {
			$GLOBALS['bhna_i18n_missing'][ $text ] = true;
		}
		return isset( $dictionary[ $text ] ) ? $dictionary[ $text ] : $text;
	}

	/**
	 * Translates a stored or dynamic message: exact match first, then sprintf-style formats,
	 * line by line (notices can contain several lines).
	 */
	public static function message( $text ) {
		if ( 'es' === self::lang() || ! is_string( $text ) || '' === $text ) {
			return $text;
		}
		$lines = explode( "\n", $text );
		foreach ( $lines as &$line ) {
			$line = self::message_line( $line );
		}
		return implode( "\n", $lines );
	}

	private static function message_line( $line ) {
		$prefix = '';
		if ( 0 === strpos( $line, '• ' ) ) {
			$prefix = '• ';
			$line   = substr( $line, strlen( '• ' ) );
		}

		$dictionary = self::dictionary();
		if ( isset( $dictionary[ $line ] ) ) {
			return $prefix . $dictionary[ $line ];
		}

		foreach ( self::patterns() as $pattern => $english ) {
			if ( preg_match( $pattern, $line, $matches ) ) {
				array_shift( $matches );
				// Values inside a message can be messages themselves (e.g. an error reason).
				$values = array_map( array( __CLASS__, 'message_line' ), $matches );
				return $prefix . vsprintf( $english, $values );
			}
		}

		// "Ajustes guardados. Telegram: …" style: translate each sentence that matches.
		if ( false !== strpos( $line, '. ' ) ) {
			$parts      = explode( '. ', $line );
			$translated = false;
			foreach ( $parts as $i => $part ) {
				$candidate = ( $i < count( $parts ) - 1 ) ? $part . '.' : $part;
				$result    = isset( $dictionary[ $candidate ] ) ? $dictionary[ $candidate ] : null;
				if ( null === $result ) {
					foreach ( self::patterns() as $pattern => $english ) {
						if ( preg_match( $pattern, $candidate, $m ) ) {
							array_shift( $m );
							$result = vsprintf( $english, $m );
							break;
						}
					}
				}
				if ( null !== $result ) {
					$parts[ $i ] = ( $i < count( $parts ) - 1 ) ? rtrim( $result, '.' ) : $result;
					$translated  = true;
				}
			}
			if ( $translated ) {
				return $prefix . implode( '. ', $parts );
			}
		}

		return $prefix . $line;
	}

	/** Regexes built from the dictionary's sprintf formats, longest first. */
	private static function patterns() {
		if ( null !== self::$patterns ) {
			return self::$patterns;
		}
		self::$patterns = array();
		$formats        = array();
		foreach ( self::dictionary() as $spanish => $english ) {
			if ( preg_match( '/%(?:\d+\$)?[sd]/', $spanish ) ) {
				$formats[ $spanish ] = $english;
			}
		}
		uksort(
			$formats,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		foreach ( $formats as $spanish => $english ) {
			$parts = preg_split( '/(%%|%(?:\d+\$)?[sd])/', $spanish, -1, PREG_SPLIT_DELIM_CAPTURE );
			$regex = '';
			foreach ( $parts as $part ) {
				if ( '%%' === $part ) {
					$regex .= '%';
				} elseif ( preg_match( '/^%(?:\d+\$)?d$/', $part ) ) {
					$regex .= '(-?\d+)';
				} elseif ( preg_match( '/^%(?:\d+\$)?s$/', $part ) ) {
					$regex .= '(.+?)';
				} else {
					$regex .= preg_quote( $part, '/' );
				}
			}
			self::$patterns[ '/^' . $regex . '$/su' ] = $english;
		}
		return self::$patterns;
	}

	/** ES | EN switch shown in the plugin header. */
	public static function switcher() {
		$current = self::lang();
		$html    = '<div class="bhna-lang" role="group" aria-label="Language">';
		foreach ( self::LANGUAGES as $code => $label ) {
			$url   = wp_nonce_url(
				add_query_arg(
					array(
						'action'   => 'bhna_set_lang',
						'lang'     => $code,
						'redirect' => rawurlencode( ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ),
					),
					admin_url( 'admin-post.php' )
				),
				'bhna_set_lang'
			);
			$html .= sprintf(
				'<a href="%s" class="%s" title="%s">%s</a>',
				esc_url( $url ),
				$code === $current ? 'is-active' : '',
				esc_attr( 'en' === $code ? 'English' : 'Español' ),
				esc_html( $label )
			);
		}
		return $html . '</div>';
	}

	public static function handle_set_lang() {
		check_admin_referer( 'bhna_set_lang' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Forbidden' );
		}
		$lang = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : 'es';
		if ( array_key_exists( $lang, self::LANGUAGES ) ) {
			update_user_meta( get_current_user_id(), self::META, $lang );
		}
		$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['redirect'] ) ) ) : '';
		// Drop the one-time notice of the previous screen.
		$redirect = remove_query_arg( array( 'bhna_msg', 'bhna_type' ), $redirect );
		wp_safe_redirect( $redirect ? $redirect : admin_url( 'admin.php?page=bhna' ) );
		exit;
	}
}

/** Shorthand used across the admin code. */
function bhna__( $text ) {
	return BHNA_I18N::t( $text );
}

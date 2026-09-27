<?php
/**
 * Small shared helpers.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL of a file inside assets/images.
 */
function lilo_asset( $file ) {
	return LILO_URI . '/assets/images/' . ltrim( $file, '/' );
}

/**
 * Theme option (Customizer) with the theme's default.
 */
function lilo_opt( $key ) {
	$defaults = lilo_option_defaults();
	return get_theme_mod( $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

/**
 * Defaults for every Customizer option.
 */
function lilo_option_defaults() {
	return array(
		'lilo_color_primary'      => '#464D1D',
		'lilo_color_primary_dark' => '#3B4117',
		'lilo_color_accent'       => '#E6535F',
		'lilo_color_bg'           => '#FAFAF8',
		'lilo_color_text'         => '#2F3313',
		'lilo_sticky_header'      => true,
		'lilo_header_cta_text'    => __( 'Order online', 'lilo-cafe' ),
		'lilo_header_cta_url'     => '#',
		'lilo_header_template'    => 0,
		'lilo_footer_template'    => 0,
		'lilo_footer_logo'        => 0,
		'lilo_footer_col1_title'  => __( 'Visit', 'lilo-cafe' ),
		'lilo_footer_col1_text'   => "684 West Church Street<br>Jasper, GA 30143",
		'lilo_footer_col2_title'  => __( 'Hours', 'lilo-cafe' ),
		'lilo_footer_col2_text'   => 'Tue–Sat 8:30am–6:30pm<br>Sun 9am–3pm · Mon closed',
		'lilo_footer_col3_title'  => __( 'Follow', 'lilo-cafe' ),
		'lilo_social_instagram'   => 'https://www.instagram.com/lilocafeco',
		'lilo_social_tiktok'      => 'https://www.tiktok.com/@lilocafe_',
		'lilo_social_facebook'    => 'https://www.facebook.com/Lilocafeco',
		'lilo_copyright'          => '© {year} Lilo Cafe LLC · lilocafé.com',
	);
}

/**
 * Render an <img> from an Elementor media setting ( [ 'id' => , 'url' => ] ) or a plain URL.
 *
 * @param array|string $media Media setting or URL.
 * @param string       $alt   Alt text fallback.
 * @param string       $size  Image size for attachments.
 * @param array        $attr  Extra attributes.
 */
function lilo_img( $media, $alt = '', $size = 'large', $attr = array() ) {
	$id  = is_array( $media ) && ! empty( $media['id'] ) ? (int) $media['id'] : 0;
	$url = is_array( $media ) ? ( isset( $media['url'] ) ? $media['url'] : '' ) : (string) $media;

	if ( ! empty( $media['alt'] ) && '' === $alt ) {
		$alt = $media['alt'];
	}

	if ( $id && wp_attachment_is_image( $id ) ) {
		$attr = array_merge( array( 'alt' => $alt ? $alt : get_post_meta( $id, '_wp_attachment_image_alt', true ) ), $attr );
		return wp_get_attachment_image( $id, $size, false, $attr );
	}

	if ( ! $url ) {
		return '';
	}

	$html = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"';
	foreach ( $attr as $name => $value ) {
		$html .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}
	if ( ! isset( $attr['loading'] ) ) {
		$html .= ' loading="lazy"';
	}
	return $html . '>';
}

/**
 * Attributes for an Elementor URL control value.
 *
 * @param array|string $link URL control value or URL.
 */
function lilo_link_attrs( $link ) {
	if ( ! is_array( $link ) ) {
		$link = array( 'url' => (string) $link );
	}
	$url   = ! empty( $link['url'] ) ? $link['url'] : '#';
	$attrs = ' href="' . esc_url( lilo_expand_url( $url ) ) . '"';
	if ( ! empty( $link['is_external'] ) ) {
		$attrs .= ' target="_blank"';
	}
	$rel = array();
	if ( ! empty( $link['is_external'] ) ) {
		$rel[] = 'noopener';
	}
	if ( ! empty( $link['nofollow'] ) ) {
		$rel[] = 'nofollow';
	}
	if ( $rel ) {
		$attrs .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
	}
	return $attrs;
}

/**
 * Lets links be written relative to the site, e.g. "/menu/#ice" or "{home}/#about".
 */
function lilo_expand_url( $url ) {
	$url = (string) $url;
	if ( false !== strpos( $url, '{menu}' ) ) {
		$menu_page = (int) get_option( 'lilo_menu_page_id' );
		$menu_url  = $menu_page && get_post_status( $menu_page ) ? get_permalink( $menu_page ) : home_url( '/menu/' );
		$url       = str_replace( '{menu}', $menu_url, $url );
	}
	$url = str_replace( '{home}', untrailingslashit( home_url() ), $url );
	if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
		$url = home_url( $url );
	}
	return $url;
}

/**
 * Allowed HTML for small rich-text fields (line breaks, emphasis, links).
 */
function lilo_kses( $text ) {
	return wp_kses(
		$text,
		array(
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'span'   => array( 'class' => array() ),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
				'class'  => array(),
			),
		)
	);
}

/**
 * Converts a "8:30am", "08:30" or "18:30" string to minutes after midnight.
 */
function lilo_time_to_minutes( $time ) {
	$time = strtolower( trim( $time ) );
	if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/', $time, $m ) ) {
		return null;
	}
	$h   = (int) $m[1];
	$min = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
	if ( ! empty( $m[3] ) ) {
		if ( 'pm' === $m[3] && $h < 12 ) {
			$h += 12;
		}
		if ( 'am' === $m[3] && 12 === $h ) {
			$h = 0;
		}
	}
	return $h * 60 + $min;
}

/**
 * Turns "8:30am - 6:30pm" style ranges into [ open, close, label ] for the live status.
 *
 * @param string $range Range text. Empty or "closed" means closed.
 * @return array|null
 */
function lilo_parse_range( $range ) {
	$range = trim( (string) $range );
	if ( '' === $range || 'closed' === strtolower( $range ) ) {
		return null;
	}
	$parts = preg_split( '/\s*[-–—]\s*/u', $range );
	if ( count( $parts ) !== 2 ) {
		return null;
	}
	$open  = lilo_time_to_minutes( $parts[0] );
	$close = lilo_time_to_minutes( $parts[1] );
	if ( null === $open || null === $close ) {
		return null;
	}
	return array( $open, $close, trim( $parts[0] ) . '–' . trim( $parts[1] ) );
}

/**
 * The live "open now" status line; filled in by main.js from the data attributes.
 *
 * @param array $s Widget settings with status_* keys.
 */
function lilo_status_html( $s ) {
	$days     = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
	$schedule = array();
	foreach ( $days as $i => $day ) {
		$schedule[ $i ] = lilo_parse_range( isset( $s[ 'hours_' . $day ] ) ? $s[ 'hours_' . $day ] : '' );
	}
	$config = array(
		'schedule' => $schedule,
		'tz'       => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '',
		'open'     => isset( $s['status_open_text'] ) ? $s['status_open_text'] : 'Open now · Today {hours}',
		'later'    => isset( $s['status_later_text'] ) ? $s['status_later_text'] : 'Today {hours}',
		'closed'   => isset( $s['status_closed_text'] ) ? $s['status_closed_text'] : 'Closed {day}s · Open {next_day} {next_open}',
		'days'     => array(
			__( 'Sunday', 'lilo-cafe' ),
			__( 'Monday', 'lilo-cafe' ),
			__( 'Tuesday', 'lilo-cafe' ),
			__( 'Wednesday', 'lilo-cafe' ),
			__( 'Thursday', 'lilo-cafe' ),
			__( 'Friday', 'lilo-cafe' ),
			__( 'Saturday', 'lilo-cafe' ),
		),
	);
	return '<div class="lilo-status" data-lilo-status="' . esc_attr( wp_json_encode( $config ) ) . '"><span class="lilo-status__dot"></span><span class="lilo-status__text">&nbsp;</span></div>';
}

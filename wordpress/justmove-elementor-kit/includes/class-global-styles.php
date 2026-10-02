<?php
/**
 * Adds the kit's colours and fonts to Elementor's Global styles
 * (Site Settings → Global Colors / Global Fonts).
 *
 * The kit CSS reads these globals (var(--e-global-color-jmk…), with the
 * original values as fallbacks), so editing "JM Yellow" in Site Settings
 * recolours every kit section, and the same colours/fonts can be picked in
 * any other Elementor widget.
 *
 * Same rules as Sync: running it again never duplicates anything, a global
 * the owner already edited is left alone, and a backup is taken first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Global_Styles {

	const OPT_APPLIED = 'jmk_global_styles_applied';

	/**
	 * _id => [title, colour]. The _id becomes --e-global-color-{_id}; never rename.
	 */
	const COLORS = array(
		'jmkyellow'     => array( 'JM Yellow', '#FFD21E' ),
		'jmkyellowdeep' => array( 'JM Yellow Deep', '#F4C100' ),
		'jmkblue'       => array( 'JM Blue', '#36ABE6' ),
		'jmkbluedeep'   => array( 'JM Blue Deep', '#1B8AC9' ),
		'jmkbg'         => array( 'JM Background', '#0B0C0E' ),
		'jmkpanel'      => array( 'JM Panel', '#14161B' ),
		'jmktext'       => array( 'JM Text', '#ECEEF1' ),
		'jmkmuted'      => array( 'JM Muted Text', '#9AA2AD' ),
		'jmkqnavy'      => array( 'JM Quote Navy', '#0C3A52' ),
		'jmkqblue'      => array( 'JM Quote Blue', '#27A7E0' ),
		'jmkqyellow'    => array( 'JM Quote Yellow', '#F6DE2D' ),
		'jmkqink'       => array( 'JM Quote Ink', '#0B1620' ),
	);

	/**
	 * _id => [title, family, weight, extra typography settings].
	 * The _id becomes --e-global-typography-{_id}-…; never rename.
	 */
	const FONTS = array(
		'jmkdisplay' => array( 'JM Display (headings)', 'Anton', '400', array( 'typography_text_transform' => 'uppercase' ) ),
		'jmkbody'    => array( 'JM Body', 'Inter', '400', array() ),
		'jmkqhead'   => array( 'JM Quote Heading', 'Archivo', '800', array() ),
		'jmkqbody'   => array( 'JM Quote Body', 'Hanken Grotesk', '400', array() ),
	);

	/**
	 * Elementor's own defaults, used site-wide by any widget left on "Default".
	 * Only written when the owner explicitly asks for it.
	 */
	const SYSTEM_COLORS = array(
		'primary'   => '#FFD21E',
		'secondary' => '#36ABE6',
		'text'      => '#9AA2AD', // mid grey: readable on both light and dark pages
		'accent'    => '#1B8AC9',
	);

	const SYSTEM_FONTS = array(
		'primary'   => array( 'Anton', '400' ),
		'secondary' => array( 'Inter', '600' ),
		'text'      => array( 'Inter', '400' ),
		'accent'    => array( 'Inter', '700' ),
	);

	public static function kit_id() {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->kits_manager ) ) {
			return 0;
		}
		return (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
	}

	public static function applied() {
		return (int) get_option( self::OPT_APPLIED, 0 );
	}

	/**
	 * @param bool $set_system Also set Elementor's Primary/Secondary/Text/Accent colours and fonts.
	 * @return array|WP_Error { added: int, kept: int, system: bool }
	 */
	public static function apply( $set_system = false ) {
		$kit_id = self::kit_id();
		if ( ! $kit_id || ! get_post( $kit_id ) ) {
			return new WP_Error( 'jmk_no_kit', __( 'Elementor Site Settings (the active Kit) could not be found. Open Elementor → Site Settings once, then try again.', 'jmk' ) );
		}

		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$added = 0;
		$kept  = 0;

		$colors = array();
		foreach ( self::COLORS as $id => $c ) {
			$colors[ $id ] = array(
				'_id'   => $id,
				'title' => $c[0],
				'color' => $c[1],
			);
		}
		$settings['custom_colors'] = self::merge( isset( $settings['custom_colors'] ) ? $settings['custom_colors'] : array(), $colors, $added, $kept );

		$fonts = array();
		foreach ( self::FONTS as $id => $f ) {
			$fonts[ $id ] = array_merge( array( '_id' => $id, 'title' => $f[0] ), self::typography( $f[1], $f[2] ), $f[3] );
		}
		$settings['custom_typography'] = self::merge( isset( $settings['custom_typography'] ) ? $settings['custom_typography'] : array(), $fonts, $added, $kept );

		if ( $set_system ) {
			$settings['system_colors']     = self::set_system( isset( $settings['system_colors'] ) ? $settings['system_colors'] : array(), self::system_colors() );
			$settings['system_typography'] = self::set_system( isset( $settings['system_typography'] ) ? $settings['system_typography'] : array(), self::system_fonts() );
		}

		if ( ! $added && ! $set_system ) {
			// Everything is already there: nothing to write, nothing to back up.
			if ( ! self::applied() ) {
				update_option( self::OPT_APPLIED, time() );
			}
			return array(
				'added'  => 0,
				'kept'   => $kept,
				'system' => false,
			);
		}

		JMK_Sync::backup( $kit_id, 'global-styles' );
		update_post_meta( $kit_id, '_elementor_page_settings', wp_slash( $settings ) );
		delete_post_meta( $kit_id, '_elementor_css' );
		\Elementor\Plugin::$instance->files_manager->clear_cache();
		update_option( self::OPT_APPLIED, time() );

		return array(
			'added'  => $added,
			'kept'   => $kept,
			'system' => (bool) $set_system,
		);
	}

	private static function typography( $family, $weight ) {
		return array(
			'typography_typography'  => 'custom',
			'typography_font_family' => $family,
			'typography_font_weight' => $weight,
		);
	}

	/**
	 * Append ours by _id; an item that already exists (maybe edited by the owner) is kept.
	 */
	private static function merge( $existing, array $ours, &$added, &$kept ) {
		$existing = is_array( $existing ) ? array_values( $existing ) : array();
		$have     = array();
		foreach ( $existing as $item ) {
			if ( isset( $item['_id'] ) ) {
				$have[ $item['_id'] ] = true;
			}
		}
		foreach ( $ours as $id => $item ) {
			if ( isset( $have[ $id ] ) ) {
				$kept++;
				continue;
			}
			$existing[] = $item;
			$added++;
		}
		return $existing;
	}

	/**
	 * Overwrite the values of Elementor's four system items, keeping their titles.
	 */
	private static function set_system( $existing, array $values ) {
		$existing = is_array( $existing ) ? array_values( $existing ) : array();
		$titles   = array(
			'primary'   => 'Primary',
			'secondary' => 'Secondary',
			'text'      => 'Text',
			'accent'    => 'Accent',
		);
		foreach ( $values as $id => $value ) {
			$found = false;
			foreach ( $existing as $i => $item ) {
				if ( isset( $item['_id'] ) && $item['_id'] === $id ) {
					$existing[ $i ] = array_merge( $item, $value );
					$found          = true;
				}
			}
			if ( ! $found ) {
				$existing[] = array_merge( array( '_id' => $id, 'title' => $titles[ $id ] ), $value );
			}
		}
		return $existing;
	}

	private static function system_colors() {
		$out = array();
		foreach ( self::SYSTEM_COLORS as $id => $hex ) {
			$out[ $id ] = array( 'color' => $hex );
		}
		return $out;
	}

	private static function system_fonts() {
		$out = array();
		foreach ( self::SYSTEM_FONTS as $id => $f ) {
			$out[ $id ] = self::typography( $f[0], $f[1] );
		}
		return $out;
	}
}

<?php
/**
 * Safe sync for kit pages and templates after the first import.
 *
 * How the kit keeps your edits:
 * - Widgets render from plugin code, so design/behaviour updates arrive with a
 *   plugin update — no re-import needed.
 * - Elementor saves only the settings you changed; everything else follows the
 *   widget defaults. Sync never rewrites existing elements, so your content,
 *   style settings, order and your own extra sections stay exactly as they are.
 * - Sync only inserts a kit section that the page has never had (e.g. one added
 *   in a later plugin version). Sections you deleted are remembered and never
 *   come back.
 * - Every write is preceded by a backup that can be restored from the admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Sync {

	const META_ROLE    = '_jmk_kit_role';
	const META_TYPES   = '_jmk_kit_types';
	const META_BACKUPS = '_jmk_kit_backups';
	const MAX_BACKUPS  = 5;

	/**
	 * Kit widget types a document of each role is built from, in layout order.
	 */
	public static function role_types( $role ) {
		switch ( $role ) {
			case 'home-full':
				return JMK_Plugin::demo_widget_types();
			case 'home-body':
				return JMK_Demo_Importer::body_widget_types();
			case 'quote-full':
				return array( 'jmk-header', 'jmk-quote-builder', 'jmk-footer', 'jmk-mobile-bar' );
			case 'quote-body':
				return array( 'jmk-quote-builder' );
			case 'header':
				return JMK_Demo_Importer::HEADER_WIDGETS;
			case 'footer':
				return JMK_Demo_Importer::FOOTER_WIDGETS;
		}
		return array();
	}

	/**
	 * Record what the kit built into a document.
	 */
	public static function mark( $post_id, $role, array $types ) {
		update_post_meta( $post_id, self::META_ROLE, $role );
		update_post_meta( $post_id, self::META_TYPES, array_values( array_unique( $types ) ) );
	}

	/**
	 * Kit documents that exist right now: key => [label, post id].
	 */
	public static function targets() {
		$out = array();
		$map = array(
			'home'   => array( __( 'Home page', 'jmk' ), JMK_Demo_Importer::imported_page_id() ),
			'quote'  => array( __( 'Quote page', 'jmk' ), JMK_Demo_Importer::quote_page_id() ),
			'header' => array( __( 'Header template', 'jmk' ), JMK_Demo_Importer::template_id( JMK_Demo_Importer::OPT_HEADER_ID ) ),
			'footer' => array( __( 'Footer template', 'jmk' ), JMK_Demo_Importer::template_id( JMK_Demo_Importer::OPT_FOOTER_ID ) ),
		);
		foreach ( $map as $key => $t ) {
			if ( $t[1] ) {
				$out[ $key ] = $t;
			}
		}
		return $out;
	}

	/**
	 * Everything that can have backups: kit documents plus Elementor's Site Settings
	 * once the kit's global styles were added.
	 */
	public static function backup_targets() {
		$out = self::targets();
		$kit = JMK_Global_Styles::kit_id();
		if ( $kit && self::backups( $kit ) ) {
			$out['kit'] = array( __( 'Elementor Site Settings (global styles)', 'jmk' ), $kit );
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Element data (read/write verbatim)
	 * ------------------------------------------------------------------ */

	public static function read_elements( $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true );
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Store element data exactly as given. Deliberately not Document::save():
	 * that re-parses every element and would drop widgets from a plugin that is
	 * inactive right now.
	 */
	private static function write_elements( $post_id, array $elements ) {
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		self::flush( $post_id );
	}

	private static function flush( $post_id ) {
		delete_post_meta( $post_id, '_elementor_css' );
		delete_post_meta( $post_id, '_elementor_element_cache' );
		delete_post_meta( $post_id, '_elementor_page_assets' );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/**
	 * Every kit widget type anywhere in the tree.
	 */
	public static function collect_types( array $elements ) {
		$types = array();
		foreach ( $elements as $el ) {
			if ( isset( $el['widgetType'] ) && 0 === strpos( $el['widgetType'], 'jmk-' ) ) {
				$types[] = $el['widgetType'];
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$types = array_merge( $types, self::collect_types( $el['elements'] ) );
			}
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * Index of the top-level element whose subtree holds $type, or -1.
	 */
	private static function top_index( array $elements, $type ) {
		foreach ( array_values( $elements ) as $i => $el ) {
			if ( in_array( $type, self::collect_types( array( $el ) ), true ) ) {
				return $i;
			}
		}
		return -1;
	}

	/**
	 * Role of a document imported before roles were recorded (plugin 1.1).
	 */
	private static function role_for( $key, $post_id, array $present ) {
		$role = get_post_meta( $post_id, self::META_ROLE, true );
		if ( $role ) {
			return $role;
		}
		switch ( $key ) {
			case 'home':
				return in_array( 'jmk-header', $present, true ) ? 'home-full' : 'home-body';
			case 'quote':
				return in_array( 'jmk-header', $present, true ) ? 'quote-full' : 'quote-body';
		}
		return $key;
	}

	/* ------------------------------------------------------------------
	 * Sync
	 * ------------------------------------------------------------------ */

	/**
	 * Add kit sections this document has never had; touch nothing else.
	 *
	 * @return string[] Widget types that were added.
	 */
	public static function sync_document( $key, $post_id ) {
		$elements = self::read_elements( $post_id );
		$present  = self::collect_types( $elements );
		$role     = self::role_for( $key, $post_id, $present );
		$desired  = self::role_types( $role );

		$placed = get_post_meta( $post_id, self::META_TYPES, true );
		if ( ! is_array( $placed ) ) {
			// Imported by 1.1 (no record): everything the layout had then counts as placed,
			// so sections the owner deleted since are not brought back.
			$placed = $desired;
		}

		$missing = array_values( array_diff( $desired, $present, $placed ) );
		if ( $missing ) {
			self::backup( $post_id, 'sync' );
			foreach ( $missing as $type ) {
				$elements = self::insert_in_order( $elements, $type, $desired );
			}
			self::write_elements( $post_id, $elements );
		}

		self::mark( $post_id, $role, array_merge( $placed, $present, $missing ) );
		return $missing;
	}

	/**
	 * Insert a new section for $type right after the closest earlier section of
	 * the layout that is on the page (or before the closest later one).
	 */
	private static function insert_in_order( array $elements, $type, array $desired ) {
		$elements = array_values( $elements );
		$new      = JMK_Demo_Importer::build_elements( array( $type ) );
		$pos      = array_search( $type, $desired, true );

		for ( $i = $pos - 1; $i >= 0; $i-- ) {
			$at = self::top_index( $elements, $desired[ $i ] );
			if ( $at >= 0 ) {
				array_splice( $elements, $at + 1, 0, $new );
				return $elements;
			}
		}
		for ( $i = $pos + 1, $n = count( $desired ); $i < $n; $i++ ) {
			$at = self::top_index( $elements, $desired[ $i ] );
			if ( $at >= 0 ) {
				array_splice( $elements, $at, 0, $new );
				return $elements;
			}
		}
		return array_merge( $elements, $new );
	}

	/**
	 * Sync every kit document.
	 *
	 * @return array key => added widget types.
	 */
	public static function sync_all() {
		$report = array();
		foreach ( self::targets() as $key => $t ) {
			$report[ $key ] = self::sync_document( $key, $t[1] );
		}
		self::flush_all();
		return $report;
	}

	/* ------------------------------------------------------------------
	 * Reset (explicit, destructive — always backed up first)
	 * ------------------------------------------------------------------ */

	public static function reset_document( $key, $post_id ) {
		$present = self::collect_types( self::read_elements( $post_id ) );
		$role    = self::role_for( $key, $post_id, $present );
		$types   = self::role_types( $role );
		if ( ! $types ) {
			return false;
		}
		self::backup( $post_id, 'reset' );
		self::write_elements( $post_id, JMK_Demo_Importer::build_elements( $types ) );
		self::mark( $post_id, $role, $types );
		return true;
	}

	/* ------------------------------------------------------------------
	 * Backups
	 * ------------------------------------------------------------------ */

	public static function backup( $post_id, $reason ) {
		$list = self::backups( $post_id );
		array_unshift(
			$list,
			array(
				'time'     => time(),
				'reason'   => $reason,
				'data'     => wp_json_encode( self::read_elements( $post_id ) ),
				'settings' => get_post_meta( $post_id, '_elementor_page_settings', true ),
			)
		);
		update_post_meta( $post_id, self::META_BACKUPS, wp_slash( array_slice( $list, 0, self::MAX_BACKUPS ) ) );
	}

	public static function backups( $post_id ) {
		$list = get_post_meta( $post_id, self::META_BACKUPS, true );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Put a backup back. The current state is backed up first, so a restore can be undone.
	 */
	public static function restore( $post_id, $index ) {
		$list = self::backups( $post_id );
		if ( ! isset( $list[ $index ] ) ) {
			return false;
		}
		$snap     = $list[ $index ];
		$elements = json_decode( (string) $snap['data'], true );
		if ( ! is_array( $elements ) ) {
			return false;
		}
		self::backup( $post_id, 'before-restore' );
		self::write_elements( $post_id, $elements );
		if ( is_array( $snap['settings'] ) ) {
			update_post_meta( $post_id, '_elementor_page_settings', wp_slash( $snap['settings'] ) );
		}
		// Sections present in the restored version count as placed again.
		$placed = get_post_meta( $post_id, self::META_TYPES, true );
		update_post_meta(
			$post_id,
			self::META_TYPES,
			array_values( array_unique( array_merge( is_array( $placed ) ? $placed : array(), self::collect_types( $elements ) ) ) )
		);
		self::flush_all();
		return true;
	}

	private static function flush_all() {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}
}

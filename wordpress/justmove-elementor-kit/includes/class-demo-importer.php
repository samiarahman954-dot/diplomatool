<?php
/**
 * One-click demo import: builds the home page from the kit widgets, saves it
 * through Elementor's document API, and optionally sets it as the front page.
 *
 * With Elementor Pro's Theme Builder available, the header widget is imported
 * as a site-wide Theme Builder "Header" template and the footer (+ mobile call
 * bar) as a site-wide "Footer" template; the page then holds only the body.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Demo_Importer {

	const OPT_PAGE_ID   = 'jmk_demo_page_id';
	const OPT_QUOTE_ID  = 'jmk_quote_page_id';
	const QUOTE_SLUG    = 'quote';
	const OPT_HEADER_ID = 'jmk_theme_header_id';
	const OPT_FOOTER_ID = 'jmk_theme_footer_id';
	const PAGE_TITLE    = 'Home – Just Move DFW';

	/** Widgets that move into Theme Builder templates when Pro is active. */
	const HEADER_WIDGETS = array( 'jmk-header' );
	const FOOTER_WIDGETS = array( 'jmk-footer', 'jmk-mobile-bar' );

	/**
	 * Elementor element tree: one full-width container (or section/column on
	 * sites without the Flexbox Container feature) per kit widget. Widget
	 * settings are left empty so every widget renders its built-in defaults.
	 *
	 * @param string[]|null $types Widget types, in order. Defaults to the full page.
	 * @return array
	 */
	public static function build_elements( $types = null ) {
		$types          = null === $types ? JMK_Plugin::demo_widget_types() : $types;
		$use_containers = self::containers_active();
		$zero           = array(
			'unit'     => 'px',
			'top'      => '0',
			'right'    => '0',
			'bottom'   => '0',
			'left'     => '0',
			'isLinked' => true,
		);
		$elements = array();

		foreach ( $types as $type ) {
			$widget = array(
				'id'         => self::element_id(),
				'elType'     => 'widget',
				'widgetType' => $type,
				'settings'   => array(),
				'elements'   => array(),
			);

			if ( $use_containers ) {
				$elements[] = array(
					'id'       => self::element_id(),
					'elType'   => 'container',
					'isInner'  => false,
					'settings' => array(
						'content_width'  => 'full',
						'flex_direction' => 'column',
						'padding'        => $zero,
						'margin'         => $zero,
						'flex_gap'       => array(
							'unit'   => 'px',
							'size'   => 0,
							'column' => '0',
							'row'    => '0',
						),
					),
					'elements' => array( $widget ),
				);
			} else {
				$elements[] = array(
					'id'       => self::element_id(),
					'elType'   => 'section',
					'isInner'  => false,
					'settings' => array(
						'layout'  => 'full_width',
						'gap'     => 'no',
						'padding' => $zero,
					),
					'elements' => array(
						array(
							'id'       => self::element_id(),
							'elType'   => 'column',
							'isInner'  => false,
							'settings' => array( '_column_size' => 100 ),
							'elements' => array( $widget ),
						),
					),
				);
			}
		}

		return $elements;
	}

	/**
	 * Body widgets only (everything except header / footer / mobile bar).
	 */
	public static function body_widget_types() {
		return array_values( array_diff( JMK_Plugin::demo_widget_types(), self::HEADER_WIDGETS, self::FOOTER_WIDGETS ) );
	}

	/**
	 * Page settings on the kit's dark background.
	 *
	 * Canvas when the page carries its own header/footer; "Elementor Full Width"
	 * when Theme Builder header/footer should wrap it (Canvas would hide them).
	 */
	public static function page_settings( $with_theme_parts = false ) {
		return array(
			'template'              => $with_theme_parts ? 'elementor_header_footer' : 'elementor_canvas',
			'background_background' => 'classic',
			'background_color'      => '#0B0C0E',
		);
	}

	/**
	 * Elementor Pro's Theme Builder is active and can hold header/footer templates.
	 */
	public static function theme_builder_available() {
		if ( ! JMK_Plugin::elementor_ready() || ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return false;
		}
		$documents = \Elementor\Plugin::$instance->documents;
		return (bool) $documents->get_document_type( 'header', false ) && (bool) $documents->get_document_type( 'footer', false );
	}

	/**
	 * Run the import.
	 *
	 * @param array $args { set_front: bool, library: bool, theme_parts: bool, quote_page: bool }
	 * @return int|WP_Error Page ID.
	 */
	public static function import( array $args ) {
		if ( ! JMK_Plugin::elementor_ready() ) {
			return new WP_Error( 'jmk_no_elementor', __( 'Elementor must be active to import the demo.', 'jmk' ) );
		}

		// Kit widgets must be known to Elementor before any document is saved.
		\Elementor\Plugin::$instance->widgets_manager->get_widget_types();

		$theme_parts = ! empty( $args['theme_parts'] ) && self::theme_builder_available();

		if ( $theme_parts ) {
			$parts = array(
				'header' => array( self::OPT_HEADER_ID, __( 'Just Move DFW – Header', 'jmk' ), self::HEADER_WIDGETS ),
				'footer' => array( self::OPT_FOOTER_ID, __( 'Just Move DFW – Footer', 'jmk' ), self::FOOTER_WIDGETS ),
			);
			foreach ( $parts as $location => $part ) {
				$result = self::import_theme_part( $location, $part[0], $part[1], $part[2] );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		$page_types    = $theme_parts ? self::body_widget_types() : JMK_Plugin::demo_widget_types();
		$page_settings = self::page_settings( $theme_parts );

		$page_id = self::create_page( self::PAGE_TITLE, '', $page_types, $page_settings );
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		if ( ! empty( $args['quote_page'] ) && ! self::quote_page_id() && ! get_page_by_path( self::QUOTE_SLUG ) ) {
			$quote_types = $theme_parts
				? array( 'jmk-quote-builder' )
				: array( 'jmk-header', 'jmk-quote-builder', 'jmk-footer', 'jmk-mobile-bar' );
			$quote_id    = self::create_page( __( 'Get a Quote', 'jmk' ), self::QUOTE_SLUG, $quote_types, $page_settings );
			if ( ! is_wp_error( $quote_id ) ) {
				update_option( self::OPT_QUOTE_ID, $quote_id );
			}
		}

		if ( ! empty( $args['set_front'] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
		}

		if ( ! empty( $args['library'] ) ) {
			self::save_library_template( $page_types, $page_settings );
		}

		update_option( self::OPT_PAGE_ID, $page_id );
		\Elementor\Plugin::$instance->files_manager->clear_cache();

		return $page_id;
	}

	/**
	 * Publish a page and fill it with kit widgets through Elementor's document API.
	 *
	 * @return int|WP_Error
	 */
	private static function create_page( $title, $slug, array $types, array $settings ) {
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $page_id, false );
		if ( ! $document ) {
			wp_delete_post( $page_id, true );
			return new WP_Error( 'jmk_doc', __( 'Elementor could not open the new page.', 'jmk' ) );
		}
		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $page_id, '_wp_page_template', $settings['template'] );
		$document->save(
			array(
				'elements' => self::build_elements( $types ),
				'settings' => $settings,
			)
		);
		return $page_id;
	}

	public static function quote_page_id() {
		$id = (int) get_option( self::OPT_QUOTE_ID );
		return ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) ? $id : 0;
	}

	/**
	 * Create a Theme Builder header/footer template shown on the entire site.
	 *
	 * A template from an earlier import is kept as-is (so edits survive a
	 * re-import); only its "Entire Site" condition is re-applied.
	 *
	 * @return int|WP_Error Template post ID.
	 */
	private static function import_theme_part( $location, $option, $title, array $widget_types ) {
		$existing = self::template_id( $option );
		if ( $existing ) {
			self::assign_entire_site( $existing );
			return $existing;
		}

		$document = \Elementor\Plugin::$instance->documents->create(
			$location,
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		if ( is_wp_error( $document ) || ! $document ) {
			return new WP_Error(
				'jmk_theme_part',
				/* translators: %s: header or footer */
				sprintf( __( 'Could not create the Theme Builder %s template.', 'jmk' ), $location )
			);
		}

		$document->save(
			array(
				'elements' => self::build_elements( $widget_types ),
				'settings' => array(),
			)
		);

		$post_id = $document->get_main_id();
		self::assign_entire_site( $post_id );
		update_option( $option, $post_id );

		return $post_id;
	}

	/**
	 * Display condition "Include: Entire Site", through Pro's conditions manager
	 * so its conditions cache is regenerated.
	 */
	private static function assign_entire_site( $post_id ) {
		$conditions = array(
			array(
				'type' => 'include',
				'name' => 'general',
			),
		);

		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( method_exists( $module, 'get_conditions_manager' ) ) {
			$module->get_conditions_manager()->save_conditions( $post_id, $conditions );
			return;
		}

		update_post_meta( $post_id, '_elementor_conditions', array( 'include/general' ) );
		if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Cache' ) ) {
			( new \ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Cache() )->regenerate();
		}
	}

	/**
	 * Also store the layout in Templates → Saved Templates for reuse on other pages.
	 */
	private static function save_library_template( array $types, array $page_settings ) {
		$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );
		if ( ! $source ) {
			return;
		}
		$source->save_item(
			array(
				'title'         => __( 'Just Move DFW – Home', 'jmk' ),
				'type'          => 'page',
				'content'       => self::build_elements( $types ),
				'page_settings' => $page_settings,
			)
		);
	}

	/**
	 * Elementor template export format (Templates → Import Templates).
	 * Full page including header and footer, so it works without Pro.
	 */
	public static function export_json() {
		return wp_json_encode(
			array(
				'version'       => '0.4',
				'title'         => 'Just Move DFW – Home',
				'type'          => 'page',
				'content'       => self::build_elements(),
				'page_settings' => self::page_settings(),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}

	public static function imported_page_id() {
		$id = (int) get_option( self::OPT_PAGE_ID );
		return ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) ? $id : 0;
	}

	/**
	 * Theme Builder template from an earlier import, if it still exists.
	 */
	public static function template_id( $option ) {
		$id = (int) get_option( $option );
		return ( $id && 'elementor_library' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) ? $id : 0;
	}

	private static function containers_active() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$experiments = \Elementor\Plugin::$instance->experiments;
		return $experiments && $experiments->is_feature_active( 'container' );
	}

	/**
	 * 7-char hex ID, same shape Elementor uses for element IDs.
	 */
	private static function element_id() {
		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}
}

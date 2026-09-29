<?php
/**
 * One-click demo import: builds the home page from the kit widgets, saves it
 * through Elementor's document API, and optionally sets it as the front page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Demo_Importer {

	const OPT_PAGE_ID = 'jmk_demo_page_id';
	const PAGE_TITLE  = 'Home – Just Move DFW';

	/**
	 * Elementor element tree: one full-width container (or section/column on
	 * sites without the Flexbox Container feature) per kit widget. Widget
	 * settings are left empty so every widget renders its built-in defaults.
	 *
	 * @return array
	 */
	public static function build_elements() {
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

		foreach ( JMK_Plugin::demo_widget_types() as $type ) {
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
	 * Page settings: blank Elementor Canvas template on the kit's dark background.
	 */
	public static function page_settings() {
		return array(
			'template'              => 'elementor_canvas',
			'background_background' => 'classic',
			'background_color'      => '#0B0C0E',
		);
	}

	/**
	 * Run the import.
	 *
	 * @param array $args { set_front: bool, library: bool }
	 * @return int|WP_Error Page ID.
	 */
	public static function import( array $args ) {
		if ( ! JMK_Plugin::elementor_ready() ) {
			return new WP_Error( 'jmk_no_elementor', __( 'Elementor must be active to import the demo.', 'jmk' ) );
		}

		// Kit widgets must be known to Elementor before the document is saved.
		\Elementor\Plugin::$instance->widgets_manager->get_widget_types();

		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => self::PAGE_TITLE,
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		$saved = self::save_document( $page_id, self::build_elements(), self::page_settings() );
		if ( is_wp_error( $saved ) ) {
			wp_delete_post( $page_id, true );
			return $saved;
		}

		if ( ! empty( $args['set_front'] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
		}

		if ( ! empty( $args['library'] ) ) {
			self::save_library_template();
		}

		update_option( self::OPT_PAGE_ID, $page_id );
		\Elementor\Plugin::$instance->files_manager->clear_cache();

		return $page_id;
	}

	/**
	 * Save element data through Elementor so meta, version and CSS are handled natively.
	 */
	private static function save_document( $post_id, array $elements, array $settings ) {
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		if ( ! $document ) {
			return new WP_Error( 'jmk_doc', __( 'Elementor could not open the new page.', 'jmk' ) );
		}
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_wp_page_template', $settings['template'] );

		$document->save(
			array(
				'elements' => $elements,
				'settings' => $settings,
			)
		);
		return true;
	}

	/**
	 * Also store the layout in Templates → Saved Templates for reuse on other pages.
	 */
	private static function save_library_template() {
		$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );
		if ( ! $source ) {
			return;
		}
		$source->save_item(
			array(
				'title'         => __( 'Just Move DFW – Home', 'jmk' ),
				'type'          => 'page',
				'content'       => self::build_elements(),
				'page_settings' => self::page_settings(),
			)
		);
	}

	/**
	 * Elementor template export format (Templates → Import Templates).
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

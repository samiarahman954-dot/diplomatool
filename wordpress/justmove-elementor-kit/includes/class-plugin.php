<?php
/**
 * Bootstraps the kit: Elementor checks, assets, widget category and widgets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Plugin {

	/** @var JMK_Plugin|null */
	private static $instance = null;

	/**
	 * Widget slug => class name. Order matches the home page layout.
	 */
	const WIDGETS = array(
		'header'         => 'JMK_Widget_Header',
		'hero'           => 'JMK_Widget_Hero',
		'trust-strip'    => 'JMK_Widget_Trust_Strip',
		'intro'          => 'JMK_Widget_Intro',
		'route-divider'  => 'JMK_Widget_Route_Divider',
		'services'       => 'JMK_Widget_Services',
		'urgency'        => 'JMK_Widget_Urgency',
		'why-us'         => 'JMK_Widget_Why_Us',
		'steps'          => 'JMK_Widget_Steps',
		'specialty'      => 'JMK_Widget_Specialty',
		'reviews'        => 'JMK_Widget_Reviews',
		'estimate-band'  => 'JMK_Widget_Estimate_Band',
		'areas'          => 'JMK_Widget_Areas',
		'guide'          => 'JMK_Widget_Guide',
		'faq'            => 'JMK_Widget_Faq',
		'cta-band'       => 'JMK_Widget_Cta_Band',
		'footer'         => 'JMK_Widget_Footer',
		'mobile-bar'     => 'JMK_Widget_Mobile_Bar',
	);

	/**
	 * Widgets that are not part of the home page layout.
	 */
	const EXTRA_WIDGETS = array(
		'quote-builder' => 'JMK_Widget_Quote_Builder',
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		JMK_Leads::init();
		JMK_Quote::init();
		JMK_Admin::init();

		if ( ! self::elementor_ready() ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_elementor' ) );
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		// Registered early; widgets pull them in through get_style_depends()/get_script_depends().
		add_action( 'init', array( $this, 'register_assets' ) );
	}

	/**
	 * Elementor is loaded and new enough for the widgets API we use.
	 */
	public static function elementor_ready() {
		return did_action( 'elementor/loaded' )
			&& defined( 'ELEMENTOR_VERSION' )
			&& version_compare( ELEMENTOR_VERSION, JMK_MIN_ELEMENTOR, '>=' );
	}

	public function notice_missing_elementor() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: minimum Elementor version */
					__( 'Just Move DFW Template Kit needs Elementor %s or newer. Please install and activate Elementor.', 'jmk' ),
					JMK_MIN_ELEMENTOR
				)
			)
		);
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'jmk',
			array(
				'title' => __( 'Just Move DFW', 'jmk' ),
				'icon'  => 'eicon-truck',
			)
		);
	}

	public function register_widgets( $widgets_manager ) {
		require_once JMK_PATH . 'includes/class-widget-base.php';

		foreach ( self::WIDGETS + self::EXTRA_WIDGETS as $slug => $class ) {
			require_once JMK_PATH . 'widgets/' . $slug . '.php';
			$widgets_manager->register( new $class() );
		}
	}

	public function register_assets() {
		wp_register_style(
			'jmk-fonts',
			'https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);
		wp_register_style( 'jmk-kit', JMK_URL . 'assets/css/jmk-kit.css', array( 'jmk-fonts' ), JMK_VERSION );
		wp_register_script( 'jmk-kit', JMK_URL . 'assets/js/jmk-kit.js', array(), JMK_VERSION, true );
		wp_localize_script(
			'jmk-kit',
			'jmkKit',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => JMK_Leads::ACTION,
				'i18n'    => array(
					'required' => __( 'Please add your name and phone so we can send your quote.', 'jmk' ),
					'error'    => __( 'Something went wrong. Please call us instead.', 'jmk' ),
					'sending'  => __( 'Sending…', 'jmk' ),
				),
			)
		);
	}

	/**
	 * Element tree for the demo home page, in layout order.
	 *
	 * @return string[] widget types (e.g. "jmk-hero").
	 */
	public static function demo_widget_types() {
		return array_map(
			static function ( $slug ) {
				return 'jmk-' . $slug;
			},
			array_keys( self::WIDGETS )
		);
	}
}

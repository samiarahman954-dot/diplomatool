<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap: assets, widget registration, admin screens, form handling.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/**
	 * Widget slug => class name (files live in widgets/class-{slug}.php).
	 * Order matters: parents before children.
	 */
	const WIDGETS = array(
		'header'          => 'Header',
		'footer'          => 'Footer',
		'home-hero'       => 'Home_Hero',
		'chapter'         => 'Chapter',
		'editorial-split' => 'Editorial_Split',
		'room-showcase'   => 'Room_Showcase',
		'page-intro'      => 'Page_Intro',
		'hospitality'     => 'Hospitality',
		'statement'       => 'Statement',
		'split-hero'      => 'Split_Hero',
		'signature'       => 'Signature',
		'reasons'         => 'Reasons',
		'services'        => 'Services',
		'pro-form'        => 'Pro_Form',
		'contact'         => 'Contact',
		'legal'           => 'Legal',
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		require_once DPK_PATH . 'includes/class-sync.php';
		require_once DPK_PATH . 'includes/class-importer.php';
		require_once DPK_PATH . 'includes/class-spam.php';
		require_once DPK_PATH . 'includes/class-forms.php';
		require_once DPK_PATH . 'includes/class-admin.php';

		Spam::instance();
		Forms::instance();
		Admin::instance();

		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_elementor' ) );
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_global' ), 20 );
		add_action( 'wp_head', array( $this, 'preload_fonts' ), 2 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	public function notice_missing_elementor() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'Douce Paresse Kit needs the Elementor plugin. Elementor Pro is also required to place the header and footer through the Theme Builder.', 'douce-paresse-kit' );
		echo '</p></div>';
	}

	public function register_assets() {
		wp_register_style( 'dpk-fonts', DPK_URL . 'assets/css/fonts.css', array(), DPK_VERSION );
		wp_register_style( 'dpk-frontend', DPK_URL . 'assets/css/frontend.css', array( 'dpk-fonts' ), DPK_VERSION );
		wp_register_script( 'dpk-frontend', DPK_URL . 'assets/js/frontend.js', array(), DPK_VERSION, true );
		$spam = Spam::get();
		wp_localize_script(
			'dpk-frontend',
			'DPK',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'turnstile' => Spam::turnstile_enabled() ? array(
					'sitekey'    => $spam['ts_site'],
					'theme'      => $spam['ts_theme'],
					'appearance' => $spam['ts_appearance'],
				) : null,
				'i18n'      => array(
					'required'  => __( 'Ce champ est requis.', 'douce-paresse-kit' ),
					'email'     => __( 'Adresse e-mail invalide.', 'douce-paresse-kit' ),
					'select'    => __( 'Veuillez choisir une option.', 'douce-paresse-kit' ),
					'error'     => __( 'Une erreur est survenue. Merci de réessayer.', 'douce-paresse-kit' ),
					'challenge' => __( 'Merci de valider la vérification anti-spam.', 'douce-paresse-kit' ),
				),
			)
		);
	}

	/**
	 * The kit styles the whole site (header/footer come from the Theme Builder), so its CSS is loaded
	 * in <head> on every page. Relying only on widget dependencies printed the CSS late in the footer
	 * (unstyled header flash) and, with Elementor "Element Caching", cached widgets could skip it entirely
	 * (header not fixed, links underlined).
	 */
	public function enqueue_global() {
		if ( ! apply_filters( 'dpk_enqueue_globally', true ) ) {
			return;
		}
		wp_enqueue_style( 'dpk-frontend' );
		wp_enqueue_script( 'dpk-frontend' );
	}

	public function preload_fonts() {
		if ( ! apply_filters( 'dpk_enqueue_globally', true ) ) {
			return;
		}
		foreach ( array( 'cormorant-latin', 'cormorant-italic-latin', 'jost-latin' ) as $font ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( DPK_URL . 'assets/fonts/' . $font . '.woff2' ) );
		}
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'douce-paresse',
			array(
				'title' => __( 'Douce Paresse', 'douce-paresse-kit' ),
				'icon'  => 'eicon-site-identity',
			)
		);
	}

	public function register_widgets( $widgets_manager ) {
		require_once DPK_PATH . 'includes/class-widget-base.php';
		require_once DPK_PATH . 'includes/trait-form.php';

		foreach ( self::WIDGETS as $slug => $class ) {
			require_once DPK_PATH . 'widgets/class-' . $slug . '.php';
			$fqcn = '\\DPK\\Widgets\\' . $class;
			$widgets_manager->register( new $fqcn() );
		}
	}
}

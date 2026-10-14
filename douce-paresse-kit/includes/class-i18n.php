<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * Website translation (GTranslate / Google Translate).
 *
 * - Brand protection: every occurrence of the protected terms ("Douce Paresse" by default) in the page
 *   body is wrapped in <span class="notranslate" translate="no">, which Google Translate and GTranslate
 *   (all plans) leave untouched, so the registered brand name stays in French in every language.
 * - Settings screen: Douce Paresse → Languages.
 * The FR | EN switcher itself is part of the DP Header widget (assets/js/frontend.js drives GTranslate).
 */
final class I18n {

	const OPTION = 'dpk_i18n';
	const PAGE   = 'dpk-languages';

	/** @var I18n|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 15 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
		add_action( 'update_option_' . self::OPTION, array( $this, 'flush_elementor' ) );
		add_action( 'activated_plugin', array( $this, 'plugin_toggled' ) );
		add_action( 'deactivated_plugin', array( $this, 'plugin_toggled' ) );
	}

	public static function defaults() {
		return array(
			'protect' => 'yes',
			'terms'   => 'Douce Paresse',
		);
	}

	public static function get() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/** Protected terms, one per line. */
	public static function terms() {
		$o = self::get();
		if ( 'yes' !== $o['protect'] ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $o['terms'] ) ) ) );
	}

	/** GTranslate plugin active (any plan). */
	public static function gtranslate_active() {
		if ( class_exists( 'GTranslate' ) ) {
			return true;
		}
		foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
			if ( 0 === strpos( (string) $file, 'gtranslate/' ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * Brand protection
	 * ------------------------------------------------------------------ */

	public function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || is_robots() || ( function_exists( 'is_favicon' ) && is_favicon() ) || get_query_var( 'sitemap' ) || get_query_var( 'sitemap-stylesheet' )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['preview_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return; // Elementor editor preview: keep the markup the editor expects.
		}
		$terms = self::terms();
		if ( ! $terms || ! apply_filters( 'dpk_protect_terms', true ) ) {
			return;
		}
		ob_start(
			function ( $html ) use ( $terms ) {
				return self::is_html_response() ? self::protect( $html, $terms ) : $html;
			}
		);
	}

	/**
	 * Only HTML pages are touched: sitemaps (Yoast, Rank Math…), JSON, XML, plain text or files sent by
	 * other plugins from template_redirect keep their exact output.
	 */
	private static function is_html_response() {
		foreach ( headers_list() as $h ) {
			if ( 0 === stripos( $h, 'content-type:' ) ) {
				return false !== stripos( $h, 'text/html' );
			}
		}
		return true; // PHP's default content type is text/html.
	}

	/** Regex matching any protected term (spaces may be any whitespace or a non-breaking space). */
	private static function pattern( $terms ) {
		$alts = array();
		foreach ( $terms as $t ) {
			$words = preg_split( '/\s+/u', trim( $t ) );
			$alts[] = implode( '(?:\s|&nbsp;|&#160;|\x{00A0})+', array_map( 'preg_quote', $words, array_fill( 0, count( $words ), '/' ) ) );
		}
		usort(
			$alts,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		return '/(?<![\p{L}\p{N}])(?:' . implode( '|', $alts ) . ')(?![\p{L}\p{N}])/iu';
	}

	/**
	 * Wraps the protected terms found in the text of the page body. Tags, attributes, scripts, styles,
	 * <title>, <textarea> and <option> are left as they are; images and inputs whose alt / title /
	 * placeholder / value contain a term get translate="no". Running it twice changes nothing.
	 */
	public static function protect( $html, $terms, $fragment = false ) {
		if ( ! is_string( $html ) || '' === $html || ! $terms ) {
			return $html;
		}
		$pattern = self::pattern( $terms );
		if ( ! preg_match( $pattern, $html ) ) {
			return $html;
		}
		if ( $fragment ) {
			$start = 0;
		} else {
			$start = preg_match( '/<body[\s>]/i', $html, $m, PREG_OFFSET_CAPTURE ) ? $m[0][1] : false;
			if ( false === $start || 0 === stripos( ltrim( $html ), '<?xml' ) ) {
				return $html; // Not an HTML page (JSON, XML, text…) or a document without body: leave it.
			}
		}
		$head  = substr( $html, 0, $start );
		$body  = substr( $html, $start );
		$parts = preg_split( '/(<!--.*?-->|<(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>)/s', $body, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}
		$skip    = '';
		$wrapped = false;
		$open    = '<span class="notranslate" translate="no" data-dpk-brand>';
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( $skip ) {
					if ( preg_match( '#^</' . $skip . '\s*>#i', $part ) ) {
						$skip = '';
					}
					continue;
				}
				if ( 0 === strpos( $part, '<!--' ) ) {
					continue;
				}
				if ( preg_match( '#^<(script|style|textarea|title|option|template|noscript|svg|math)[\s>]#i', $part, $m ) && ! preg_match( '#/>$#', $part ) ) {
					$skip = strtolower( $m[1] );
					continue;
				}
				// Images / inputs: their alt, title, placeholder or value are translated too.
				if ( preg_match( '#^<(img|input|area)\b#i', $part ) && false === stripos( $part, 'translate=' )
					&& preg_match( '/\s(?:alt|title|placeholder|value)\s*=\s*("[^"]*"|\'[^\']*\')/i', $part, $attr ) && preg_match( $pattern, html_entity_decode( $attr[1], ENT_QUOTES, 'UTF-8' ) ) ) {
					$parts[ $i ] = preg_replace( '#\s*/?>$#', ' translate="no"$0', $part, 1 );
				}
				$wrapped = false !== strpos( $part, 'data-dpk-brand' );
				continue;
			}
			if ( $skip || $wrapped ) {
				$wrapped = false;
				continue; // Inside a skipped element, or already wrapped by an earlier pass.
			}
			$parts[ $i ] = preg_replace( $pattern, $open . '$0</span>', $part );
		}
		return $head . implode( '', $parts );
	}

	/* ------------------------------------------------------------------
	 * Settings screen
	 * ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page( Admin::SLUG, __( 'Languages', 'douce-paresse-kit' ), __( 'Languages', 'douce-paresse-kit' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	public function register_setting() {
		register_setting(
			'dpk_i18n_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize( $in ) {
		$in = (array) $in;
		return array(
			'protect' => empty( $in['protect'] ) ? '' : 'yes',
			'terms'   => implode( "\n", array_slice( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', (string) ( $in['terms'] ?? '' ) ) ) ), 0, 50 ) ),
		);
	}

	/** The header shows the switcher only while GTranslate is active (Automatic mode). */
	public function plugin_toggled( $plugin ) {
		if ( 0 === strpos( (string) $plugin, 'gtranslate/' ) ) {
			$this->flush_elementor();
		}
	}

	/** Cached Elementor output must be rebuilt after a change. */
	public function flush_elementor() {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o  = self::get();
		$gt = get_option( 'GTranslate' );
		?>
		<div class="wrap dpk-admin">
			<h1><?php esc_html_e( 'Douce Paresse — Languages', 'douce-paresse-kit' ); ?></h1>
			<p class="dpk-lead"><?php esc_html_e( 'The site is translated by the GTranslate plugin. The kit keeps your brand name in French in every language and replaces the floating GTranslate button with a discreet “FR | EN” switcher in the header (desktop) and in the menu (tablet / mobile).', 'douce-paresse-kit' ); ?></p>

			<div class="dpk-card">
				<h2><?php esc_html_e( 'GTranslate', 'douce-paresse-kit' ); ?></h2>
				<?php if ( self::gtranslate_active() ) : ?>
					<p><span class="dashicons dashicons-yes-alt" style="color:#008a20"></span> <?php esc_html_e( 'GTranslate is active.', 'douce-paresse-kit' ); ?>
					<?php
					if ( is_array( $gt ) && ! empty( $gt['default_language'] ) ) {
						/* translators: %s: language code */
						echo ' ' . esc_html( sprintf( __( 'Original language: %s.', 'douce-paresse-kit' ), $gt['default_language'] ) );
					}
					?>
					</p>
				<?php else : ?>
					<p><span class="dashicons dashicons-warning" style="color:#d63638"></span> <?php esc_html_e( 'GTranslate is not active: the FR | EN switcher is hidden (header setting “Show” = Automatic).', 'douce-paresse-kit' ); ?></p>
				<?php endif; ?>
				<ol>
					<li><?php esc_html_e( 'In Settings → GTranslate keep French as the original language and English among the languages (Widget look “Float” is fine: the kit hides the floating button when its own switcher is on the page).', 'douce-paresse-kit' ); ?></li>
					<li><?php esc_html_e( 'The switcher is in the DP Header widget (Content → Languages): labels and language codes, show / hide, and the option to hide the floating button.', 'douce-paresse-kit' ); ?></li>
				</ol>
			</div>

			<form method="post" action="options.php" class="dpk-card">
				<?php settings_fields( 'dpk_i18n_group' ); ?>
				<h2><?php esc_html_e( 'Never translate', 'douce-paresse-kit' ); ?></h2>
				<p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[protect]" value="yes" <?php checked( 'yes', $o['protect'] ); ?>> <?php esc_html_e( 'Keep these words in their original form in every language', 'douce-paresse-kit' ); ?></label></p>
				<p><label><strong><?php esc_html_e( 'Words / brand names (one per line)', 'douce-paresse-kit' ); ?></strong><br>
					<textarea name="<?php echo esc_attr( self::OPTION ); ?>[terms]" rows="4" class="large-text code"><?php echo esc_textarea( $o['terms'] ); ?></textarea></label></p>
				<p class="description"><?php esc_html_e( 'Upper / lower case does not matter (“DOUCE PARESSE” is protected too). Works on every page, in the header, footer, page content and any Elementor widget. Clear your cache after a change.', 'douce-paresse-kit' ); ?></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

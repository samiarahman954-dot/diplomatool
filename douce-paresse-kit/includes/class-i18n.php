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
			'fixes'   => array(),
		);
	}

	const MAX_FIXES = 400;

	/** Tags allowed in a corrected English passage. */
	public static function fix_tags() {
		return array(
			'a'      => array( 'href' => true, 'title' => true, 'target' => true, 'rel' => true ),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'br'     => array(),
			'span'   => array( 'class' => true ),
		);
	}

	/** English corrections for the front end: [ { fr, en } ], complete rows only. */
	public static function fixes() {
		$o = self::get();
		return array_values(
			array_filter(
				(array) $o['fixes'],
				function ( $f ) {
					return is_array( $f ) && '' !== trim( (string) ( $f['fr'] ?? '' ) ) && '' !== trim( (string) ( $f['en'] ?? '' ) );
				}
			)
		);
	}

	/**
	 * "FR: … / EN: …" blocks pasted in the bulk box → rows. A block may span several lines;
	 * a new "FR:" starts the next correction.
	 */
	public static function parse_bulk( $text ) {
		$rows = array();
		$cur  = null;
		$key  = '';
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			if ( preg_match( '/^\s*(FR|EN)\s*[:：]\s*(.*)$/iu', $line, $m ) ) {
				$key = strtolower( $m[1] );
				if ( 'fr' === $key ) {
					if ( $cur ) {
						$rows[] = $cur;
					}
					$cur = array( 'fr' => $m[2], 'en' => '' );
				} elseif ( $cur ) {
					$cur['en'] = $m[2];
				}
			} elseif ( $cur && $key && '' !== trim( $line ) ) {
				$cur[ $key ] .= ( '' === $cur[ $key ] ? '' : ' ' ) . trim( $line );
			}
		}
		if ( $cur ) {
			$rows[] = $cur;
		}
		return $rows;
	}

	/** One row, cleaned. Null when the French text is empty. */
	public static function clean_fix( $row ) {
		$fr = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $row['fr'] ?? '' ) ) ) );
		$en = trim( wp_kses( (string) ( $row['en'] ?? '' ), self::fix_tags() ) );
		if ( '' === $fr ) {
			return null;
		}
		return array(
			'fr' => mb_substr( $fr, 0, 5000 ),
			'en' => mb_substr( $en, 0, 10000 ),
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

	/**
	 * GTranslate settings the switcher needs (read from GTranslate's own option, so it works even when
	 * GTranslate prints no widget on the page).
	 */
	public static function gt_config() {
		$d = get_option( 'GTranslate' );
		$d = is_array( $d ) ? $d : array();
		$mode = 'none';
		if ( ! empty( $d['pro_version'] ) ) {
			$mode = 'sub_directory';
		} elseif ( ! empty( $d['enterprise_version'] ) ) {
			$mode = 'sub_domain';
		}
		$langs = array();
		foreach ( array( 'fincl_langs', 'incl_langs' ) as $k ) {
			if ( ! empty( $d[ $k ] ) && is_array( $d[ $k ] ) ) {
				$langs = array_merge( $langs, array_map( 'strval', $d[ $k ] ) );
			}
		}
		return array(
			'active'    => self::gtranslate_active(),
			'source'    => ! empty( $d['default_language'] ) ? (string) $d['default_language'] : '',
			'mode'      => $mode,
			'languages' => array_values( array_unique( $langs ) ),
			'floating'  => isset( $d['floating_language_selector'] ) ? (string) $d['floating_language_selector'] : '',
			'look'      => isset( $d['widget_look'] ) ? (string) $d['widget_look'] : '',
		);
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
		return '/(?<![\p{L}\p{N}&#])(?:' . implode( '|', $alts ) . ')(?![\p{L}\p{N}])/iu'; // Not inside &copy; / &#…;
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
		$head = substr( $html, 0, $start );
		$body = substr( $html, $start );

		// Set whole raw-text / foreign blocks aside first: a "<" inside a script (i<n) must not be read as
		// a tag, and nothing inside them may be changed.
		$kept = array();
		$body = preg_replace_callback(
			'#<!--.*?-->|<(script|style|textarea|title|option|template|noscript|svg|math)\b(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>.*?</\1\s*>#is',
			function ( $m ) use ( &$kept ) {
				$kept[] = $m[0];
				return "\x1A" . ( count( $kept ) - 1 ) . "\x1A";
			},
			$body
		);
		if ( null === $body ) {
			return $html;
		}
		$parts = preg_split( '/(<(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>)/s', $body, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}
		$wrapped = false;
		$open    = '<span class="notranslate" translate="no" data-dpk-brand>';
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				// Images / inputs: their alt, title, placeholder or value are translated too.
				if ( preg_match( '#^<(img|input|area)\b#i', $part ) && false === stripos( $part, 'translate=' )
					&& preg_match( '/\s(?:alt|title|placeholder|value)\s*=\s*("[^"]*"|\'[^\']*\')/i', $part, $attr ) && preg_match( $pattern, html_entity_decode( $attr[1], ENT_QUOTES, 'UTF-8' ) ) ) {
					$parts[ $i ] = preg_replace( '#\s*/?>$#', ' translate="no"$0', $part, 1 );
				}
				$wrapped = false !== strpos( $part, 'data-dpk-brand' );
				continue;
			}
			if ( $wrapped ) {
				$wrapped = false;
				continue; // Already wrapped by an earlier pass.
			}
			$parts[ $i ] = preg_replace( $pattern, $open . '$0</span>', $part );
		}
		$body = implode( '', $parts );
		if ( $kept ) {
			$body = preg_replace_callback(
				'/\x1A(\d+)\x1A/',
				function ( $m ) use ( $kept ) {
					return $kept[ (int) $m[1] ];
				},
				$body
			);
		}
		return $head . $body;
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
		$in    = (array) $in;
		$rows  = array_merge( array_values( (array) ( $in['fixes'] ?? array() ) ), self::parse_bulk( $in['fixes_bulk'] ?? '' ) );
		$fixes = array();
		$seen  = array();
		foreach ( $rows as $row ) {
			$row = is_array( $row ) && empty( $row['delete'] ) ? self::clean_fix( $row ) : null;
			if ( ! $row ) {
				continue;
			}
			$key = mb_strtolower( $row['fr'] );
			if ( isset( $seen[ $key ] ) ) {
				$fixes[ $seen[ $key ] ] = $row; // The same French text twice: the last English wins (bulk paste updates).
				continue;
			}
			$seen[ $key ] = count( $fixes );
			$fixes[]      = $row;
		}
		return array(
			'protect' => empty( $in['protect'] ) ? '' : 'yes',
			'terms'   => implode( "\n", array_slice( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', (string) ( $in['terms'] ?? '' ) ) ) ), 0, 50 ) ),
			'fixes'   => array_slice( $fixes, 0, self::MAX_FIXES ),
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

				<h2 id="dpk-fixes"><?php esc_html_e( 'English corrections', 'douce-paresse-kit' ); ?></h2>
				<p><?php esc_html_e( 'GTranslate translates the site automatically. For any passage whose English is not right, write the French text exactly as it is on the site and your own English: visitors reading in English see your English, GTranslate no longer translates that passage. Everything else is still translated by GTranslate.', 'douce-paresse-kit' ); ?></p>
				<ul class="ul-disc">
					<li><?php esc_html_e( 'French text: copy it from the page (a title, a paragraph, a button, or one sentence of a paragraph). Upper / lower case, spaces and the kind of apostrophe do not matter.', 'douce-paresse-kit' ); ?></li>
					<li><?php esc_html_e( 'If you later change that French text on the page, change it here too (otherwise GTranslate translates the new text again).', 'douce-paresse-kit' ); ?></li>
					<li><?php esc_html_e( 'English: plain text; <strong>, <em>, <br> and links are allowed. Clear your cache after a change.', 'douce-paresse-kit' ); ?></li>
				</ul>
				<?php $fixes = array_values( (array) $o['fixes'] ); ?>
				<table class="widefat striped dpk-fixes">
					<thead><tr><th style="width:45%"><?php esc_html_e( 'French text on the site', 'douce-paresse-kit' ); ?></th><th style="width:45%"><?php esc_html_e( 'Correct English', 'douce-paresse-kit' ); ?></th><th><?php esc_html_e( 'Delete', 'douce-paresse-kit' ); ?></th></tr></thead>
					<tbody data-dpk-fix-rows data-next="<?php echo esc_attr( count( $fixes ) + 1 ); ?>">
					<?php foreach ( array_merge( $fixes, array( array( 'fr' => '', 'en' => '' ) ) ) as $i => $f ) : ?>
						<tr>
							<td><textarea name="<?php echo esc_attr( self::OPTION . '[fixes][' . $i . '][fr]' ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $f['fr'] ?? '' ); ?></textarea></td>
							<td><textarea name="<?php echo esc_attr( self::OPTION . '[fixes][' . $i . '][en]' ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $f['en'] ?? '' ); ?></textarea></td>
							<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION . '[fixes][' . $i . '][delete]' ); ?>" value="1"> <span class="screen-reader-text"><?php esc_html_e( 'Delete', 'douce-paresse-kit' ); ?></span></label></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" data-dpk-fix-add><?php esc_html_e( 'Add a row', 'douce-paresse-kit' ); ?></button>
					<?php
					/* translators: %d: number of corrections */
					echo ' ' . esc_html( sprintf( _n( '%d correction saved.', '%d corrections saved.', count( $fixes ), 'douce-paresse-kit' ), count( $fixes ) ) );
					?>
				</p>
				<details>
					<summary><strong><?php esc_html_e( 'Add many at once (paste)', 'douce-paresse-kit' ); ?></strong></summary>
					<p class="description"><?php esc_html_e( 'One block per correction: a line starting with “FR:” then a line starting with “EN:”. A French text already in the table gets the new English.', 'douce-paresse-kit' ); ?></p>
					<textarea name="<?php echo esc_attr( self::OPTION ); ?>[fixes_bulk]" rows="8" class="large-text code" placeholder="FR: Et si un détail faisait toute la différence ?&#10;EN: What if one detail made all the difference?&#10;&#10;FR: …&#10;EN: …"></textarea>
				</details>
				<?php submit_button(); ?>
			</form>
			<script>
			( function () {
				var add = document.querySelector( '[data-dpk-fix-add]' );
				var rows = document.querySelector( '[data-dpk-fix-rows]' );
				if ( ! add || ! rows ) { return; }
				add.addEventListener( 'click', function () {
					var last = rows.lastElementChild;
					var row = last.cloneNode( true );
					var n = parseInt( rows.getAttribute( 'data-next' ), 10 );
					rows.setAttribute( 'data-next', n + 1 );
					row.querySelectorAll( 'textarea, input' ).forEach( function ( f ) {
						f.name = f.name.replace( /\[fixes\]\[\d+\]/, '[fixes][' + n + ']' );
						if ( 'checkbox' === f.type ) { f.checked = false; } else { f.value = ''; }
					} );
					rows.appendChild( row );
					row.querySelector( 'textarea' ).focus();
				} );
			} )();
			</script>
		</div>
		<?php
	}
}

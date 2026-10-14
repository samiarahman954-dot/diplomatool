<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * Diagnostics.
 *
 * - WP Admin → Douce Paresse → Diagnostics: server-side checks (versions, plugin files, cache /
 *   optimisation plugins, Elementor settings, Theme Builder header/footer and their conditions,
 *   kit pages, header widget settings, the HTML visitors really receive, PHP errors, e-mail).
 * - Any front-end page + ?dpk-debug=1 (administrators only): a panel that checks the page in the
 *   real browser (header fixed or not and why, CSS rules, ancestors, covering elements, fonts,
 *   images, overflow, JS errors, forms) with a "Copy report" button.
 */
final class Debug {

	const PAGE       = 'dpk-debug';
	const OPT_MAIL   = 'dpk_last_mail_error';
	const QUERY      = 'dpk-debug';

	/** Plugins known to cache pages or to combine / delay / defer JavaScript and CSS. */
	const OPTIMIZERS = array(
		'wp-rocket'                   => 'WP Rocket',
		'litespeed-cache'             => 'LiteSpeed Cache',
		'w3-total-cache'              => 'W3 Total Cache',
		'wp-super-cache'              => 'WP Super Cache',
		'wp-fastest-cache'            => 'WP Fastest Cache',
		'sg-cachepress'               => 'SiteGround Speed Optimizer',
		'autoptimize'                 => 'Autoptimize',
		'perfmatters'                 => 'Perfmatters',
		'hummingbird-performance'     => 'Hummingbird',
		'wp-optimize'                 => 'WP-Optimize',
		'jetpack-boost'               => 'Jetpack Boost',
		'nitropack'                   => 'NitroPack',
		'breeze'                      => 'Breeze',
		'flying-scripts'              => 'Flying Scripts',
		'wp-asset-clean-up'           => 'Asset CleanUp',
		'swift-performance-lite'      => 'Swift Performance',
		'cache-enabler'               => 'Cache Enabler',
		'comet-cache'                 => 'Comet Cache',
		'powered-cache'               => 'Powered Cache',
		'speed-optimizer'             => 'Speed Optimizer',
		'hostinger'                   => 'Hostinger tools',
		'cloudflare'                  => 'Cloudflare',
		'async-javascript'            => 'Async JavaScript',
		'phastpress'                  => 'PhastPress',
		'rocket-lazy-load'            => 'Lazy Load by WP Rocket',
		'debloat'                     => 'Debloat',
		'fast-velocity-minify'        => 'Fast Velocity Minify',
	);

	/** @var Debug|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'wp_mail_failed', array( $this, 'mail_failed' ) );
		add_action( 'init', array( $this, 'front_init' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
	}

	/* ------------------------------------------------------------------
	 * Hooks
	 * ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page( Admin::SLUG, __( 'Diagnostics', 'douce-paresse-kit' ), __( 'Diagnostics', 'douce-paresse-kit' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	public function mail_failed( $error ) {
		if ( is_wp_error( $error ) ) {
			update_option( self::OPT_MAIL, array( 'time' => time(), 'message' => $error->get_error_message() ), false );
		}
	}

	public static function front_active() {
		return ! is_admin() && isset( $_GET[ self::QUERY ] ) && is_user_logged_in() && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public function front_init() {
		if ( ! self::front_active() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // Never let a page cache store the debug view.
		}
		nocache_headers();
		add_action( 'wp_head', array( $this, 'head_probe' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'front_assets' ), 99 );
	}

	public function admin_bar( $bar ) {
		if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$on = self::front_active();
		$bar->add_node(
			array(
				'id'    => 'dpk-debug',
				'title' => $on ? 'DP Debug ✓' : 'DP Debug',
				'href'  => $on ? remove_query_arg( self::QUERY ) : add_query_arg( self::QUERY, '1' ),
				'meta'  => array( 'title' => __( 'Douce Paresse: check this page in the browser', 'douce-paresse-kit' ) ),
			)
		);
	}

	/**
	 * Printed first in <head>: records JS errors from the very start and takes a snapshot of the header
	 * and its ancestors before the kit script changes anything.
	 */
	public function head_probe() {
		?>
<script id="dpk-debug-probe">
(function(){var d=window.__dpkDebug={errors:[],start:Date.now(),snapshot:null};
window.addEventListener('error',function(e){var t=e.target;if(t&&t!==window&&(t.src||t.href)){d.errors.push({type:'resource',msg:'Failed to load '+(t.tagName||'')+' '+(t.src||t.href)});return;}d.errors.push({type:'js',msg:String(e.message),src:(e.filename||'')+':'+(e.lineno||0)});},true);
window.addEventListener('unhandledrejection',function(e){d.errors.push({type:'promise',msg:String(e.reason&&e.reason.message||e.reason)});});
document.addEventListener('DOMContentLoaded',function(){var r=document.querySelector('[data-dpk-header]');if(!r){return;}var c=[],P=['position','transform','filter','backdropFilter','perspective','willChange','contain','containerType','zIndex','opacity','overflow','isolation','animationName','mixBlendMode','clipPath','display','visibility'];
for(var el=r;el;el=el.parentElement){var s=getComputedStyle(el),o={el:el.tagName.toLowerCase()+(el.id?'#'+el.id:'')+(el.className&&typeof el.className==='string'?'.'+el.className.trim().split(/\s+/).slice(0,4).join('.'):''),ds:el.getAttribute('data-settings')||''};P.forEach(function(p){o[p]=s[p];});c.push(o);}
var b=r.querySelector('.dpk-header__bar');d.snapshot={chain:c,bar:b?{position:getComputedStyle(b).position,top:getComputedStyle(b).top}:null};});
})();
</script>
		<?php
	}

	public function front_assets() {
		wp_enqueue_style( 'dpk-debug', DPK_URL . 'assets/css/debug.css', array(), DPK_VERSION );
		wp_enqueue_script( 'dpk-debug', DPK_URL . 'assets/js/debug.js', array(), DPK_VERSION, true );
		wp_localize_script(
			'dpk-debug',
			'DPKDebug',
			array(
				'version' => DPK_VERSION,
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'admin'   => admin_url( 'admin.php?page=' . self::PAGE ),
				'server'  => $this->summary_lines(),
				'header'  => $this->header_info(),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Checks
	 * ------------------------------------------------------------------ */

	private static function row( $state, $label, $message, $details = '' ) {
		return compact( 'state', 'label', 'message', 'details' );
	}

	/** Elementor experiment state, safe on every version. */
	private static function experiment( $name ) {
		try {
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) ) {
				return \Elementor\Plugin::$instance->experiments->is_feature_active( $name ) ? 'on' : 'off';
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		}
		return '?';
	}

	/** Every element of an Elementor tree, with the chain of its parents. */
	private static function walk( $elements, $parents, $cb ) {
		foreach ( (array) $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$cb( $el, $parents );
			if ( ! empty( $el['elements'] ) ) {
				self::walk( $el['elements'], array_merge( $parents, array( $el ) ), $cb );
			}
		}
	}

	private static function elementor_data( $id ) {
		$raw  = get_post_meta( $id, '_elementor_data', true );
		$data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array();
	}

	/** Settings on an element that can stop a fixed header from staying on screen. */
	private static function risky_settings( $settings ) {
		$found = array();
		foreach ( (array) $settings as $k => $v ) {
			if ( '' === $v || null === $v || array() === $v ) {
				continue;
			}
			if ( preg_match( '/^(_?animation|motion_fx_|_transform_|sticky|_position|position$|z_index|_z_index|css_filters|custom_css|_overflow|overflow)/', $k ) ) {
				if ( preg_match( '/^(_?animation_duration|animation_delay)$/', $k ) ) {
					continue;
				}
				$found[ $k ] = is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
			}
		}
		return $found;
	}

	/** The kit header template and the settings of its DP Header widget. */
	public function header_info() {
		$importer = new Importer();
		$id       = $importer->find_post( 'header' );
		$info     = array(
			'template_id' => $id,
			'status'      => $id ? get_post_status( $id ) : '',
			'type'        => $id ? get_post_meta( $id, '_elementor_template_type', true ) : '',
			'conditions'  => $id ? (array) get_post_meta( $id, '_elementor_conditions', true ) : array(),
			'widgets'     => array(),
		);
		if ( ! $id ) {
			return $info;
		}
		self::walk(
			self::elementor_data( $id ),
			array(),
			function ( $el, $parents ) use ( &$info ) {
				if ( 'widget' !== ( $el['elType'] ?? '' ) || 'dpk-header' !== ( $el['widgetType'] ?? '' ) ) {
					return;
				}
				$s       = (array) ( $el['settings'] ?? array() );
				$risky   = array();
				foreach ( $parents as $p ) {
					$r = self::risky_settings( $p['settings'] ?? array() );
					if ( $r ) {
						$risky[ ( $p['elType'] ?? '?' ) . ' ' . ( $p['id'] ?? '' ) ] = $r;
					}
				}
				$own = self::risky_settings( $s );
				if ( $own ) {
					$risky[ 'widget ' . ( $el['id'] ?? '' ) ] = $own;
				}
				$info['widgets'][] = array(
					'id'     => $el['id'] ?? '',
					'fixed'  => array_key_exists( 'dpk_fixed', $s ) ? (string) $s['dpk_fixed'] : '(default: yes)',
					'risky'  => $risky,
				);
			}
		);
		return $info;
	}

	/** Theme Builder templates of one type, with their conditions. */
	private static function templates( $type ) {
		$ids = get_posts(
			array(
				'post_type'        => 'elementor_library',
				'post_status'      => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'meta_key'         => '_elementor_template_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $type, // phpcs:ignore WordPress.DB.SlowDBQuery
				'numberposts'      => 50,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = array(
				'id'         => (int) $id,
				'title'      => get_the_title( $id ),
				'status'     => get_post_status( $id ),
				'kit'        => (bool) get_post_meta( $id, Importer::META_KEY, true ),
				'conditions' => array_values( (array) get_post_meta( $id, '_elementor_conditions', true ) ),
			);
		}
		return $out;
	}

	/** Short text lines (also sent to the browser panel, so the copied report has both sides). */
	public function summary_lines() {
		global $wp_version;
		$theme = wp_get_theme();
		$lines = array(
			'Kit ' . DPK_VERSION . ' | WP ' . $wp_version . ' | PHP ' . PHP_VERSION,
			'Elementor ' . ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'NOT ACTIVE' ) . ' | Elementor Pro ' . ( defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : 'NOT ACTIVE' ),
			'Theme ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( $theme->parent() ? ' (child of ' . $theme->parent()->get( 'Name' ) . ')' : '' ),
			'Permalinks: ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Plain' ),
			'Optimisation/cache plugins: ' . ( $this->optimizers() ? implode( ', ', $this->optimizers() ) : 'none' ) . ( defined( 'WP_CACHE' ) && WP_CACHE ? ' | WP_CACHE on' : '' ),
			'Elementor: CSS print ' . get_option( 'elementor_css_print_method', 'external' ) . ' | optimized markup ' . self::experiment( 'e_optimized_markup' ) . ' | element cache ' . self::experiment( 'e_element_cache' ) . ' | containers ' . self::experiment( 'container' ),
		);
		foreach ( array( 'header', 'footer' ) as $type ) {
			$parts = array();
			foreach ( self::templates( $type ) as $t ) {
				$parts[] = '#' . $t['id'] . ( $t['kit'] ? ' (kit)' : '' ) . ' ' . $t['status'] . ' [' . implode( ', ', $t['conditions'] ) . ']';
			}
			$lines[] = ucfirst( $type ) . ' templates: ' . ( $parts ? implode( '; ', $parts ) : 'none' );
		}
		$h = $this->header_info();
		foreach ( $h['widgets'] as $w ) {
			$lines[] = 'DP Header widget ' . $w['id'] . ': fixed=' . $w['fixed'] . ( $w['risky'] ? ' | settings: ' . wp_json_encode( $w['risky'] ) : '' );
		}
		return $lines;
	}

	private function optimizers() {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$found = array();
		foreach ( $active as $file ) {
			$slug = strtok( (string) $file, '/' );
			if ( isset( self::OPTIMIZERS[ $slug ] ) ) {
				$found[] = self::OPTIMIZERS[ $slug ];
			}
		}
		return $found;
	}

	/** Settings of optimisation plugins that delay or combine JavaScript (the kit script must run on load). */
	private function js_delay_settings() {
		$found = array();
		$rocket = get_option( 'wp_rocket_settings' );
		if ( is_array( $rocket ) ) {
			foreach ( array( 'delay_js' => 'Delay JavaScript execution', 'defer_all_js' => 'Load JavaScript deferred', 'minify_concatenate_js' => 'Combine JavaScript', 'remove_unused_css' => 'Remove unused CSS' ) as $k => $label ) {
				if ( ! empty( $rocket[ $k ] ) ) {
					$found[] = 'WP Rocket: ' . $label;
				}
			}
		}
		$perf = get_option( 'perfmatters_options' );
		if ( is_array( $perf ) && ! empty( $perf['assets']['delay_js'] ) ) {
			$found[] = 'Perfmatters: Delay JavaScript';
		}
		if ( is_array( $perf ) && ! empty( $perf['assets']['remove_unused_css'] ) ) {
			$found[] = 'Perfmatters: Remove unused CSS';
		}
		foreach ( array( 'litespeed.conf.optm-js_delay_inc' => 'LiteSpeed: JS delayed includes', 'litespeed.conf.optm-js_comb' => 'LiteSpeed: JS combine', 'litespeed.conf.optm-ucss' => 'LiteSpeed: Generate UCSS (removes unused CSS)', 'litespeed.conf.optm-css_comb' => 'LiteSpeed: CSS combine' ) as $opt => $label ) {
			$v = get_option( $opt );
			if ( ! empty( $v ) ) {
				$found[] = $label;
			}
		}
		if ( 'on' === get_option( 'autoptimize_js' ) ) {
			$found[] = 'Autoptimize: Optimize JavaScript';
		}
		if ( 'on' === get_option( 'autoptimize_css' ) ) {
			$found[] = 'Autoptimize: Optimize CSS';
		}
		if ( get_option( 'siteground_optimizer_combine_javascript' ) ) {
			$found[] = 'SiteGround: Combine JavaScript';
		}
		return $found;
	}

	/** All server checks, as rows. */
	public function checks() {
		global $wp_version;
		$rows     = array();
		$importer = new Importer();

		/* Environment */
		$rows[] = self::row( 'info', 'Versions', implode( ' · ', array_slice( $this->summary_lines(), 0, 3 ) ) );
		$rows[] = self::row( defined( 'ELEMENTOR_VERSION' ) ? 'ok' : 'error', 'Elementor', defined( 'ELEMENTOR_VERSION' ) ? 'Active (' . ELEMENTOR_VERSION . ')' : 'Not active: no widget can render.' );
		$rows[] = self::row( defined( 'ELEMENTOR_PRO_VERSION' ) ? 'ok' : 'error', 'Elementor Pro', defined( 'ELEMENTOR_PRO_VERSION' ) ? 'Active (' . ELEMENTOR_PRO_VERSION . ')' : 'Not active: the header/footer cannot be placed by the Theme Builder.' );
		$block  = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
		$rows[] = self::row( $block ? 'error' : 'ok', 'Theme', wp_get_theme()->get( 'Name' ) . ( $block ? ' is a block theme: Theme Builder header/footer are not supported.' : '' ) );
		$perma  = get_option( 'permalink_structure' );
		$rows[] = self::row( $perma ? 'ok' : 'warn', 'Permalinks', $perma ? $perma : 'Plain (?page_id=N). Works, but “Post name” is recommended.' );

		/* Plugin copies and files */
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$copies = array();
		foreach ( get_plugins() as $file => $p ) {
			if ( false !== stripos( $p['Name'], 'Douce Paresse' ) ) {
				$copies[] = $file . ' v' . $p['Version'] . ( is_plugin_active( $file ) ? ' (active)' : '' );
			}
		}
		$rows[] = self::row( count( $copies ) > 1 ? 'warn' : 'ok', 'Plugin copies', count( $copies ) > 1 ? 'Several copies are installed. Delete the old ones so only the latest version is active.' : 'One copy, ' . plugin_basename( DPK_FILE ), implode( "\n", $copies ) );
		$js     = (string) @file_get_contents( DPK_PATH . 'assets/js/frontend.js' ); // phpcs:ignore
		$css    = (string) @file_get_contents( DPK_PATH . 'assets/css/frontend.css' ); // phpcs:ignore
		$files  = false !== strpos( $js, 'liftHeader' ) && false !== strpos( $css, 'dpk-header-portal' );
		$rows[] = self::row( $files ? 'ok' : 'error', 'Plugin files', $files ? 'frontend.js / frontend.css are the current version.' : 'frontend.js / frontend.css on the server are OLD. Upload the zip again with “Replace current with uploaded”.' );

		/* Optimisation */
		$opt    = $this->optimizers();
		$delay  = $this->js_delay_settings();
		$rows[] = self::row( $opt ? 'warn' : 'ok', 'Cache / optimisation plugins', $opt ? implode( ', ', $opt ) . '. Purge their cache after every kit update.' : 'None detected.' );
		if ( $delay ) {
			$rows[] = self::row( 'warn', 'JavaScript / CSS optimisation', 'These settings can stop or postpone the kit script (fixed header, menu, forms) or remove kit CSS. Exclude “douce-paresse-kit” from them.', implode( "\n", $delay ) );
		}
		$rows[] = self::row( defined( 'WP_CACHE' ) && WP_CACHE ? 'warn' : 'ok', 'Page cache (WP_CACHE)', defined( 'WP_CACHE' ) && WP_CACHE ? 'On: visitors may get an old copy of the pages until the cache is purged.' : 'Off.' );

		/* Elementor */
		$rows[] = self::row( 'info', 'Elementor settings', 'CSS print method: ' . get_option( 'elementor_css_print_method', 'external' ) . ' · Optimized markup: ' . self::experiment( 'e_optimized_markup' ) . ' · Element caching: ' . self::experiment( 'e_element_cache' ) . ' · Flexbox containers: ' . self::experiment( 'container' ) );

		/* Theme Builder */
		foreach ( array( 'header', 'footer' ) as $type ) {
			$list   = self::templates( $type );
			$active = array_filter(
				$list,
				function ( $t ) {
					return 'publish' === $t['status'] && $t['conditions'];
				}
			);
			$kit = array_filter(
				$list,
				function ( $t ) {
					return $t['kit'];
				}
			);
			$detail = '';
			foreach ( $list as $t ) {
				$detail .= '#' . $t['id'] . ' “' . $t['title'] . '” ' . $t['status'] . ( $t['kit'] ? ' (kit)' : '' ) . ' conditions: ' . ( $t['conditions'] ? implode( ', ', $t['conditions'] ) : 'none' ) . "\n";
			}
			if ( ! $kit ) {
				$rows[] = self::row( 'error', ucfirst( $type ) . ' template', 'The kit ' . $type . ' is not imported. Run the demo import.', $detail );
			} elseif ( count( $active ) > 1 ) {
				$rows[] = self::row( 'error', ucfirst( $type ) . ' template', count( $active ) . ' published ' . $type . ' templates have display conditions. Elementor shows only one: keep the kit one, set the others to draft or remove their conditions.', $detail );
			} else {
				$t      = reset( $kit );
				$ok     = 'publish' === $t['status'] && in_array( 'include/general', $t['conditions'], true );
				$rows[] = self::row( $ok ? 'ok' : 'error', ucfirst( $type ) . ' template', $ok ? 'Kit ' . $type . ' #' . $t['id'] . ' is published with “Entire Site”.' : 'Kit ' . $type . ' #' . $t['id'] . ' is ' . $t['status'] . ' / conditions: ' . ( $t['conditions'] ? implode( ', ', $t['conditions'] ) : 'none' ) . '. Publish it with the condition “Entire Site” (or run Smart sync).', $detail );
			}
		}

		/* Header widget */
		$h = $this->header_info();
		if ( $h['template_id'] ) {
			if ( ! $h['widgets'] ) {
				$rows[] = self::row( 'error', 'DP Header widget', 'The kit header template contains no DP Header widget.' );
			}
			foreach ( $h['widgets'] as $w ) {
				$sticky = 'yes' === $w['fixed'] || '(default: yes)' === $w['fixed'];
				$rows[] = self::row( $sticky ? 'ok' : 'error', 'Header “Fixed on scroll”', $sticky ? 'On.' : 'OFF in the widget (Content → Fixed on scroll). Switch it on and update the header.' );
				if ( $w['risky'] ) {
					$rows[] = self::row( 'warn', 'Header wrapper settings', 'Animation / motion / position / z-index settings found on the header or its containers. The kit lifts the header out of them on the live site; remove them if anything still looks wrong.', wp_json_encode( $w['risky'], JSON_PRETTY_PRINT ) );
				}
			}
		}

		/* Pages */
		$missing = array();
		$details = '';
		foreach ( $importer->items() as $key => $item ) {
			if ( 'page' !== ( $item['kind'] ?? '' ) ) {
				continue;
			}
			$id = $importer->find_post( $key );
			if ( ! $id ) {
				$missing[] = $key;
				continue;
			}
			$details .= $key . ' #' . $id . ' ' . get_post_status( $id ) . ' ' . get_permalink( $id ) . ' template=' . get_post_meta( $id, '_wp_page_template', true ) . ' builder=' . get_post_meta( $id, '_elementor_edit_mode', true ) . "\n";
		}
		$rows[] = self::row( $missing ? 'warn' : 'ok', 'Kit pages', $missing ? 'Not imported: ' . implode( ', ', $missing ) : 'All pages exist.', $details );
		$front  = (int) get_option( 'page_on_front' );
		$rows[] = self::row( 'page' === get_option( 'show_on_front' ) && $front === $importer->find_post( 'home' ) ? 'ok' : 'warn', 'Front page', 'page' === get_option( 'show_on_front' ) ? 'Static page #' . $front . ' (kit Accueil = #' . $importer->find_post( 'home' ) . ')' : 'Latest posts (the kit Accueil page is not the front page).' );

		/* Images */
		$lost = array();
		foreach ( $importer->image_files() as $file ) {
			$id = $importer->find_image( $file );
			if ( ! $id || ! file_exists( (string) get_attached_file( $id ) ) ) {
				$lost[] = $file;
			}
		}
		$rows[] = self::row( $lost ? 'warn' : 'ok', 'Kit images', $lost ? count( $lost ) . ' image(s) missing in the Media Library: run the import again.' : 'All images present.', implode( "\n", $lost ) );

		/* E-mail and forms */
		$mail   = get_option( self::OPT_MAIL );
		$rows[] = self::row( $mail ? 'warn' : 'ok', 'E-mail sending', $mail ? 'Last failure ' . human_time_diff( (int) $mail['time'] ) . ' ago: ' . $mail['message'] . ' (install an SMTP plugin).' : 'No failure recorded.' );
		foreach ( $this->form_recipients() as $f ) {
			$rows[] = self::row(
				$f['invalid'] ? 'warn' : 'ok',
				'Form notifications',
				$f['page'] . ' (' . $f['widget'] . '): ' . ( $f['valid'] ? implode( ', ', $f['valid'] ) : 'site admin e-mail ' . get_option( 'admin_email' ) ) . ( $f['invalid'] ? ' | IGNORED (not valid e-mail addresses): ' . implode( ', ', $f['invalid'] ) : '' )
			);
		}
		if ( class_exists( 'DPK\\I18n' ) ) {
			$terms  = I18n::terms();
			$rows[] = self::row( I18n::gtranslate_active() ? 'ok' : 'info', 'Translation', ( I18n::gtranslate_active() ? 'GTranslate active' : 'GTranslate not active (FR | EN switcher hidden in Automatic mode)' ) . ' · never translated: ' . ( $terms ? implode( ', ', $terms ) : 'nothing (protection off)' ) . '.' );
		}
		$rows[] = self::row( Spam::turnstile_enabled() ? 'ok' : 'info', 'Cloudflare Turnstile', Spam::turnstile_enabled() ? 'On.' : 'Off (other anti-spam layers are active).' );

		/* PHP errors in the log */
		$log = $this->log_lines();
		if ( null !== $log ) {
			$rows[] = self::row( $log ? 'warn' : 'ok', 'PHP error log', $log ? count( $log ) . ' recent line(s) mention the kit or Elementor.' : 'No kit-related line in debug.log.', implode( "\n", $log ) );
		}

		/* What visitors receive */
		foreach ( $this->fetch_checks() as $r ) {
			$rows[] = $r;
		}
		return $rows;
	}

	/** Every kit form on the kit pages and the addresses it notifies. */
	private function form_recipients() {
		$importer = new Importer();
		$out      = array();
		foreach ( $importer->items() as $key => $item ) {
			if ( 'page' !== ( $item['kind'] ?? '' ) ) {
				continue;
			}
			$id = $importer->find_post( $key );
			if ( ! $id ) {
				continue;
			}
			self::walk(
				self::elementor_data( $id ),
				array(),
				function ( $el ) use ( &$out, $id ) {
					if ( ! in_array( $el['widgetType'] ?? '', array( 'dpk-contact', 'dpk-pro-form' ), true ) ) {
						return;
					}
					list( $valid, $invalid ) = Forms::parse_recipients( $el['settings']['recipient'] ?? '' );
					$out[] = array(
						'page'    => get_the_title( $id ),
						'widget'  => $el['widgetType'],
						'valid'   => $valid,
						'invalid' => $invalid,
					);
				}
			);
		}
		return $out;
	}

	/** Last kit/Elementor related lines of wp-content/debug.log (null when there is no log). */
	private function log_lines() {
		$file = WP_CONTENT_DIR . '/debug.log';
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ) {
			$file = WP_DEBUG_LOG;
		}
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$size = filesize( $file );
		$fh   = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return null;
		}
		fseek( $fh, max( 0, $size - 300000 ) );
		$tail = (string) fread( $fh, 300000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$out = array();
		foreach ( explode( "\n", $tail ) as $line ) {
			if ( preg_match( '/douce-paresse|elementor/i', $line ) && preg_match( '/error|warning|notice|deprecated/i', $line ) ) {
				$out[] = mb_substr( trim( $line ), 0, 400 );
			}
		}
		return array_slice( array_unique( $out ), -15 );
	}

	/** Requests the home page like a visitor (not logged in) and inspects the HTML. */
	private function fetch_checks() {
		$rows = array();
		$url  = home_url( '/' );
		$t    = microtime( true );
		$res  = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'sslverify'   => false,
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
				'user-agent'  => 'Mozilla/5.0 (DPK diagnostics)',
			)
		);
		$ms = round( ( microtime( true ) - $t ) * 1000 );
		if ( is_wp_error( $res ) ) {
			return array( self::row( 'warn', 'Visitor HTML', 'The server could not load its own home page (' . $res->get_error_message() . '). Use the browser check (DP Debug) instead.' ) );
		}
		$code    = wp_remote_retrieve_response_code( $res );
		$html    = (string) wp_remote_retrieve_body( $res );
		$headers = array();
		foreach ( array( 'server', 'cf-cache-status', 'x-cache', 'x-litespeed-cache', 'x-proxy-cache', 'x-rocket-nginx-serving-static', 'age', 'cache-control', 'x-sg-cache', 'x-hcdn-cache-status', 'x-kinsta-cache' ) as $h ) {
			$v = wp_remote_retrieve_header( $res, $h );
			if ( '' !== $v && null !== $v ) {
				$headers[] = $h . ': ' . ( is_array( $v ) ? implode( ', ', $v ) : $v );
			}
		}
		$rows[] = self::row( 200 === (int) $code ? 'ok' : 'error', 'Visitor HTML', 'Home page answered ' . $code . ' in ' . $ms . ' ms, ' . size_format( strlen( $html ) ) . '.', implode( "\n", $headers ) );

		// Kit assets and their version.
		$css_ver = preg_match( '#douce-paresse-kit/assets/css/frontend\.css\?ver=([0-9.]+)#', $html, $m ) ? $m[1] : '';
		$js_ver  = preg_match( '#douce-paresse-kit/assets/js/frontend\.js\?ver=([0-9.]+)#', $html, $m ) ? $m[1] : '';
		if ( ! $css_ver ) {
			$rows[] = self::row( 'warn', 'Kit CSS in the page', 'frontend.css is not linked directly (combined/minified by an optimisation plugin, or not loaded at all).' );
		} else {
			$rows[] = self::row( DPK_VERSION === $css_ver ? 'ok' : 'error', 'Kit CSS in the page', 'Version ' . $css_ver . ( DPK_VERSION === $css_ver ? '.' : ' but the plugin is ' . DPK_VERSION . ': visitors get an OLD cached page. Purge every cache (plugin, host, Cloudflare).' ) );
		}
		if ( ! $js_ver ) {
			$rows[] = self::row( 'error', 'Kit script in the page', 'frontend.js is not in the page (removed or combined by an optimisation plugin). Without it the header cannot be lifted and the menu/forms do not work.' );
		} else {
			$rows[] = self::row( DPK_VERSION === $js_ver ? 'ok' : 'error', 'Kit script in the page', 'Version ' . $js_ver . ( DPK_VERSION === $js_ver ? '.' : ' but the plugin is ' . DPK_VERSION . ': OLD cached page. Purge every cache.' ) );
			if ( preg_match( '#<script[^>]*douce-paresse-kit/assets/js/frontend\.js[^>]*>#', $html, $tag ) ) {
				$delayed = preg_match( '/type=["\'](?!text\/javascript|module)[^"\']+["\']|data-rocket-src|data-litespeed-src|data-src=|data-type=["\']lazy|data-pagespeed/i', $tag[0] );
				if ( $delayed ) {
					$rows[] = self::row( 'error', 'Kit script delayed', 'An optimisation plugin delays the kit script until the visitor moves the mouse/scrolls. Exclude “douce-paresse-kit” from “Delay JavaScript”.', $tag[0] );
				}
			}
		}

		// Header markup.
		$headers_n = preg_match_all( '/data-dpk-header/', $html );
		$fixed     = preg_match( '/dpk-header--fixed/', $html );
		$locations = preg_match_all( '/elementor-location-header/', $html );
		$hello     = preg_match( '/class="[^"]*\bsite-header\b/', $html );
		if ( ! $headers_n ) {
			$rows[] = self::row( 'error', 'Header in the page', 'The DP Header widget is NOT in the home page: another header is shown (theme header or another Theme Builder template).' . ( $hello ? ' The theme’s own header (.site-header) is present.' : '' ) );
		} else {
			$rows[] = self::row( $fixed ? 'ok' : 'error', 'Header in the page', $headers_n . ' DP Header widget(s), ' . ( $fixed ? 'fixed mode on' : 'fixed mode OFF' ) . ', ' . $locations . ' Theme Builder header location(s)' . ( $hello ? ', plus the theme’s own header (.site-header)' : '' ) . '.' );
		}
		if ( preg_match( '/<header[^>]*elementor-location-header[^>]*data-elementor-id="(\d+)"|data-elementor-id="(\d+)"[^>]*data-elementor-type="header"|data-elementor-type="header"[^>]*data-elementor-id="(\d+)"/', $html, $m ) ) {
			$shown = max( array_map( 'intval', array_slice( $m, 1 ) ) );
			$kit   = ( new Importer() )->find_post( 'header' );
			$rows[] = self::row( $shown === $kit ? 'ok' : 'error', 'Header template shown', 'Template #' . $shown . ( $shown === $kit ? ' (kit header).' : ' is shown, but the kit header is #' . $kit . '.' ) );
		}
		if ( preg_match_all( '/href="[^"]*[?&](?:page_id|p)=\d+/', $html, $mm ) ) {
			$rows[] = self::row( 'warn', 'Plain links', count( $mm[0] ) . ' link(s) still use ?page_id= (old cached HTML or links typed by hand).' );
		}
		if ( preg_match( '/<!--[^>]*(WP Rocket|LiteSpeed|W3 Total Cache|WP Fastest Cache|Cache Enabler|SG Optimizer|Hummingbird|NitroPack|Breeze|Autoptimize|cached)[^>]*-->/i', $html, $m ) ) {
			$rows[] = self::row( 'warn', 'Cached HTML', 'The home page is served from a cache: ' . trim( $m[0] ) . '. Purge it after each change.' );
		}
		return $rows;
	}

	/* ------------------------------------------------------------------
	 * Screen
	 * ------------------------------------------------------------------ */

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rows  = $this->checks();
		$icons = array( 'ok' => '✓', 'warn' => '⚠', 'error' => '✗', 'info' => 'i' );
		$text  = "DOUCE PARESSE KIT — SERVER DIAGNOSTICS\n" . gmdate( 'Y-m-d H:i' ) . " UTC\n\n";
		foreach ( $rows as $r ) {
			$text .= '[' . strtoupper( $r['state'] ) . '] ' . $r['label'] . ': ' . $r['message'] . "\n";
			if ( '' !== $r['details'] ) {
				$text .= '    ' . str_replace( "\n", "\n    ", trim( $r['details'] ) ) . "\n";
			}
		}
		$importer = new Importer();
		$pages    = array();
		foreach ( array( 'home', 'maison', 'coffret', 'pro', 'contact' ) as $key ) {
			$id = $importer->find_post( $key );
			if ( $id ) {
				$pages[ get_the_title( $id ) ] = add_query_arg( self::QUERY, '1', get_permalink( $id ) );
			}
		}
		if ( ! $pages ) {
			$pages[ __( 'Home page', 'douce-paresse-kit' ) ] = add_query_arg( self::QUERY, '1', home_url( '/' ) );
		}
		$errors = count( wp_list_filter( $rows, array( 'state' => 'error' ) ) );
		$warns  = count( wp_list_filter( $rows, array( 'state' => 'warn' ) ) );
		?>
		<div class="wrap dpk-admin dpk-diag">
			<h1><?php esc_html_e( 'Douce Paresse — Diagnostics', 'douce-paresse-kit' ); ?></h1>
			<p class="dpk-diag__sum">
				<?php
				/* translators: 1: errors, 2: warnings */
				echo esc_html( sprintf( __( '%1$d problem(s), %2$d warning(s).', 'douce-paresse-kit' ), $errors, $warns ) );
				?>
			</p>

			<div class="dpk-card">
				<h2><?php esc_html_e( '1. Browser check (most important for the header)', 'douce-paresse-kit' ); ?></h2>
				<p><?php esc_html_e( 'Opens the page with a debug panel. It checks, in your real browser, whether the header stays fixed, and if not exactly why (which element, which CSS rule), plus fonts, images, layout overflow, JavaScript errors and forms. Click “Copy report” in the panel and send it.', 'douce-paresse-kit' ); ?></p>
				<p>
					<?php foreach ( $pages as $title => $link ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $title ); ?> ↗</a>
					<?php endforeach; ?>
				</p>
				<p class="description"><?php esc_html_e( 'Also available on any page from the admin bar: “DP Debug”. Only administrators see it; visitors are not affected.', 'douce-paresse-kit' ); ?></p>
			</div>

			<div class="dpk-card">
				<h2><?php esc_html_e( '2. Server checks', 'douce-paresse-kit' ); ?></h2>
				<table class="widefat striped dpk-diag__table">
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr class="dpk-diag--<?php echo esc_attr( $r['state'] ); ?>">
							<td class="dpk-diag__icon"><?php echo esc_html( $icons[ $r['state'] ] ); ?></td>
							<th scope="row"><?php echo esc_html( $r['label'] ); ?></th>
							<td>
								<?php echo esc_html( $r['message'] ); ?>
								<?php if ( '' !== $r['details'] ) : ?>
									<details><summary><?php esc_html_e( 'Details', 'douce-paresse-kit' ); ?></summary><pre><?php echo esc_html( trim( $r['details'] ) ); ?></pre></details>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="dpk-card">
				<h2><?php esc_html_e( '3. Report', 'douce-paresse-kit' ); ?></h2>
				<textarea id="dpk-diag-report" class="large-text code" rows="12" readonly><?php echo esc_textarea( $text ); ?></textarea>
				<p><button type="button" class="button" onclick="var t=document.getElementById('dpk-diag-report');t.select();try{navigator.clipboard.writeText(t.value);}catch(e){document.execCommand('copy');}this.textContent='<?php echo esc_js( __( 'Copied ✓', 'douce-paresse-kit' ) ); ?>';"><?php esc_html_e( 'Copy report', 'douce-paresse-kit' ); ?></button></p>
			</div>
		</div>
		<?php
	}
}

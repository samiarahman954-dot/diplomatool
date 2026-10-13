<?php
/**
 * Diagnostics test: DPK\Debug server checks against in-memory WordPress stubs.
 * Run: php tests/debug-test.php
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DPK_PATH', dirname( __DIR__ ) . '/' );
	define( 'DPK_URL', 'https://site.test/wp-content/plugins/douce-paresse-kit/' );
	define( 'DPK_VERSION', '9.9.9' );
	define( 'DPK_FILE', DPK_PATH . 'douce-paresse-kit.php' );
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() );
	define( 'ELEMENTOR_VERSION', '3.25.0' );
	define( 'ELEMENTOR_PRO_VERSION', '3.25.0' );

	$GLOBALS['wp_version'] = '6.6';
	$GLOBALS['T']          = array(); // test state

	function __( $s ) { return $s; }
	function _n( $a, $b, $n ) { return 1 == $n ? $a : $b; }
	function esc_html__( $s ) { return $s; }
	function esc_html_e( $s ) { echo htmlspecialchars( $s ); }
	function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s ); }
	function esc_url( $s ) { return (string) $s; }
	function esc_js( $s ) { return addslashes( $s ); }
	function esc_textarea( $s ) { return htmlspecialchars( (string) $s ); }
	function add_action() {}
	function add_filter() {}
	function is_admin() { return true; }
	function is_wp_error( $e ) { return $e instanceof WP_Error; }
	function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
	function current_user_can() { return true; }
	function is_user_logged_in() { return true; }
	function is_multisite() { return false; }
	function home_url( $p = '/' ) { return 'https://site.test' . $p; }
	function admin_url( $p = '' ) { return 'https://site.test/wp-admin/' . $p; }
	function get_permalink( $id ) { return 'https://site.test/p' . $id . '/'; }
	function add_query_arg( $k, $v, $u = '' ) { return $u . '?' . $k . '=' . $v; }
	function remove_query_arg( $k ) { return '/'; }
	function get_the_title( $id ) { return 'Post ' . $id; }
	function get_post_status( $id ) { return $GLOBALS['T']['posts'][ $id ]['status'] ?? 'publish'; }
	function get_attached_file( $id ) { return __FILE__; }
	function human_time_diff( $t ) { return '1 min'; }
	function size_format( $n ) { return $n . ' B'; }
	function gmdate_( $f ) { return gmdate( $f ); }
	function wp_list_filter( $list, $args ) { return array_filter( $list, function ( $r ) use ( $args ) { foreach ( $args as $k => $v ) { if ( ( $r[ $k ] ?? null ) !== $v ) { return false; } } return true; } ); }
	function plugin_basename( $f ) { return 'douce-paresse-kit/douce-paresse-kit.php'; }
	function get_plugins() { return $GLOBALS['T']['plugins']; }
	function is_plugin_active( $f ) { return true; }
	function wp_get_theme() { return new class() { function get( $k ) { return 'Version' === $k ? '3.1' : 'Hello Elementor'; } function parent() { return false; } function get_template() { return 'hello-elementor'; } }; }
	function wp_is_block_theme() { return false; }
	function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['T']['options'] ) ? $GLOBALS['T']['options'][ $k ] : $d; }
	function update_option( $k, $v ) { $GLOBALS['T']['options'][ $k ] = $v; }
	function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['T']['posts'][ $id ]['meta'][ $k ] ?? ''; }
	function get_posts( $a ) {
		$out = array();
		foreach ( $GLOBALS['T']['posts'] as $id => $p ) {
			if ( isset( $a['meta_key'] ) && ( $p['meta'][ $a['meta_key'] ] ?? null ) !== $a['meta_value'] ) { continue; }
			if ( isset( $a['post_status'] ) && ! in_array( $p['status'], (array) $a['post_status'], true ) ) { continue; }
			$out[] = $id;
		}
		return $out;
	}
	function wp_remote_get( $url, $args ) { return $GLOBALS['T']['http']; }
	function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
	function wp_remote_retrieve_body( $r ) { return $r['body']; }
	function wp_remote_retrieve_header( $r, $h ) { return $r['headers'][ $h ] ?? ''; }
	class WP_Error { private $m; function __construct( $c = '', $m = '' ) { $this->m = $m; } function get_error_message() { return $this->m; } }
}

namespace DPK {
	class Admin { const SLUG = 'dpk-kit'; }
	class Spam { const PAGE = 'dpk-spam'; static function turnstile_enabled() { return false; } }
	class Importer {
		const META_KEY = '_dpk_demo_key';
		function items() { return array( 'header' => array( 'type' => 'header' ), 'footer' => array( 'type' => 'footer' ), 'home' => array( 'type' => 'page' ) ); }
		function find_post( $key ) { foreach ( $GLOBALS['T']['posts'] as $id => $p ) { if ( ( $p['meta']['_dpk_demo_key'] ?? '' ) === $key && 'trash' !== $p['status'] ) { return $id; } } return 0; }
		function image_files() { return array( 'logo.png' ); }
		function find_image( $f ) { return 99; }
	}
}

namespace {
	require DPK_PATH . 'includes/class-debug.php';

	$fails = 0;
	function check( $label, $ok ) {
		global $fails;
		echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . "\n";
		if ( ! $ok ) { $fails++; }
	}
	function rows_by_label( $rows ) { $o = array(); foreach ( $rows as $r ) { $o[ $r['label'] ][] = $r; } return $o; }

	function header_data( $sticky, $container_settings = array() ) {
		$w = array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'dpk-header', 'settings' => null === $sticky ? array() : array( 'sticky' => $sticky ) );
		return json_encode( array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => $container_settings, 'elements' => array( $w ) ) ) );
	}

	function page_html( $ver, $script_attrs = '', $tpl = 10 ) {
		return '<html><head><link rel="stylesheet" href="https://site.test/wp-content/plugins/douce-paresse-kit/assets/css/frontend.css?ver=' . $ver . '"></head><body>'
			. '<header data-elementor-type="header" data-elementor-id="' . $tpl . '" class="elementor elementor-' . $tpl . ' elementor-location-header"><div class="dpk dpk-header dpk-header--fixed" data-dpk-header></div></header>'
			. '<script ' . $script_attrs . ' src="https://site.test/wp-content/plugins/douce-paresse-kit/assets/js/frontend.js?ver=' . $ver . '" id="dpk-frontend-js"></script></body></html>';
	}

	function reset_state() {
		$GLOBALS['T'] = array(
			'plugins' => array( 'douce-paresse-kit/douce-paresse-kit.php' => array( 'Name' => 'Douce Paresse Kit', 'Version' => DPK_VERSION ) ),
			'options' => array( 'permalink_structure' => '/%postname%/', 'active_plugins' => array( 'elementor/elementor.php' ), 'show_on_front' => 'page', 'page_on_front' => 20 ),
			'posts'   => array(
				10 => array( 'status' => 'publish', 'meta' => array( '_dpk_demo_key' => 'header', '_elementor_template_type' => 'header', '_elementor_conditions' => array( 'include/general' ), '_elementor_data' => header_data( null ) ) ),
				11 => array( 'status' => 'publish', 'meta' => array( '_dpk_demo_key' => 'footer', '_elementor_template_type' => 'footer', '_elementor_conditions' => array( 'include/general' ) ) ),
				20 => array( 'status' => 'publish', 'meta' => array( '_dpk_demo_key' => 'home' ) ),
			),
			'http'    => array( 'code' => 200, 'headers' => array( 'server' => 'nginx' ), 'body' => page_html( DPK_VERSION ) ),
		);
	}

	$d = DPK\Debug::instance();

	echo "Healthy site\n";
	reset_state();
	$rows = $d->checks();
	$errors = array_filter( $rows, function ( $r ) { return 'error' === $r['state']; } );
	check( 'no error rows (' . implode( ', ', array_column( $errors, 'label' ) ) . ')', ! $errors );
	$by = rows_by_label( $rows );
	check( 'sticky reported on (default)', 'ok' === $by['Header “Fixed on scroll”'][0]['state'] );
	check( 'header template shown = kit', 'ok' === $by['Header template shown'][0]['state'] );
	check( 'plugin files current', 'ok' === $by['Plugin files'][0]['state'] );

	echo "Sticky switched off + animation on the container\n";
	reset_state();
	$GLOBALS['T']['posts'][10]['meta']['_elementor_data'] = header_data( '', array( 'animation' => 'fadeInDown', 'z_index' => 5 ) );
	$by = rows_by_label( $d->checks() );
	check( 'sticky off is an error', 'error' === $by['Header “Fixed on scroll”'][0]['state'] );
	check( 'wrapper settings listed', isset( $by['Header wrapper settings'] ) && false !== strpos( $by['Header wrapper settings'][0]['details'], 'fadeInDown' ) );

	echo "Second header template with Entire Site\n";
	reset_state();
	$GLOBALS['T']['posts'][30] = array( 'status' => 'publish', 'meta' => array( '_elementor_template_type' => 'header', '_elementor_conditions' => array( 'include/general' ) ) );
	$GLOBALS['T']['http']['body'] = page_html( DPK_VERSION, '', 30 );
	$by = rows_by_label( $d->checks() );
	check( 'competing header templates', 'error' === $by['Header template'][0]['state'] );
	check( 'other template shown on the site', 'error' === $by['Header template shown'][0]['state'] && false !== strpos( $by['Header template shown'][0]['message'], '#30' ) );

	echo "Old cached page, delayed script, WP Rocket\n";
	reset_state();
	$GLOBALS['T']['options']['active_plugins'][] = 'wp-rocket/wp-rocket.php';
	$GLOBALS['T']['options']['wp_rocket_settings'] = array( 'delay_js' => 1 );
	$GLOBALS['T']['http']['body'] = page_html( '1.1.2', 'type="rocketlazyloadscript" data-rocket-src="x"' ) . '<!-- This website is like a Rocket, Cached by WP Rocket -->';
	$GLOBALS['T']['http']['body'] = str_replace( 'src="https://site.test/wp-content/plugins/douce-paresse-kit/assets/js', 'data-x="https://site.test/wp-content/plugins/douce-paresse-kit/assets/js', $GLOBALS['T']['http']['body'] );
	$GLOBALS['T']['http']['body'] = str_replace( 'data-rocket-src="x" data-x=', 'data-rocket-src=', $GLOBALS['T']['http']['body'] );
	$by = rows_by_label( $d->checks() );
	check( 'optimiser detected', 'warn' === $by['Cache / optimisation plugins'][0]['state'] );
	check( 'delay JS setting reported', isset( $by['JavaScript / CSS optimisation'] ) && false !== strpos( $by['JavaScript / CSS optimisation'][0]['details'], 'Delay JavaScript' ) );
	check( 'old CSS version is an error', 'error' === $by['Kit CSS in the page'][0]['state'] );
	check( 'cached HTML reported', isset( $by['Cached HTML'] ) );

	echo "Delayed script tag with normal src\n";
	reset_state();
	$GLOBALS['T']['http']['body'] = page_html( DPK_VERSION, 'type="text/litespeed" data-no-optimize="0"' );
	$by = rows_by_label( $d->checks() );
	check( 'delayed script detected', isset( $by['Kit script delayed'] ) );

	echo "Kit header missing from the page / loopback failure\n";
	reset_state();
	$GLOBALS['T']['http']['body'] = '<html><body><header class="site-header">Theme</header></body></html>';
	$by = rows_by_label( $d->checks() );
	check( 'missing DP Header is an error', 'error' === $by['Header in the page'][0]['state'] && false !== strpos( $by['Header in the page'][0]['message'], 'site-header' ) );
	check( 'missing script is an error', 'error' === $by['Kit script in the page'][0]['state'] );
	reset_state();
	$GLOBALS['T']['http'] = new WP_Error( 'x', 'cURL error 28' );
	$by = rows_by_label( $d->checks() );
	check( 'loopback failure is a warning, not a crash', 'warn' === $by['Visitor HTML'][0]['state'] );

	echo "Screen renders\n";
	reset_state();
	ob_start();
	$d->render();
	$html = ob_get_clean();
	check( 'report textarea present', false !== strpos( $html, 'dpk-diag-report' ) && false !== strpos( $html, 'SERVER DIAGNOSTICS' ) );
	check( 'browser check links', false !== strpos( $html, 'dpk-debug=1' ) );
	check( 'summary lines', 3 < count( $d->summary_lines() ) );

	echo $fails ? "\n$fails FAILURE(S)\n" : "\nALL OK\n";
	exit( $fails ? 1 : 0 );
}

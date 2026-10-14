<?php
/**
 * Form notifications: several recipients per form, invalid entries ignored, admin fallback,
 * and the real DPK\Forms::handle() path up to wp_mail().
 * Run: php tests/forms-test.php
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DPK_PATH', dirname( __DIR__ ) . '/' );

	$GLOBALS['T'] = array( 'mail' => null, 'meta' => array(), 'json' => null );

	class DPK_Json_Exit extends Exception {}

	function __( $s ) { return $s; }
	function add_action() {}
	function add_filter() {}
	function get_option( $k, $d = false ) { return 'admin_email' === $k ? 'admin@site.fr' : $d; }
	function sanitize_email( $e ) { return preg_replace( '/[^a-z0-9@._+\'-]/i', '', $e ); }
	function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
	function absint( $n ) { return abs( (int) $n ); }
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $k ) ); }
	function wp_unslash( $v ) { return $v; }
	function sanitize_text_field( $v ) { return trim( strip_tags( $v ) ); }
	function sanitize_textarea_field( $v ) { return trim( strip_tags( $v ) ); }
	function did_action() { return 1; }
	function get_permalink( $id ) { return 'https://site.fr/contact/'; }
	function is_wp_error() { return false; }
	function wp_insert_post( $a ) { return 77; }
	function update_post_meta( $id, $k, $v ) { $GLOBALS['T']['meta'][ $k ] = $v; }
	function wp_mail( $to, $subject, $body, $headers ) { $GLOBALS['T']['mail'] = compact( 'to', 'subject', 'body', 'headers' ); return true; }
	function wp_send_json_success( $d = null ) { $GLOBALS['T']['json'] = array( true, $d ); throw new DPK_Json_Exit(); }
	function wp_send_json_error( $d = null ) { $GLOBALS['T']['json'] = array( false, $d ); throw new DPK_Json_Exit(); }
}

namespace Elementor {
	class Plugin { public static $instance; public $documents; public $elements_manager; }
}

namespace DPK {
	class Spam {
		public static function check() { return array( 'status' => 'ok' ); }
		public static function burn_token() {}
		public static function get() { return array( 'keep_spam' => 'yes' ); }
		public static function client_ip() { return '1.2.3.4'; }
	}
}

namespace {
	require DPK_PATH . 'includes/class-forms.php';

	$fails = 0;
	function check( $label, $ok ) {
		global $fails;
		echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . "\n";
		if ( ! $ok ) { $fails++; }
	}

	echo "Parsing\n";
	list( $v, $bad ) = DPK\Forms::parse_recipients( "owner@site.fr\nstaff@site.fr, sales@site.fr; Owner@Site.fr\n\n  oops  \n<boss@site.fr>" );
	check( 'lines, commas, semicolons, <>', array( 'owner@site.fr', 'staff@site.fr', 'sales@site.fr', 'boss@site.fr' ) === $v );
	check( 'duplicates removed (case-insensitive)', 4 === count( $v ) );
	check( 'invalid entry reported', array( 'oops' ) === $bad );
	list( $v, $bad ) = DPK\Forms::parse_recipients( 'équipe@douceparesse.com, contact@café.fr, ok@site.fr' );
	check( 'addresses sanitize_email would rewrite are invalid, not changed', array( 'ok@site.fr' ) === $v && array( 'équipe@douceparesse.com', 'contact@café.fr' ) === $bad );
	check( 'empty -> admin e-mail', array( 'admin@site.fr' ) === DPK\Forms::recipients( '' ) );
	check( 'only invalid -> admin e-mail', array( 'admin@site.fr' ) === DPK\Forms::recipients( 'nope, still-nope' ) );
	check( 'single address still works', array( 'me@site.fr' ) === DPK\Forms::recipients( 'me@site.fr' ) );
	check( 'capped at ' . DPK\Forms::MAX_RECIPIENTS, DPK\Forms::MAX_RECIPIENTS === count( DPK\Forms::recipients( implode( ',', array_map( function ( $i ) { return "u$i@site.fr"; }, range( 1, 30 ) ) ) ) ) );

	echo "Submission\n";
	function submit( $recipient ) {
		$settings = array(
			'recipient'     => $recipient,
			'email_subject' => 'Contact',
			'fields'        => array(
				array( '_id' => 'a', 'name' => 'nom', 'label' => 'Nom', 'type' => 'text', 'required' => 'yes' ),
				array( '_id' => 'b', 'name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => 'yes' ),
			),
		);
		$doc = new class() { function get_elements_data() { return array( array( 'id' => 'c1', 'elements' => array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'dpk-contact', 'settings' => array() ) ) ) ); } };
		\Elementor\Plugin::$instance                   = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->documents        = new class( $doc ) { private $d; function __construct( $d ) { $this->d = $d; } function get( $id ) { return $this->d; } };
		\Elementor\Plugin::$instance->elements_manager = new class( $settings ) { private $s; function __construct( $s ) { $this->s = $s; } function create_element_instance( $el ) { $s = $this->s; return new class( $s ) { private $s; function __construct( $s ) { $this->s = $s; } function get_settings_for_display() { return $this->s; } }; } };
		$_POST = array( 'post_id' => '5', 'widget_id' => 'w1', 'nom' => 'Alice', 'email' => 'alice@client.fr', 'dpk_token' => 't' );
		$GLOBALS['T']['mail'] = null;
		$GLOBALS['T']['meta'] = array();
		$r = new ReflectionClass( 'DPK\Forms' );
		$forms = $r->newInstanceWithoutConstructor();
		try { $forms->handle(); } catch ( DPK_Json_Exit $e ) {} // phpcs:ignore
		return $GLOBALS['T'];
	}
	$t = submit( "owner@site.fr\nstaff@site.fr, bad entry" );
	check( 'success answered', true === $t['json'][0] );
	check( 'wp_mail gets every valid address', array( 'owner@site.fr', 'staff@site.fr' ) === $t['mail']['to'] );
	check( 'reply-to is the visitor', in_array( 'Reply-To: alice@client.fr', $t['mail']['headers'], true ) );
	check( 'recipients stored on the message', 'owner@site.fr, staff@site.fr' === $t['meta']['_dpk_mailed_to'] );
	$t = submit( '' );
	check( 'no recipient -> admin e-mail', array( 'admin@site.fr' ) === $t['mail']['to'] );

	echo $fails ? "\n$fails FAILURE(S)\n" : "\nALL OK\n";
	exit( $fails ? 1 : 0 );
}

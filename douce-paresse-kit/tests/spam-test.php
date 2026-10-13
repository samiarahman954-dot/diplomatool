<?php
/**
 * Anti-spam tests with tiny WordPress stubs:
 *   php tests/spam-test.php
 */
namespace {
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
$OPT = array(); $TR = array(); $REMOTE = array( 'success' => true );
function get_option( $k, $d = false ) { global $OPT; return $OPT[ $k ] ?? $d; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_salt() { return 'test-salt'; }
function get_transient( $k ) { global $TR; return $TR[ $k ] ?? false; }
function set_transient( $k, $v ) { global $TR; $TR[ $k ] = $v; }
function __( $s ) { return $s; }
function wp_list_pluck( $l, $f ) { return array_column( $l, $f ); }
function wp_remote_post( $url, $args ) { global $REMOTE, $LAST; $LAST = $args; return $REMOTE; }
function is_wp_error() { return false; }
function wp_remote_retrieve_body( $r ) { return json_encode( $r ); }
function wp_check_comment_disallowed_list( $a, $e, $u, $c ) { return false !== stripos( $c, 'forbidden-by-wp' ); }
function add_action() {}
}
namespace DPK {
class Admin { const SLUG = 'dpk-kit'; }
class Forms { const CPT = 'dpk_message'; }
}
namespace {
require __DIR__ . '/../includes/class-spam.php';
use DPK\Spam;

$fail = 0;
function check( $label, $cond ) { global $fail; echo ( $cond ? '  ok   ' : '  FAIL ' ) . $label . "\n"; if ( ! $cond ) { $fail++; } }
function opts( $o = array() ) { global $OPT, $TR; $OPT['dpk_spam'] = array_merge( array( 'check_mx' => '', 'akismet' => '' ), $o ); $TR = array(); }
function vals( $msg, $email = 'jean@hotel.fr' ) {
	return array(
		array( 'key' => 'nom', 'label' => 'Nom', 'type' => 'text', 'value' => 'Jean Dupont' ),
		array( 'key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'value' => $email ),
		array( 'key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'value' => $msg ),
	);
}
function req( $extra = array() ) { return array_merge( array( 'dpk_hp' => '', 'dpk_token' => Spam::issue_token( time() - 10 ), 'cf-turnstile-response' => '' ), $extra ); }
$good = 'Bonjour, nous sommes un hôtel de 40 chambres à Lyon et souhaitons recevoir vos tarifs.';

echo "IP detection (direct, Cloudflare proxy, Cloudflare Tunnel, spoofing)\n";
opts();
check( 'direct visitor', '203.0.113.9' === Spam::client_ip( array( 'REMOTE_ADDR' => '203.0.113.9' ) ) );
check( 'via Cloudflare edge', '198.51.100.7' === Spam::client_ip( array( 'REMOTE_ADDR' => '172.70.1.2', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ) ) );
check( 'via Cloudflare edge (IPv6)', '198.51.100.7' === Spam::client_ip( array( 'REMOTE_ADDR' => '2a06:98c1::1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ) ) );
check( 'via Cloudflare Tunnel (cloudflared on localhost)', '198.51.100.7' === Spam::client_ip( array( 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ) ) );
check( 'via Tunnel in Docker (private IP)', '198.51.100.7' === Spam::client_ip( array( 'REMOTE_ADDR' => '172.18.0.5', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ) ) );
check( 'spoofed header from the internet ignored', '203.0.113.9' === Spam::client_ip( array( 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4' ) ) );
check( 'garbage header ignored', '172.70.1.2' === Spam::client_ip( array( 'REMOTE_ADDR' => '172.70.1.2', 'HTTP_CF_CONNECTING_IP' => 'x' ) ) );
opts( array( 'cloudflare_ip' => 'off' ) );
check( 'mode off', '172.70.1.2' === Spam::client_ip( array( 'REMOTE_ADDR' => '172.70.1.2', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ) ) );

echo "Signed token / time trap\n";
opts();
check( 'valid after delay', true === Spam::check_token( Spam::issue_token( time() - 10 ) ) );
check( 'too fast', 'fast' === Spam::check_token( Spam::issue_token( time() - 1 ) ) );
check( 'expired', 'expired' === Spam::check_token( Spam::issue_token( time() - 13 * 3600 ) ) );
check( 'missing', 'missing' === Spam::check_token( '' ) );
$t = Spam::issue_token( time() - 10 );
check( 'forged signature', 'invalid' === Spam::check_token( substr( $t, 0, -1 ) . ( '0' === substr( $t, -1 ) ? '1' : '0' ) ) );
check( 'forged time', 'invalid' === Spam::check_token( ( time() - 100 ) . substr( $t, strpos( $t, '.' ) ) ) );
Spam::burn_token( $t );
check( 'single use', 'used' === Spam::check_token( $t ) );

echo "Full check\n";
opts();
check( 'clean message passes', 'ok' === Spam::check( vals( $good ), 'jean@hotel.fr', req() )['status'] );
opts();
check( 'honeypot = silent spam', 'spam' === Spam::check( vals( $good ), 'jean@hotel.fr', req( array( 'dpk_hp' => 'http://x' ) ) )['status'] );
opts();
$r = Spam::check( vals( $good ), 'jean@hotel.fr', req( array( 'dpk_token' => Spam::issue_token( time() ) ) ) );
check( 'too fast = visible error with retry', 'error' === $r['status'] && ! empty( $r['retry'] ) );
opts();
check( 'no token (direct POST bot) = error', 'error' === Spam::check( vals( $good ), 'jean@hotel.fr', req( array( 'dpk_token' => '' ) ) )['status'] );
opts();
check( '3 links = spam', 'spam' === Spam::check( vals( 'see https://a.com https://b.com www.c.net' ), 'jean@hotel.fr', req() )['status'] );
opts();
check( 'e-mail addresses are not links', 'ok' === Spam::check( vals( 'écrivez à jean@hotel.fr ou marie@hotel.fr, site hotel.fr' ), 'jean@hotel.fr', req() )['status'] );
opts();
check( 'BBCode link = spam', 'spam' === Spam::check( vals( 'nice [url=http://x]x[/url]' ), 'jean@hotel.fr', req() )['status'] );
opts();
check( 'blocked word = spam', 'spam' === Spam::check( vals( 'Best CASINO bonus' ), 'jean@hotel.fr', req() )['status'] );
opts();
check( 'whole words only ("sexe" ≠ "sex")', 'ok' === Spam::check( vals( 'Le sexe du chat, essexe' ), 'jean@hotel.fr', req() )['status'] );
opts();
check( 'WordPress disallowed list', 'spam' === Spam::check( vals( 'forbidden-by-wp' ), 'jean@hotel.fr', req() )['status'] );
opts();
check( 'Cyrillic spam', 'spam' === Spam::check( vals( 'Здравствуйте, предлагаем продвижение сайта недорого и быстро' ), 'jean@hotel.fr', req() )['status'] );
opts();
check( 'French accents are fine', 'ok' === Spam::check( vals( 'Très élégant, ça nous intéresse beaucoup ! Où êtes-vous situés ?' ), 'jean@hotel.fr', req() )['status'] );
opts();
$r = Spam::check( vals( $good, 'x@yopmail.com' ), 'x@yopmail.com', req() );
check( 'disposable e-mail = field error', 'error' === $r['status'] && 'email' === $r['field'] );
opts();
Spam::check( vals( $good ), 'jean@hotel.fr', req() );
check( 'duplicate detected', 'duplicate' === Spam::check( vals( $good ), 'jean@hotel.fr', req() )['status'] );
opts( array( 'rate_ip' => 2, 'duplicates' => '' ) );
$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
Spam::check( vals( $good . ' 1' ), 'a@hotel.fr', req() ); Spam::check( vals( $good . ' 2' ), 'b@hotel.fr', req() );
check( 'IP rate limit', 'rate-ip' === Spam::check( vals( $good . ' 3' ), 'c@hotel.fr', req() )['reason'] );
opts( array( 'rate_email' => 1, 'duplicates' => '' ) );
Spam::check( vals( $good . ' 1' ), 'same@hotel.fr', req() );
check( 'e-mail rate limit', 'rate-email' === Spam::check( vals( $good . ' 2' ), 'same@hotel.fr', req() )['reason'] );

echo "Cloudflare Turnstile\n";
opts( array( 'turnstile' => 'yes', 'ts_site' => 'site', 'ts_secret' => 'secret' ) );
check( 'missing response = error', 'turnstile' === Spam::check( vals( $good ), 'jean@hotel.fr', req() )['reason'] );
opts( array( 'turnstile' => 'yes', 'ts_site' => 'site', 'ts_secret' => 'secret' ) );
$REMOTE = array( 'success' => true );
check( 'valid response passes', 'ok' === Spam::check( vals( $good ), 'jean@hotel.fr', req( array( 'cf-turnstile-response' => 'tok' ) ) )['status'] );
check( 'secret + visitor IP sent to Cloudflare', 'secret' === $LAST['body']['secret'] && 'tok' === $LAST['body']['response'] && '203.0.113.50' === $LAST['body']['remoteip'] );
opts( array( 'turnstile' => 'yes', 'ts_site' => 'site', 'ts_secret' => 'secret' ) );
$REMOTE = array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) );
check( 'rejected response = error', 'turnstile' === Spam::check( vals( $good ), 'jean@hotel.fr', req( array( 'cf-turnstile-response' => 'bad' ) ) )['reason'] );
opts( array( 'turnstile' => 'yes', 'ts_site' => '', 'ts_secret' => '' ) );
check( 'enabled without keys = not enforced', ! Spam::turnstile_enabled() );

echo $fail ? "\n$fail FAILED\n" : "\nAll anti-spam tests passed\n";
exit( $fail ? 1 : 0 );
}

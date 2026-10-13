<?php
/**
 * Permalink-independent internal links:
 *   php tests/links-test.php
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['HOME'] = 'https://example.com/';
function home_url( $p = '/' ) { return rtrim( $GLOBALS['HOME'], '/' ) . $p; }
function wp_parse_url( $u ) { return parse_url( $u ); }
function get_permalink( $id ) { $map = array( 12 => 'la-maison', 15 => 'professionnels' ); return isset( $map[ $id ] ) ? home_url( '/' . $map[ $id ] . '/' ) : false; }
function esc_url( $u ) { return htmlspecialchars( $u, ENT_QUOTES ); }
require __DIR__ . '/../includes/class-links.php';
use DPK\Links;
$fail = 0;
function check( $l, $c ) { global $fail; echo ( $c ? '  ok   ' : '  FAIL ' ) . $l . "\n"; if ( ! $c ) { $fail++; } }
check( 'plain page link → pretty', 'https://example.com/la-maison/' === Links::url( 'https://example.com/?page_id=12' ) );
check( 'anchor kept', 'https://example.com/professionnels/#demande' === Links::url( 'https://example.com/?page_id=15#demande' ) );
check( '?p= form', 'https://example.com/la-maison/' === Links::url( 'https://example.com/?p=12' ) );
check( 'index.php form', 'https://example.com/la-maison/' === Links::url( 'https://example.com/index.php?page_id=12' ) );
check( 'pretty link untouched', 'https://example.com/contact/' === Links::url( 'https://example.com/contact/' ) );
check( 'other site untouched', 'https://other.com/?page_id=12' === Links::url( 'https://other.com/?page_id=12' ) );
check( 'mailto untouched', 'mailto:a@b.fr' === Links::url( 'mailto:a@b.fr' ) );
check( 'anchor only untouched', '#demande' === Links::url( '#demande' ) );
check( 'unknown page untouched', 'https://example.com/?page_id=99' === Links::url( 'https://example.com/?page_id=99' ) );
$GLOBALS['HOME'] = 'https://example.com/site/';
check( 'WordPress in a subfolder', 'https://example.com/site/la-maison/' === Links::url( 'https://example.com/site/?page_id=12' ) );
$GLOBALS['HOME'] = 'https://example.com/';
check( 'HTML hrefs rewritten', 'En savoir plus : <a href="https://example.com/la-maison/">Politique</a>.' === Links::html( 'En savoir plus : <a href="https://example.com/?page_id=12">Politique</a>.' ) );
echo $fail ? "\n$fail FAILED\n" : "\nAll link tests passed\n";
exit( $fail ? 1 : 0 );

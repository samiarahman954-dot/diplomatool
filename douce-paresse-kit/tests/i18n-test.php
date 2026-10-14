<?php
/**
 * Brand protection for website translation (DPK\I18n::protect).
 * Run: php tests/i18n-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_action() {}
function add_filter() {}
require dirname( __DIR__ ) . '/includes/class-i18n.php';

$fails = 0;
function check( $label, $ok, $got = '' ) {
	global $fails;
	echo ( $ok ? '  ok   ' : '  FAIL ' ) . $label . ( $ok ? '' : "\n         got: " . $got ) . "\n";
	if ( ! $ok ) { $fails++; }
}
$t    = array( 'Douce Paresse' );
$span = '<span class="notranslate" translate="no" data-dpk-brand>';
$p    = function ( $html ) use ( $t ) { return DPK\I18n::protect( $html, $t ); };

$doc = '<!DOCTYPE html><html><head><title>Douce Paresse — Accueil</title><meta property="og:title" content="Douce Paresse"><script>var a = "Douce Paresse";</script></head>'
	. '<body class="x"><h1 class="dpk-home-hero__title">DOUCE PARESSE</h1>'
	. '<p>Bienvenue chez Douce Paresse, l’art de la courtoisie.</p>'
	. '<p>Douce&nbsp;Paresse et Douce   Paresse</p>'
	. '<a href="/" aria-label="Douce Paresse — Accueil" data-x=\'{"t":"Douce Paresse"}\'>Accueil</a>'
	. '<img src="logo.png" alt="Douce Paresse"><img src="x.png" alt="Chambre">'
	. '<input type="text" placeholder="Votre message à Douce Paresse">'
	. '<script type="application/ld+json">{"name":"Douce Paresse"}</script>'
	. '<style>.x:after{content:"Douce Paresse"}</style>'
	. '<textarea>Douce Paresse</textarea><select><option>Douce Paresse</option></select>'
	. '<!-- Douce Paresse -->'
	. '<p>Les Douces Paresses, paresse douce, DouceParesse</p>'
	. '<span class="dpk-footer__tagline">© 2026 Douce Paresse</span>'
	. '</body></html>';
$out = $p( $doc );

echo "Text\n";
check( 'uppercase heading wrapped', false !== strpos( $out, '<h1 class="dpk-home-hero__title">' . $span . 'DOUCE PARESSE</span></h1>' ), $out );
check( 'inside a sentence', false !== strpos( $out, 'chez ' . $span . 'Douce Paresse</span>, l’art' ) );
check( '&nbsp; and multiple spaces', false !== strpos( $out, $span . 'Douce&nbsp;Paresse</span> et ' . $span . 'Douce   Paresse</span>' ) );
check( 'footer copyright', false !== strpos( $out, '© 2026 ' . $span . 'Douce Paresse</span>' ) );
check( 'other words not touched (Douces Paresses, DouceParesse)', false !== strpos( $out, '<p>Les Douces Paresses, paresse douce, DouceParesse</p>' ) );

echo "Left alone\n";
check( '<head> (title, meta, script) unchanged', 0 === strpos( $out, substr( $doc, 0, strpos( $doc, '<body' ) ) ) );
check( 'attributes unchanged', false !== strpos( $out, '<a href="/" aria-label="Douce Paresse — Accueil" data-x=\'{"t":"Douce Paresse"}\'>Accueil</a>' ) );
check( 'scripts / JSON-LD / styles unchanged', false !== strpos( $out, '<script type="application/ld+json">{"name":"Douce Paresse"}</script><style>.x:after{content:"Douce Paresse"}</style>' ) );
check( 'textarea / option / comment unchanged', false !== strpos( $out, '<textarea>Douce Paresse</textarea><select><option>Douce Paresse</option></select><!-- Douce Paresse -->' ) );

echo "Attributes that Google translates\n";
check( 'img alt with the brand gets translate="no"', false !== strpos( $out, '<img src="logo.png" alt="Douce Paresse" translate="no">' ) );
check( 'other img untouched', false !== strpos( $out, '<img src="x.png" alt="Chambre">' ) );
check( 'input placeholder with the brand gets translate="no"', false !== strpos( $out, '<input type="text" placeholder="Votre message à Douce Paresse" translate="no">' ) );

echo "Safety\n";
check( 'second pass changes nothing', $p( $out ) === $out );
check( 'no term → identical', $doc === DPK\I18n::protect( $doc, array() ) );
$plain = '<html><body><p>Rien à protéger</p></body></html>';
check( 'page without the term → identical', $plain === $p( $plain ) );
check( 'fragment (no <body>)', $span . 'Douce Paresse</span> Kit' === $p( 'Douce Paresse Kit' ) );
check( 'several terms', '<body>' . $span . 'Le Coffret</span> de ' . $span . 'Douce Paresse</span></body>' === DPK\I18n::protect( '<body>Le Coffret de Douce Paresse</body>', array( 'Douce Paresse', 'Le Coffret' ) ) );
$big = '<html><body>' . str_repeat( '<div class="a" data-s=\'{"x":1}\'><p>Texte Douce Paresse</p></div>', 20000 ) . '</body></html>';
$t0  = microtime( true );
$res = $p( $big );
check( 'large page (' . round( strlen( $big ) / 1048576, 1 ) . ' MB) processed in ' . round( ( microtime( true ) - $t0 ) * 1000 ) . ' ms', 20000 === substr_count( $res, 'data-dpk-brand' ) );

echo $fails ? "\n$fails FAILURE(S)\n" : "\nALL OK\n";
exit( $fails ? 1 : 0 );

<?php
/**
 * Brand protection for website translation (DPK\I18n::protect).
 * Run: php tests/i18n-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_action() {}
function add_filter() {}
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
function wp_kses( $s, $allowed ) { return strip_tags( $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
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
check( 'fragment when asked', $span . 'Douce Paresse</span> Kit' === DPK\I18n::protect( 'Douce Paresse Kit', $t, true ) );
check( 'inline SVG text unchanged', '<body><svg><text>Douce Paresse</text></svg> ' . $span . 'Douce Paresse</span></body>' === $p( '<body><svg><text>Douce Paresse</text></svg> Douce Paresse</body>' ) );
check( 'a "<" inside a script does not stop protection', '<body><script>for(var i=0;i<n;i++){x()}</script><h1>' . $span . 'Douce Paresse</span></h1><style>a>b{}</style><p>' . $span . 'Douce Paresse</span></p></body>' === $p( '<body><script>for(var i=0;i<n;i++){x()}</script><h1>Douce Paresse</h1><style>a>b{}</style><p>Douce Paresse</p></body>' ) );
check( 'HTML entities never split (&amp; &copy;)', '<body>Tom &amp; Jerry &copy; ' . $span . 'amp</span></body>' === DPK\I18n::protect( '<body>Tom &amp; Jerry &copy; amp</body>', array( 'copy', 'amp' ) ) );
echo "Not an HTML page: unchanged\n";
foreach ( array(
	'JSON'           => '{"name":"Douce Paresse","url":"/"}',
	'XML sitemap'    => '<?xml version="1.0"?><urlset><url><image:title>Douce Paresse</image:title></url></urlset>',
	'XML with body'  => '<?xml version="1.0"?><feed><body>Douce Paresse</body></feed>',
	'robots.txt'     => "User-agent: *\n# Douce Paresse",
	'plain fragment' => 'Douce Paresse Kit',
	'tbody only'     => '<table><tbody><tr><td>Douce Paresse</td></tr></tbody></table>',
) as $label => $in ) {
	check( $label, $in === $p( $in ), $p( $in ) );
}
check( 'several terms', '<body>' . $span . 'Le Coffret</span> de ' . $span . 'Douce Paresse</span></body>' === DPK\I18n::protect( '<body>Le Coffret de Douce Paresse</body>', array( 'Douce Paresse', 'Le Coffret' ) ) );
$big = '<html><body>' . str_repeat( '<div class="a" data-s=\'{"x":1}\'><p>Texte Douce Paresse</p><script>if(a<b){c()}</script></div>', 20000 ) . '</body></html>';
$t0  = microtime( true );
$res = $p( $big );
check( 'large page (' . round( strlen( $big ) / 1048576, 1 ) . ' MB) processed in ' . round( ( microtime( true ) - $t0 ) * 1000 ) . ' ms', 20000 === substr_count( $res, 'data-dpk-brand' ) );

echo "English corrections\n";
$rows = DPK\I18n::parse_bulk( "FR: Et si un détail faisait toute la différence ?\nEN: What if one detail\nmade all the difference?\n\nfr : La Maison\nEn: The House\nstray line\nEN:\nFR: Sans anglais" );
check( 'bulk: blocks, multi-line, any case', array(
	array( 'fr' => 'Et si un détail faisait toute la différence ?', 'en' => 'What if one detail made all the difference?' ),
	array( 'fr' => 'La Maison', 'en' => '' ),
	array( 'fr' => 'Sans anglais', 'en' => '' ),
) === $rows, json_encode( $rows ) );
$i18n = ( new ReflectionClass( 'DPK\I18n' ) )->newInstanceWithoutConstructor();
$saved = $i18n->sanitize( array(
	'fixes'      => array(
		array( 'fr' => '  La   <b>Maison</b> ', 'en' => 'The <strong>House</strong><script>x()</script>' ),
		array( 'fr' => 'Supprimer', 'en' => 'Delete me', 'delete' => '1' ),
		array( 'fr' => '', 'en' => 'no French' ),
	),
	'fixes_bulk' => "FR: la maison\nEN: Our House\nFR: Accueil\nEN: Home",
) );
check( 'saved: cleaned, deleted / empty rows dropped, bulk updates an existing French text', array(
	array( 'fr' => 'la maison', 'en' => 'Our House' ),
	array( 'fr' => 'Accueil', 'en' => 'Home' ),
) === $saved['fixes'], json_encode( $saved['fixes'] ) );
$saved = $i18n->sanitize( array( 'fixes' => array( array( 'fr' => 'La Maison', 'en' => 'The <strong>House</strong><script>x()</script><img src=x>' ) ) ) );
check( 'English keeps <strong>, drops script / img', 'The <strong>House</strong>x()' === $saved['fixes'][0]['en'], $saved['fixes'][0]['en'] );

echo $fails ? "\n$fails FAILURE(S)\n" : "\nALL OK\n";
exit( $fails ? 1 : 0 );

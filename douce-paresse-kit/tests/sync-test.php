<?php
/**
 * Stand-alone tests for the three-way merge (no WordPress needed):
 *   php tests/sync-test.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-sync.php';

use DPK\Sync;

$failures = 0;
function check( $label, $cond ) {
	global $failures;
	echo ( $cond ? "  ok   " : "  FAIL " ) . $label . "\n";
	if ( ! $cond ) {
		++$failures;
	}
}

function widget( $id, $type, $settings ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
}
function box( $id, $child ) {
	return array( 'id' => $id, 'elType' => 'container', 'settings' => array( 'content_width' => 'full' ), 'elements' => array( $child ) );
}
function find_el( $list, $id ) {
	foreach ( $list as $el ) {
		if ( $el['id'] === $id ) {
			return $el;
		}
		$f = find_el( $el['elements'], $id );
		if ( $f ) {
			return $f;
		}
	}
	return null;
}
function ids( $list ) {
	return array_map( function ( $e ) { return $e['id']; }, $list );
}

// Control defaults as Elementor would report them.
$defaults = function ( $el ) {
	if ( isset( $el['widgetType'] ) && 'dpk-home-hero' === $el['widgetType'] ) {
		return array( 'layout' => 'centered', 'title' => 'DOUCE PARESSE', 'subtitle' => 'l’art de la courtoisie française' );
	}
	return array();
};

$base = array(
	box( 'c1', widget( 'w1', 'dpk-home-hero', array( 'layout' => 'centered', 'title' => 'DOUCE PARESSE', 'subtitle' => 'Old subtitle', 'image' => array( 'id' => 5, 'url' => 'a.webp' ) ) ) ),
	box( 'c2', widget( 'w2', 'dpk-chapter', array( 'title' => 'Le Premier Geste', 'label' => 'Chapitre I' ) ) ),
	box( 'c3', widget( 'w3', 'dpk-reasons', array( 'items' => array( array( '_id' => 'r1', 'title' => 'A' ), array( '_id' => 'r2', 'title' => 'B' ) ) ) ) ),
	box( 'c4', widget( 'w4', 'dpk-statement', array( 'title' => 'Old statement' ) ) ),
);

echo "1. Owner edits are kept, untouched values follow the kit\n";
$local                                         = $base;
$local[0]['elements'][0]['settings']['title']  = 'MY OWN TITLE';                      // owner edit
$local[0]['elements'][0]['settings']['_margin'] = array( 'top' => '10' );            // owner style edit (new key)
$local[1]['elements'][0]['settings']['title_typography_font_size'] = array( 'size' => 60 ); // owner style edit
$remote                                        = $base;
$remote[0]['elements'][0]['settings']['subtitle'] = 'New kit subtitle';             // kit update, untouched by owner
$remote[0]['elements'][0]['settings']['title']    = 'KIT TITLE V2';                 // kit update, edited by owner
$s      = new Sync( $defaults );
$merged = $s->merge_list( $base, $local, $remote );
$hero   = find_el( $merged, 'w1' )['settings'];
check( 'owner title kept', 'MY OWN TITLE' === $hero['title'] );
check( 'kit subtitle applied', 'New kit subtitle' === $hero['subtitle'] );
check( 'owner margin kept', array( 'top' => '10' ) === $hero['_margin'] );
check( 'owner typography kept', array( 'size' => 60 ) === find_el( $merged, 'w2' )['settings']['title_typography_font_size'] );
check( 'stats: 1 updated, 1 kept', 1 === $s->stats['updated'] && 1 === $s->stats['kept'] );

echo "2. New kit section is inserted after its previous sibling\n";
$remote2   = $base;
array_splice( $remote2, 2, 0, array( box( 'c9', widget( 'w9', 'dpk-signature', array( 'title' => 'New' ) ) ) ) );
$s         = new Sync( $defaults );
$merged    = $s->merge_list( $base, $base, $remote2 );
check( 'order c1 c2 c9 c3 c4', array( 'c1', 'c2', 'c9', 'c3', 'c4' ) === ids( $merged ) );
check( 'stats added = 1', 1 === $s->stats['added'] );

echo "3. Sections deleted by the owner stay deleted, sections added by the owner stay\n";
$local3   = array( $base[0], box( 'mine', widget( 'wm', 'dpk-statement', array( 'title' => 'Mine' ) ) ), $base[2], $base[3] ); // c2 deleted
$remote3  = $base;
$remote3[1]['elements'][0]['settings']['title'] = 'Kit changed the deleted one';
$merged   = ( new Sync( $defaults ) )->merge_list( $base, $local3, $remote3 );
check( 'c2 not resurrected', null === find_el( $merged, 'c2' ) );
check( 'owner section kept in place', array( 'c1', 'mine', 'c3', 'c4' ) === ids( $merged ) );

echo "4. Kit removes a section: removed if untouched, kept if the owner edited it\n";
$remote4 = array( $base[0], $base[1] ); // c3 and c4 removed upstream
$local4  = $base;
$local4[3]['elements'][0]['settings']['title'] = 'Owner rewrote it';
$merged  = ( new Sync( $defaults ) )->merge_list( $base, $local4, $remote4 );
check( 'untouched c3 removed', null === find_el( $merged, 'c3' ) );
check( 'edited c4 kept', null !== find_el( $merged, 'c4' ) );

echo "5. Elementor strips settings equal to their default on save\n";
$local5 = $base;
unset( $local5[0]['elements'][0]['settings']['layout'] );   // 'centered' == default, removed by the editor
unset( $local5[0]['elements'][0]['settings']['title'] );    // 'DOUCE PARESSE' == default
$remote5 = $base;
$remote5[0]['elements'][0]['settings']['layout'] = 'split'; // kit switches layout
$merged  = ( new Sync( $defaults ) )->merge_list( $base, $local5, $remote5 );
$hero    = find_el( $merged, 'w1' )['settings'];
check( 'kit layout applied although key was stripped', isset( $hero['layout'] ) && 'split' === $hero['layout'] );
check( 'stripped title not re-added (still default)', ! isset( $hero['title'] ) );

echo "6. Owner reset a value to the default: remote change of that value is not forced\n";
$local6 = $base;
$local6[0]['elements'][0]['settings']['subtitle'] = 'l’art de la courtoisie française'; // owner typed the default back
unset( $local6[0]['elements'][0]['settings']['subtitle'] );
$remote6 = $base;
$remote6[0]['elements'][0]['settings']['subtitle'] = 'Kit subtitle v3';
$merged  = ( new Sync( $defaults ) )->merge_list( $base, $local6, $remote6 );
check( 'owner choice (default) kept', ! isset( find_el( $merged, 'w1' )['settings']['subtitle'] ) );

echo "7. Repeaters merge row by row\n";
$local7 = $base;
$local7[2]['elements'][0]['settings']['items'][1]['title'] = 'Owner B';
$remote7 = $base;
$remote7[2]['elements'][0]['settings']['items'][0]['title'] = 'Kit A2';
$remote7[2]['elements'][0]['settings']['items'][] = array( '_id' => 'r3', 'title' => 'Kit C' );
$merged = ( new Sync( $defaults ) )->merge_list( $base, $local7, $remote7 );
$items  = find_el( $merged, 'w3' )['settings']['items'];
check( 'owner row edit kept', 'Owner B' === $items[1]['title'] );
check( 'untouched row follows the kit', 'Kit A2' === $items[0]['title'] );
check( 'new kit row added after its sibling', 3 === count( $items ) && 'Kit C' === $items[2]['title'] );
$local7b = $base;
unset( $local7b[2]['elements'][0]['settings']['items'][0] );
$local7b[2]['elements'][0]['settings']['items'] = array_values( $local7b[2]['elements'][0]['settings']['items'] );
$merged = ( new Sync( $defaults ) )->merge_list( $base, $local7b, $remote7 );
$items  = find_el( $merged, 'w3' )['settings']['items'];
check( 'row deleted by the owner stays deleted', array( 'r2', 'r3' ) === array_column( $items, '_id' ) );

echo "7b. Kit rows inserted / removed in the middle (kit _ids follow the row position)\n";
$row  = function ( $id, $prefix, $text, $url ) { return array( '_id' => $id, 'prefix' => $prefix, 'text' => $text, 'link' => array( 'url' => $url ) ); };
$sync = function ( $b, $l, $r ) { return ( new Sync( function () { return array(); } ) )->merge_settings( array( 'c' => $b ), array( 'c' => $l ), array( 'c' => $r ) )['c']; };
$fmt  = function ( $rows ) { return implode( ' / ', array_map( function ( $r ) { return $r['prefix'] . ':' . $r['text']; }, $rows ) ); };
$b0   = array( $row( 'e0', 'Email', 'contact@dp.com', 'mailto:contact@dp.com' ), $row( 'e1', '', 'Instagram', 'https://instagram.com/dp' ) );
$l0   = $b0;
$l0[1]['text'] = '@douceparesse';
$r0   = array( $row( 'e0', 'Email', 'contact@dp.com', 'mailto:contact@dp.com' ), $row( 'e1', 'Tél', '01 23', 'tel:+331' ), $row( 'e2', '', 'Instagram', 'https://instagram.com/dp' ) );
$m0   = $sync( $b0, $l0, $r0 );
check( 'row inserted in the middle: owner edit kept, no mixed row, no duplicate', 'Email:contact@dp.com / Tél:01 23 / :@douceparesse' === $fmt( $m0 ) );
check( 'ids stay unique', count( $m0 ) === count( array_unique( array_column( $m0, '_id' ) ) ) );
check( 'second sync (snapshot = raw kit rows) changes nothing', $m0 === $sync( $r0, $m0, $r0 ) );
$r1 = array( $row( 'e0', 'Email', 'contact@dp.com', 'mailto:contact@dp.com' ), $row( 'e1', 'Tél', '01 23', 'tel:+331' ), $row( 'e2', '', 'Instagram', 'https://instagram.com/dp' ) );
$r1[1]['text'] = '01 99';
check( 'later kit change of the inserted row reaches it', 'Email:contact@dp.com / Tél:01 99 / :@douceparesse' === $fmt( $sync( $r0, $m0, $r1 ) ) );
$b2 = array( $row( 'e0', 'Email', 'a@dp.com', 'mailto:a@dp.com' ), $row( 'e1', '', 'Instagram', 'https://instagram.com/dp' ), $row( 'e2', '', 'LinkedIn', 'https://linkedin.com/dp' ) );
$l2 = $b2;
$l2[2]['text'] = 'Mon LinkedIn';
$r2 = array( $row( 'e0', 'Email', 'a@dp.com', 'mailto:a@dp.com' ), $row( 'e1', '', 'LinkedIn', 'https://linkedin.com/dp' ) );
check( 'row removed in the middle: untouched row dropped, edited row kept', 'Email:a@dp.com / :Mon LinkedIn' === $fmt( $sync( $b2, $l2, $r2 ) ) );
$r3 = $b0;
$r3[1]['link']['url'] = 'https://instagram.com/new';
$m3 = $sync( $b0, $l0, $r3 );
check( 'kit edits a row in place (new URL): merged into the same row', 2 === count( $m3 ) && 'https://instagram.com/new' === $m3[1]['link']['url'] && '@douceparesse' === $m3[1]['text'] );

echo "8. Idempotent when nothing changed + type-insensitive comparison\n";
$local8 = $base;
$local8[0]['elements'][0]['settings']['image']['id'] = '5'; // string vs int after a save
$s      = new Sync( $defaults );
$merged = $s->merge_list( $base, $local8, $base );
check( 'no change', Sync::same( $merged, $local8 ) && 0 === $s->stats['updated'] && 0 === $s->stats['kept'] );

echo "9. Re-sync after a sync keeps the owner's edits (snapshot = last kit version)\n";
$first  = ( new Sync( $defaults ) )->merge_list( $base, $local, $remote ); // as in test 1
$again  = ( new Sync( $defaults ) )->merge_list( $remote, $first, $remote );
check( 'second sync changes nothing', Sync::same( $again, $first ) );

echo "10. Change counter\n";
$s = new Sync( $defaults );
check( 'untouched = 0', 0 === $s->count_changes( $base, $base ) );
check( 'stripped defaults = 0', 0 === $s->count_changes( $base, $local5 ) );
check( 'test-1 edits = 3', 3 === $s->count_changes( $base, $local ) );

echo $failures ? "\n$failures FAILED\n" : "\nAll sync tests passed\n";
exit( $failures ? 1 : 0 );

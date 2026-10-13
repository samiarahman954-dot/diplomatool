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

echo "7. Repeaters are compared as a whole\n";
$local7 = $base;
$local7[2]['elements'][0]['settings']['items'][1]['title'] = 'Owner B';
$remote7 = $base;
$remote7[2]['elements'][0]['settings']['items'][0]['title'] = 'Kit A2';
$merged = ( new Sync( $defaults ) )->merge_list( $base, $local7, $remote7 );
check( 'owner repeater kept', 'Owner B' === find_el( $merged, 'w3' )['settings']['items'][1]['title'] );

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

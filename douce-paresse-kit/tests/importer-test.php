<?php
/**
 * End-to-end importer test with in-memory WordPress / Elementor Pro stubs:
 *   php tests/importer-test.php
 */
// In-memory WordPress + Elementor (Pro) stubs to exercise DPK\Importer end to end.
namespace {
$fake = sys_get_temp_dir() . '/dpk-fakewp/'; @mkdir( $fake . 'wp-admin/includes', 0777, true ); file_put_contents( $fake . 'wp-admin/includes/image.php', '<?php' );
define( 'ABSPATH', $fake ); define( 'DPK_PATH', dirname( __DIR__ ) . '/' ); define( 'DPK_URL', 'http://site/wp-content/plugins/dpk/' );
define( 'ELEMENTOR_VERSION', '3.30.0' );
$POSTS = array(); $META = array(); $OPTS = array(); $TERMS = array(); $NEXT = 100; $CONDS = array();
function __( $s ) { return $s; }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function wp_slash( $v ) { return addslashes( $v ); }
function wp_unslash( $v ) { return stripslashes( $v ); }
function home_url( $p = '/' ) { return 'http://site' . $p; }
function get_permalink( $id ) { global $POSTS, $OPTS; if ( ( $OPTS['page_on_front'] ?? 0 ) == $id ) { return 'http://site/'; } return 'http://site/' . $POSTS[ $id ]['post_name'] . '/'; }
function wp_insert_post( $a ) { global $POSTS, $NEXT; $id = $NEXT++; $a['ID'] = $id; $a['post_name'] = $a['post_name'] ?? sanitize( $a['post_title'] ); $POSTS[ $id ] = $a; return $id; }
function sanitize( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $s ) ); }
function wp_insert_attachment( $a, $f ) { $a['post_type'] = 'attachment'; $a['file'] = $f; return wp_insert_post( $a ); }
function update_post_meta( $id, $k, $v ) { global $META; $META[ $id ][ $k ] = is_string( $v ) ? wp_unslash( $v ) : $v; return true; }
function get_post_meta( $id, $k, $single = true ) { global $META; return $META[ $id ][ $k ] ?? ''; }
function delete_post_meta( $id, $k ) { global $META; unset( $META[ $id ][ $k ] ); }
function get_posts( $q ) {
	global $POSTS, $META; $types = (array) $q['post_type']; $st = (array) $q['post_status']; $out = array();
	foreach ( $POSTS as $id => $p ) {
		if ( ! in_array( $p['post_type'], $types, true ) || ! in_array( $p['post_status'], $st, true ) ) { continue; }
		if ( ( $META[ $id ][ $q['meta_key'] ] ?? null ) !== $q['meta_value'] ) { continue; }
		$out[] = $id;
	}
	return array_slice( $out, 0, $q['numberposts'] );
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error { public $m; function __construct( $c, $m ) { $this->m = $m; } function get_error_message() { return $this->m; } }
function wp_upload_bits( $name, $x, $bits ) { return array( 'file' => '/uploads/' . $name, 'url' => 'http://site/uploads/' . $name, 'error' => false ); }
function wp_check_filetype( $f ) { return array( 'type' => 'image/webp' ); }
function wp_generate_attachment_metadata() { return array(); }
function wp_update_attachment_metadata() {}
function wp_get_attachment_url( $id ) { global $POSTS; return 'http://site/uploads/' . basename( $POSTS[ $id ]['file'] ); }
function wp_set_object_terms( $id, $t ) { global $TERMS; $TERMS[ $id ] = $t; }
function update_option( $k, $v ) { global $OPTS; $OPTS[ $k ] = $v; }
function get_option( $k ) { global $OPTS; return $OPTS[ $k ] ?? false; }
function did_action() { return 1; }
}
namespace ElementorPro\Modules\ThemeBuilder {
class CM { function save_conditions( $id, $c ) { global $CONDS; $CONDS[ $id ] = $c; } }
class Module { static function instance() { return new self(); } function get_conditions_manager() { return new CM(); } }
}
namespace Elementor {
class Exp { function is_feature_active( $f ) { return true; } }
class WM { function get_widget_types( $t ) { return null; } }
class FM { public $cleared = 0; function clear_cache() { $this->cleared++; } }
class Plugin { public static $instance; public $experiments, $widgets_manager, $elements_manager, $files_manager; }
Plugin::$instance = new Plugin(); Plugin::$instance->experiments = new Exp(); Plugin::$instance->widgets_manager = new WM(); Plugin::$instance->elements_manager = new WM(); Plugin::$instance->files_manager = new FM();
}
namespace {
require DPK_PATH . 'includes/class-sync.php';
require DPK_PATH . 'includes/class-importer.php';
$fail = 0;
function ok( $l, $c ) { global $fail; echo ( $c ? '  ok   ' : '  FAIL ' ) . $l . "\n"; if ( ! $c ) { $fail++; } }
class TestImporter extends DPK\Importer { public $override = null; public function demo() { return $this->override ?: parent::demo(); } }
function run( $imp, $mode, $keys = null ) {
	$keys = $keys ?: array_keys( $imp->items() ); $log = array();
	foreach ( $imp->image_files() as $f ) { $imp->ensure_image( $f ); }
	$log = array_merge( $log, $imp->prepare( $keys, true ) );
	foreach ( $keys as $k ) { $log[] = $imp->import_item( $k, $mode ); }
	return $log;
}
function count_type( $t ) { global $POSTS; return count( array_filter( $POSTS, function ( $p ) use ( $t ) { return $p['post_type'] === $t; } ) ); }
function els( $id ) { return json_decode( get_post_meta( $id, '_elementor_data' ), true ); }
function widget_settings( $id, $wtype ) { foreach ( els( $id ) as $c ) { foreach ( $c['elements'] as $w ) { if ( $w['widgetType'] === $wtype ) { return $w['settings']; } } } }

$imp = new TestImporter();
echo "First import\n";
$log = run( $imp, 'sync' );
echo '    ' . implode( "\n    ", $log ) . "\n";
ok( '7 pages', 7 === count_type( 'page' ) );
ok( '2 templates', 2 === count_type( 'elementor_library' ) );
ok( '27 images', 27 === count_type( 'attachment' ) );
$h = $imp->find_post( 'header' ); $f = $imp->find_post( 'footer' ); $home = $imp->find_post( 'home' );
ok( 'header is a Theme Builder header', 'header' === get_post_meta( $h, '_elementor_template_type' ) && 'header' === $TERMS[ $h ] );
ok( 'footer is a Theme Builder footer', 'footer' === get_post_meta( $f, '_elementor_template_type' ) && 'footer' === $TERMS[ $f ] );
ok( 'header/footer conditions = entire site', $CONDS[ $h ] === array( array( 'type' => 'include', 'name' => 'general' ) ) && isset( $CONDS[ $f ] ) );
ok( 'front page set', $OPTS['page_on_front'] === $home && 'page' === $OPTS['show_on_front'] );
ok( 'page template full width', 'elementor_header_footer' === get_post_meta( $home, '_wp_page_template' ) );
$hs = widget_settings( $h, 'dpk-header' );
ok( 'header link resolved to imported page', 'http://site/la-maison/' === $hs['menu_left'][0]['link']['url'] );
ok( 'logo uses media library image', is_int( $hs['logo']['id'] ) && false !== strpos( $hs['logo']['url'], '/uploads/logo-icon-navy.png' ) );
ok( 'repeater rows have stable _id', ! empty( $hs['menu_left'][0]['_id'] ) );
ok( 'container wrapper', 'container' === els( $home )[0]['elType'] && 'full' === els( $home )[0]['settings']['content_width'] );
ok( 'no pending flags left', '' === get_post_meta( $home, '_dpk_pending' ) );

echo "Second import (no duplicates)\n";
run( $imp, 'sync' );
ok( 'still 7 pages / 2 templates / 27 images', 7 === count_type( 'page' ) && 2 === count_type( 'elementor_library' ) && 27 === count_type( 'attachment' ) );
ok( 'status untouched', 0 === $imp->status( 'home' )['edits'] && ! $imp->status( 'home' )['update'] );

echo "Owner edits the home page in Elementor\n";
$data = els( $home );
$data[0]['elements'][0]['settings']['title'] = 'MON TITRE';                       // content edit
$data[1]['elements'][0]['settings']['title_typography_font_size'] = array( 'unit' => 'px', 'size' => 70 ); // style edit
$data[] = array( 'id' => 'mine001', 'elType' => 'container', 'settings' => array(), 'elements' => array() ); // own section
update_post_meta( $home, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
ok( 'status shows 3 edits', 3 === $imp->status( 'home' )['edits'] );

echo "Kit gets updated (hero subtitle changed, hero title changed, new section added)\n";
$demo = $imp->demo();
$demo['items']['home']['widgets'][0]['settings']['subtitle'] = 'NOUVEAU SOUS-TITRE';
$demo['items']['home']['widgets'][0]['settings']['title'] = 'KIT TITLE V2';
array_splice( $demo['items']['home']['widgets'], 1, 0, array( array( 'key' => 'new', 'label' => 'New', 'type' => 'dpk-statement', 'settings' => array( 'title' => 'Nouvelle section' ) ) ) );
$imp->override = $demo;
ok( 'status shows kit update', $imp->status( 'home' )['update'] );
echo '    ' . $imp->import_item( 'home', 'sync' ) . "\n";
$after = els( $home );
$hero  = $after[0]['elements'][0]['settings'];
ok( 'owner title kept', 'MON TITRE' === $hero['title'] );
ok( 'kit subtitle applied', 'NOUVEAU SOUS-TITRE' === $hero['subtitle'] );
ok( 'owner style kept', 70 === $after[2]['elements'][0]['settings']['title_typography_font_size']['size'] );
ok( 'new kit section inserted after hero', 'dpk-statement' === $after[1]['elements'][0]['widgetType'] );
ok( 'owner section kept at the end', 'mine001' === end( $after )['id'] );
ok( 'backup saved', (bool) get_post_meta( $home, '_dpk_backup' ) );
echo '    ' . $imp->import_item( 'home', 'sync' ) . "\n";
ok( 'resync is a no-op', DPK\Sync::same( els( $home ), $after ) );

echo "Skip mode / reset / restore\n";
echo '    ' . $imp->import_item( 'home', 'skip' ) . "\n";
ok( 'skip untouched', DPK\Sync::same( els( $home ), $after ) );
echo '    ' . $imp->import_item( 'home', 'reset' ) . "\n";
ok( 'reset gives kit title', 'KIT TITLE V2' === els( $home )[0]['elements'][0]['settings']['title'] );
$imp->restore( 'home' );
ok( 'restore brings owner title back', 'MON TITRE' === els( $home )[0]['elements'][0]['settings']['title'] );

echo "Trashed page is not resurrected\n";
$POSTS[ $imp->find_post( 'contact' ) ]['post_status'] = 'trash';
run( $imp, 'sync' );
ok( 'no new contact page', 7 === count_type( 'page' ) && 'trashed' === $imp->status( 'contact' )['state'] );

echo $fail ? "\n$fail FAILED\n" : "\nAll importer tests passed\n";
exit( $fail ? 1 : 0 );
}

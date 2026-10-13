<?php
/**
 * Control ids must not collide with the controls Elementor / Elementor Pro add to every widget
 * (Advanced tab). A collision silently replaces the kit control (e.g. Pro's "sticky" select turned
 * the header's "Fixed on scroll" switch off). Also catches duplicates inside one widget.
 * Run: php tests/controls-test.php
 */
$root     = dirname( __DIR__ );
$reserved = '/^(_.*|sticky.*|motion_fx.*|hide_.*|custom_css.*|custom_attributes|animation.*|e_display_conditions|css_classes|element_id|position|z_index|transform.*|background.*|border.*|mask.*|width|height|margin|padding|align_self|order|flex_.*|display_conditions.*|section_effects|section_custom_css.*|section_attributes|section_layout|section_style|section_advanced|section_responsive|section_transform)$/';
$fails    = 0;
$base     = file_get_contents( $root . '/includes/class-widget-base.php' ) . file_get_contents( $root . '/includes/trait-form.php' );
foreach ( glob( $root . '/widgets/class-*.php' ) as $file ) {
	$src = file_get_contents( $file );
	preg_match_all( "/(?:add_(?:responsive_)?control|start_controls_(?:section|tabs|tab))\(\s*'([a-z0-9_]+)'/", $src, $m );
	$ids  = $m[1];
	$seen = array();
	foreach ( $ids as $id ) {
		if ( preg_match( $reserved, $id ) ) {
			echo '  FAIL ' . basename( $file ) . ": control id '$id' is reserved by Elementor / Elementor Pro\n";
			$fails++;
		}
		if ( isset( $seen[ $id ] ) && ! preg_match( '/^(title|text|link|label|image|desc|value|num|icon|content|rows|lead|closing|body)$/', $id ) ) {
			echo '  FAIL ' . basename( $file ) . ": control id '$id' used twice\n";
			$fails++;
		}
		$seen[ $id ] = 1;
	}
	echo '  ok   ' . basename( $file ) . ' (' . count( $ids ) . " ids)\n";
}
preg_match_all( "/add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $base, $m );
foreach ( $m[1] as $id ) {
	if ( preg_match( $reserved, $id ) ) {
		echo "  FAIL base: control id '$id' is reserved\n";
		$fails++;
	}
}
$header = file_get_contents( $root . '/widgets/class-header.php' );
if ( false === strpos( $header, "'dpk_fixed'" ) ) {
	echo "  FAIL header: dpk_fixed control missing\n";
	$fails++;
}
echo $fails ? "\n$fails FAILURE(S)\n" : "\nALL OK\n";
exit( $fails ? 1 : 0 );

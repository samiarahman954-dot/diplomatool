<?php
/**
 * Elementor integration: widget category and widgets.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widget class files => class names.
 */
function lilo_elementor_widgets() {
	return array(
		'header'            => 'Lilo_Widget_Header',
		'footer'            => 'Lilo_Widget_Footer',
		'hero'              => 'Lilo_Widget_Hero',
		'menu-categories'   => 'Lilo_Widget_Menu_Categories',
		'price-list'        => 'Lilo_Widget_Price_List',
		'product-showcase'  => 'Lilo_Widget_Product_Showcase',
		'story'             => 'Lilo_Widget_Story',
		'mission'           => 'Lilo_Widget_Mission',
		'visit'             => 'Lilo_Widget_Visit',
		'tags'              => 'Lilo_Widget_Tags',
		'social'            => 'Lilo_Widget_Social',
		'page-header'       => 'Lilo_Widget_Page_Header',
		'menu-tabs'         => 'Lilo_Widget_Menu_Tabs',
		'menu-list'         => 'Lilo_Widget_Menu_List',
		'menu-options'      => 'Lilo_Widget_Menu_Options',
		'menu-photos'       => 'Lilo_Widget_Menu_Photos',
		'note'              => 'Lilo_Widget_Note',
	);
}

add_action( 'elementor/elements/categories_registered', 'lilo_elementor_category' );
function lilo_elementor_category( $elements_manager ) {
	$elements_manager->add_category(
		'lilo-cafe',
		array(
			'title' => __( 'Lilo Cafe', 'lilo-cafe' ),
			'icon'  => 'eicon-cup',
		)
	);
}

add_action( 'elementor/widgets/register', 'lilo_register_elementor_widgets' );
function lilo_register_elementor_widgets( $widgets_manager ) {
	require_once LILO_DIR . '/inc/elementor/class-lilo-widget-base.php';
	require_once LILO_DIR . '/inc/elementor/class-lilo-menu-section-base.php';
	foreach ( lilo_elementor_widgets() as $file => $class ) {
		require_once LILO_DIR . '/inc/elementor/widgets/' . $file . '.php';
		$widgets_manager->register( new $class() );
	}
}

/**
 * Brand fonts inside the Elementor editor panel previews.
 */
add_action( 'elementor/editor/after_enqueue_styles', 'lilo_elementor_editor_styles' );
function lilo_elementor_editor_styles() {
	wp_add_inline_style( 'elementor-editor', '#elementor-panel-category-lilo-cafe .elementor-element .icon{color:#E6535F}' );
}

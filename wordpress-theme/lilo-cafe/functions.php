<?php
/**
 * Lilo Cafe theme bootstrap.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

define( 'LILO_VERSION', '1.0.1' );
define( 'LILO_DIR', get_template_directory() );
define( 'LILO_URI', get_template_directory_uri() );

require LILO_DIR . '/inc/helpers.php';
require LILO_DIR . '/inc/setup.php';
require LILO_DIR . '/inc/customizer.php';
require LILO_DIR . '/inc/template-parts.php';
require LILO_DIR . '/inc/header-footer-builder.php';
require LILO_DIR . '/inc/demo/demo-data.php';
require LILO_DIR . '/inc/elementor/elementor.php';

if ( is_admin() ) {
	require LILO_DIR . '/inc/demo/importer.php';
}

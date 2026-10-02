<?php
/**
 * Plugin Name:       Just Move DFW – Elementor Template Kit
 * Description:       The Just Move DFW home page rebuilt as custom Elementor widgets (one widget per section, no default Elementor widgets) with one-click demo import.
 * Version:           1.3.0
 * Author:            Just Move DFW
 * Text Domain:       jmk
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Elementor tested up to: 3.30
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JMK_VERSION', '1.3.0' );
define( 'JMK_FILE', __FILE__ );
define( 'JMK_PATH', plugin_dir_path( __FILE__ ) );
define( 'JMK_URL', plugin_dir_url( __FILE__ ) );
define( 'JMK_MIN_ELEMENTOR', '3.5.0' );

require_once JMK_PATH . 'includes/class-plugin.php';
require_once JMK_PATH . 'includes/class-leads.php';
require_once JMK_PATH . 'includes/class-quote.php';
require_once JMK_PATH . 'includes/class-demo-importer.php';
require_once JMK_PATH . 'includes/class-sync.php';
require_once JMK_PATH . 'includes/class-global-styles.php';
require_once JMK_PATH . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'JMK_Admin', 'on_activate' ) );

add_action( 'plugins_loaded', array( 'JMK_Plugin', 'instance' ) );

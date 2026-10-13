<?php
/**
 * Plugin Name:       Douce Paresse — Elementor Template Kit
 * Description:       Douce Paresse website as an Elementor kit: one custom widget per section, one-click demo import (pages + Theme Builder header/footer) and a safe sync that keeps your own edits.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Douce Paresse
 * Text Domain:       douce-paresse-kit
 * Elementor tested up to: 3.30.0
 * Elementor Pro tested up to: 3.30.0
 */

defined( 'ABSPATH' ) || exit;

define( 'DPK_VERSION', '1.1.0' );
define( 'DPK_FILE', __FILE__ );
define( 'DPK_PATH', plugin_dir_path( __FILE__ ) );
define( 'DPK_URL', plugin_dir_url( __FILE__ ) );

require_once DPK_PATH . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'DPK\\Plugin', 'instance' ) );

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'dpk_purge_spam' );
	}
);

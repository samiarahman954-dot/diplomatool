<?php
/**
 * Plugin Name:       Brotherhood News AI
 * Description:       Lee feeds RSS, filtra por palabras clave, reescribe las noticias con la API de Claude (Anthropic) y las guarda como borrador para revisión editorial. Incluye productos de Amazon y publicación automática en X y Telegram.
 * Version:           0.3.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Samia
 * Text Domain:       bh-news-ai
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'BHNA_VERSION', '0.3.1' );
define( 'BHNA_DB_VERSION', '1' );
define( 'BHNA_FILE', __FILE__ );
define( 'BHNA_DIR', plugin_dir_path( __FILE__ ) );
define( 'BHNA_URL', plugin_dir_url( __FILE__ ) );

require_once BHNA_DIR . 'includes/class-bhna-settings.php';
require_once BHNA_DIR . 'includes/class-bhna-installer.php';
require_once BHNA_DIR . 'includes/class-bhna-items.php';
require_once BHNA_DIR . 'includes/class-bhna-logger.php';
require_once BHNA_DIR . 'includes/class-bhna-filter.php';
require_once BHNA_DIR . 'includes/class-bhna-fetcher.php';
require_once BHNA_DIR . 'includes/class-bhna-claude.php';
require_once BHNA_DIR . 'includes/class-bhna-quality.php';
require_once BHNA_DIR . 'includes/class-bhna-publisher.php';
require_once BHNA_DIR . 'includes/class-bhna-notifier.php';
require_once BHNA_DIR . 'includes/class-bhna-processor.php';
require_once BHNA_DIR . 'includes/class-bhna-cron.php';
require_once BHNA_DIR . 'includes/class-bhna-amazon.php';
require_once BHNA_DIR . 'includes/class-bhna-social.php';
require_once BHNA_DIR . 'includes/class-bhna-setup.php';
require_once BHNA_DIR . 'includes/class-bhna-plugin.php';

if ( is_admin() ) {
	require_once BHNA_DIR . 'admin/class-bhna-ui.php';
	require_once BHNA_DIR . 'admin/class-bhna-admin.php';
	require_once BHNA_DIR . 'admin/class-bhna-extras-admin.php';
}

register_activation_hook( __FILE__, array( 'BHNA_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BHNA_Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'BHNA_Plugin', 'init' ) );

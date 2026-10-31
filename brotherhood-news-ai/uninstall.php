<?php
/**
 * Removes the plugin's data on uninstall, only if the administrator enabled that option.
 * Drafts already created in WordPress are never deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$bhna_settings = get_option( 'bhna_settings', array() );

wp_clear_scheduled_hook( 'bhna_run' );

if ( empty( $bhna_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bhna_items" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

foreach ( array( 'bhna_settings', 'bhna_feeds', 'bhna_log', 'bhna_last_run', 'bhna_db_version' ) as $bhna_option ) {
	delete_option( $bhna_option );
}

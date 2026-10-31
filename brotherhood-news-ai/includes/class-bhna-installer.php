<?php
/**
 * Activation, deactivation and database schema.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Installer {

	public static function activate() {
		self::create_tables();

		if ( false === get_option( BHNA_Settings::OPTION ) ) {
			add_option( BHNA_Settings::OPTION, BHNA_Settings::defaults(), '', false );
		}
		if ( false === get_option( BHNA_Settings::FEEDS_OPTION ) ) {
			add_option( BHNA_Settings::FEEDS_OPTION, BHNA_Settings::default_feeds(), '', false );
			// First install: create the sections' categories and fill the keyword lists.
			BHNA_Setup::apply();
		}

		BHNA_Cron::schedule();
	}

	public static function deactivate() {
		BHNA_Cron::unschedule();
	}

	/** Runs on every load so schema changes in future versions are applied after an update. */
	public static function maybe_upgrade() {
		if ( get_option( 'bhna_db_version' ) !== BHNA_DB_VERSION ) {
			self::create_tables();
		}
	}

	public static function create_tables() {
		global $wpdb;

		$table   = BHNA_Items::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			feed_id varchar(64) NOT NULL DEFAULT '',
			guid_hash char(40) NOT NULL,
			source_name varchar(191) NOT NULL DEFAULT '',
			source_url text NOT NULL,
			title text NOT NULL,
			summary text NOT NULL,
			published_at datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			reason varchar(255) NOT NULL DEFAULT '',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			quality_flag tinyint(1) NOT NULL DEFAULT 0,
			model varchar(64) NOT NULL DEFAULT '',
			input_tokens int(10) unsigned NOT NULL DEFAULT 0,
			output_tokens int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			processed_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY guid_hash (guid_hash),
			KEY status (status),
			KEY created_at (created_at),
			KEY processed_at (processed_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'bhna_db_version', BHNA_DB_VERSION, false );
	}
}

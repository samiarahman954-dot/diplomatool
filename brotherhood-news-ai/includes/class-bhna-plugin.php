<?php
/**
 * Bootstrap.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Plugin {

	public static function init() {
		BHNA_Installer::maybe_upgrade();
		BHNA_Cron::init();

		if ( is_admin() ) {
			BHNA_Admin::init();
		}
	}
}

<?php
/**
 * Bootstrap.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Plugin {

	public static function init() {
		BHNA_Installer::maybe_upgrade();
		BHNA_I18N::init();
		BHNA_Cron::init();
		BHNA_Amazon::init();
		BHNA_Social::init();

		if ( is_admin() ) {
			BHNA_UI::init();
			BHNA_Admin::init();
			BHNA_Extras_Admin::init();
		}
	}
}

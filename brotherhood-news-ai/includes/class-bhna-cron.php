<?php
/**
 * Scheduled run: read the feeds, then process the queue.
 *
 * WP-Cron only fires when the site gets visits. For reliable 24/7 operation, add a real cron job in
 * Hostinger (see readme.txt) that calls wp-cron.php every 5–15 minutes.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Cron {

	const HOOK     = 'bhna_run';
	const SCHEDULE = 'bhna_interval';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	public static function add_schedule( $schedules ) {
		$minutes                     = max( 5, (int) BHNA_Settings::get( 'interval' ) );
		$schedules[ self::SCHEDULE ] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			/* translators: %d: minutes */
			'display'  => sprintf( 'Cada %d minutos (Brotherhood News AI)', $minutes ),
		);
		return $schedules;
	}

	public static function schedule() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Called after the interval setting changes. */
	public static function reschedule() {
		self::unschedule();
		self::schedule();
	}

	public static function run() {
		$fetched   = BHNA_Fetcher::run();
		$processed = BHNA_Processor::run();
		update_option( 'bhna_last_run', array( 'time' => time(), 'fetched' => $fetched, 'processed' => $processed ), false );
		return array( 'fetched' => $fetched, 'processed' => $processed );
	}
}

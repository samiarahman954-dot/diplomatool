<?php
/**
 * Small event log (last 200 entries) shown in the admin "Registro" screen.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Logger {

	const OPTION = 'bhna_log';
	const MAX    = 200;

	public static function info( $message ) {
		self::add( 'info', $message );
	}

	public static function warning( $message ) {
		self::add( 'warning', $message );
	}

	public static function error( $message ) {
		self::add( 'error', $message );
	}

	public static function add( $level, $message ) {
		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'time'    => time(),
				'level'   => $level,
				'message' => wp_strip_all_tags( (string) $message ),
			)
		);
		update_option( self::OPTION, array_slice( $log, 0, self::MAX ), false );
	}

	public static function entries() {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	public static function clear() {
		update_option( self::OPTION, array(), false );
	}
}

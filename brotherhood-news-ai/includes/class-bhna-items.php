<?php
/**
 * Data access for the items table: every feed entry the plugin has seen, with its processing status.
 *
 * Statuses: skipped (filtered out), queued, processing, done (draft created), failed.
 * The unique guid_hash is what prevents the same story from being processed twice.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Items {

	const SKIPPED    = 'skipped';
	const QUEUED     = 'queued';
	const PROCESSING = 'processing';
	const DONE       = 'done';
	const FAILED     = 'failed';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bhna_items';
	}

	public static function now() {
		return current_time( 'mysql', true );
	}

	public static function hash( $guid ) {
		return sha1( (string) $guid );
	}

	/** Id and status of an already seen entry, or null. */
	public static function find( $guid ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM {$table} WHERE guid_hash = %s", self::hash( $guid ) ), ARRAY_A );
	}

	public static function exists( $guid ) {
		global $wpdb;
		$table = self::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE guid_hash = %s", self::hash( $guid ) ) );
	}

	/**
	 * Inserts a feed entry unless it was already seen. Returns the new id, or 0 for a duplicate.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now = self::now();

		$row = array(
			'feed_id'      => $data['feed_id'],
			'guid_hash'    => self::hash( $data['guid'] ),
			'source_name'  => $data['source_name'],
			'source_url'   => $data['source_url'],
			'title'        => $data['title'],
			'summary'      => $data['summary'],
			'published_at' => $data['published_at'],
			'status'       => $data['status'],
			'reason'       => isset( $data['reason'] ) ? $data['reason'] : '',
			'created_at'   => $now,
			'updated_at'   => $now,
		);

		// wpdb::insert() writes a null published_at as SQL NULL. A duplicate guid_hash (two overlapping
		// runs) fails on the unique key and simply returns 0.
		$suppress = $wpdb->suppress_errors( true );
		$inserted = $wpdb->insert( self::table(), $row );
		$wpdb->suppress_errors( $suppress );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	}

	public static function update( $id, array $fields ) {
		global $wpdb;
		$fields['updated_at'] = self::now();
		return $wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) );
	}

	/**
	 * Moves up to $limit queued items to "processing" and returns them.
	 * The conditional UPDATE makes sure two overlapping runs never claim the same item.
	 */
	public static function claim( $limit ) {
		global $wpdb;
		$table = self::table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT %d", self::QUEUED, $limit ) );

		$claimed = array();
		foreach ( $ids as $id ) {
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d AND status = %s",
					self::PROCESSING,
					self::now(),
					$id,
					self::QUEUED
				)
			);
			if ( $updated ) {
				$claimed[] = self::get( $id );
			}
		}
		return $claimed;
	}

	/** Items left in "processing" by a run that died (timeout, fatal error) go back to the queue. */
	public static function release_stuck( $minutes = 30 ) {
		global $wpdb;
		$table  = self::table();
		$before = gmdate( 'Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s WHERE status = %s AND updated_at < %s", self::QUEUED, self::PROCESSING, $before ) );
	}

	/** Start of the current day in the site's timezone, as a UTC datetime string. */
	public static function today_start_utc() {
		$start = new DateTime( 'today', wp_timezone() );
		$start->setTimezone( new DateTimeZone( 'UTC' ) );
		return $start->format( 'Y-m-d H:i:s' );
	}

	/** Number of drafts created by Claude today: what the daily limit counts. */
	public static function done_today() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s AND processed_at >= %s", self::DONE, self::today_start_utc() ) );
	}

	public static function count_by_status() {
		global $wpdb;
		$table  = self::table();
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );
		$counts = array_fill_keys( array( self::SKIPPED, self::QUEUED, self::PROCESSING, self::DONE, self::FAILED ), 0 );
		foreach ( $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}
		return $counts;
	}

	public static function recent( $limit = 100, $status = '' ) {
		global $wpdb;
		$table = self::table();
		if ( $status ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d", $status, $limit ), ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	/** Per-day counts for the last $days days, keyed by local date. */
	public static function daily_stats( $days = 14 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT created_at, processed_at, status, input_tokens, output_tokens, model FROM {$table} WHERE created_at >= %s OR processed_at >= %s",
				$since,
				$since
			),
			ARRAY_A
		);

		$stats = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day           = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$stats[ $day ] = array( 'read' => 0, 'skipped' => 0, 'done' => 0, 'failed' => 0, 'cost' => 0.0 );
		}

		foreach ( $rows as $row ) {
			$read_day = wp_date( 'Y-m-d', strtotime( $row['created_at'] . ' UTC' ) );
			if ( isset( $stats[ $read_day ] ) ) {
				$stats[ $read_day ]['read']++;
				if ( self::SKIPPED === $row['status'] ) {
					$stats[ $read_day ]['skipped']++;
				}
			}
			if ( $row['processed_at'] ) {
				$done_day = wp_date( 'Y-m-d', strtotime( $row['processed_at'] . ' UTC' ) );
				if ( isset( $stats[ $done_day ] ) ) {
					if ( self::DONE === $row['status'] ) {
						$stats[ $done_day ]['done']++;
					} elseif ( self::FAILED === $row['status'] ) {
						$stats[ $done_day ]['failed']++;
					}
					$stats[ $done_day ]['cost'] += self::cost( $row['model'], $row['input_tokens'], $row['output_tokens'] );
				}
			}
		}
		return $stats;
	}

	/** Totals per feed: read, skipped and drafts created. */
	public static function feed_stats() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT feed_id, status, COUNT(*) AS total FROM {$table} GROUP BY feed_id, status", ARRAY_A );
		$stats = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $stats[ $row['feed_id'] ] ) ) {
				$stats[ $row['feed_id'] ] = array( 'read' => 0, 'skipped' => 0, 'done' => 0, 'failed' => 0 );
			}
			$stats[ $row['feed_id'] ]['read'] += (int) $row['total'];
			if ( isset( $stats[ $row['feed_id'] ][ $row['status'] ] ) ) {
				$stats[ $row['feed_id'] ][ $row['status'] ] += (int) $row['total'];
			}
		}
		return $stats;
	}

	/** Estimated USD cost of one Claude call (thinking tokens are included in output_tokens). */
	public static function cost( $model, $input_tokens, $output_tokens ) {
		if ( ! array_key_exists( (string) $model, BHNA_Settings::MODELS ) ) {
			return 0.0;
		}
		$price = BHNA_Settings::MODELS[ $model ];
		return ( $input_tokens * $price['in'] + $output_tokens * $price['out'] ) / 1000000;
	}

	public static function retry( $id ) {
		return self::update( $id, array( 'status' => self::QUEUED, 'attempts' => 0, 'reason' => '' ) );
	}
}

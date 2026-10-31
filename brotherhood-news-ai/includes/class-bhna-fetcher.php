<?php
/**
 * Reads the enabled RSS feeds and stores new entries (title + summary only, never the full article).
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Fetcher {

	/** Entries older than this are ignored, so a new feed doesn't flood the queue with old news. */
	const MAX_AGE_HOURS = 48;

	/** Maximum entries read per feed and run. */
	const MAX_ITEMS = 20;

	/**
	 * @return array{new: int, queued: int, skipped: int, errors: int}
	 */
	public static function run() {
		$totals = array( 'new' => 0, 'queued' => 0, 'skipped' => 0, 'errors' => 0 );

		foreach ( BHNA_Settings::feeds() as $feed ) {
			if ( empty( $feed['enabled'] ) || empty( $feed['url'] ) ) {
				continue;
			}
			$result = self::fetch_feed( $feed );
			if ( is_wp_error( $result ) ) {
				$totals['errors']++;
				$message = sprintf( 'Error leyendo el feed "%s" (%s): %s', self::label( $feed ), $feed['url'], $result->get_error_message() );
				BHNA_Logger::error( $message );
				BHNA_Notifier::error_alert( 'feed_' . $feed['id'], $message );
				continue;
			}
			foreach ( $totals as $key => $value ) {
				if ( isset( $result[ $key ] ) ) {
					$totals[ $key ] += $result[ $key ];
				}
			}
		}

		if ( $totals['new'] ) {
			BHNA_Logger::info( sprintf( 'Feeds leídos: %d noticias nuevas, %d en cola, %d descartadas por el filtro.', $totals['new'], $totals['queued'], $totals['skipped'] ) );
		}
		return $totals;
	}

	public static function fetch_feed( array $feed ) {
		require_once ABSPATH . WPINC . '/feed.php';

		$lifetime = function () {
			return 5 * MINUTE_IN_SECONDS;
		};
		add_filter( 'wp_feed_cache_transient_lifetime', $lifetime );
		$rss = fetch_feed( $feed['url'] );
		remove_filter( 'wp_feed_cache_transient_lifetime', $lifetime );

		if ( is_wp_error( $rss ) ) {
			return $rss;
		}

		$counts  = array( 'new' => 0, 'queued' => 0, 'skipped' => 0 );
		$min_age = time() - self::MAX_AGE_HOURS * HOUR_IN_SECONDS;

		foreach ( $rss->get_items( 0, self::MAX_ITEMS ) as $entry ) {
			$link  = esc_url_raw( (string) $entry->get_permalink() );
			$guid  = $entry->get_id() ? $entry->get_id() : $link;
			$title = self::clean( $entry->get_title() );

			if ( ! $guid || ! $title || BHNA_Items::exists( $guid ) ) {
				continue;
			}

			$timestamp = $entry->get_date( 'U' );
			if ( $timestamp && (int) $timestamp < $min_age ) {
				continue;
			}

			$summary = self::clean( $entry->get_description() );
			if ( function_exists( 'mb_substr' ) ) {
				$summary = mb_substr( $summary, 0, 1500 );
			}

			list( $passes, $reason ) = BHNA_Filter::check( $title, $summary, $feed );

			$id = BHNA_Items::insert(
				array(
					'feed_id'      => $feed['id'],
					'guid'         => $guid,
					'source_name'  => $feed['name'],
					'source_url'   => $link,
					'title'        => $title,
					'summary'      => $summary,
					'published_at' => $timestamp ? gmdate( 'Y-m-d H:i:s', (int) $timestamp ) : null,
					'status'       => $passes ? BHNA_Items::QUEUED : BHNA_Items::SKIPPED,
					'reason'       => $reason,
				)
			);

			if ( $id ) {
				$counts['new']++;
				$counts[ $passes ? 'queued' : 'skipped' ]++;
			}
		}

		return $counts;
	}

	public static function clean( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = wp_strip_all_tags( $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	public static function label( array $feed ) {
		$label = $feed['name'];
		if ( ! empty( $feed['category_id'] ) ) {
			$category = get_cat_name( (int) $feed['category_id'] );
			if ( $category ) {
				$label .= ' – ' . $category;
			}
		}
		return $label;
	}
}

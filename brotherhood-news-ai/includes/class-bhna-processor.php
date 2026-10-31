<?php
/**
 * Processes the queue: sends queued items to Claude, creates the drafts and alerts the editors.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Processor {

	const LOCK = 'bhna_processing_lock';

	/**
	 * @return array{done: int, failed: int, retried: int, limit_reached: bool}
	 */
	public static function run( $max = null ) {
		$totals = array( 'done' => 0, 'failed' => 0, 'retried' => 0, 'limit_reached' => false );

		// One run at a time: overlapping cron and "Run now" calls would double the API spend.
		if ( get_transient( self::LOCK ) ) {
			return $totals;
		}
		set_transient( self::LOCK, 1, 10 * MINUTE_IN_SECONDS );

		try {
			BHNA_Items::release_stuck();

			$remaining = (int) BHNA_Settings::get( 'daily_limit' ) - BHNA_Items::done_today();
			if ( $remaining <= 0 ) {
				$totals['limit_reached'] = true;
				return $totals;
			}

			$batch = null === $max ? (int) BHNA_Settings::get( 'batch_size' ) : (int) $max;
			$batch = max( 1, min( $batch, $remaining ) );

			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 60 + $batch * BHNA_Claude::TIMEOUT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}

			foreach ( BHNA_Items::claim( $batch ) as $item ) {
				$outcome = self::process_item( $item );
				$totals[ $outcome ]++;
			}
		} finally {
			delete_transient( self::LOCK );
		}

		if ( $totals['done'] || $totals['failed'] ) {
			BHNA_Logger::info( sprintf( 'Procesadas con Claude: %d borradores creados, %d fallidas, %d para reintentar.', $totals['done'], $totals['failed'], $totals['retried'] ) );
		}
		return $totals;
	}

	/**
	 * @return string done|failed|retried
	 */
	public static function process_item( array $item ) {
		$feed = BHNA_Settings::feed( $item['feed_id'] );
		if ( ! $feed ) {
			// Feed removed after the item was queued: keep the item's own data.
			$feed = BHNA_Settings::new_feed( array( 'id' => $item['feed_id'], 'name' => $item['source_name'] ) );
		}

		$attempts = (int) $item['attempts'] + 1;
		$rewrite  = BHNA_Claude::rewrite( $item, $feed );

		if ( is_wp_error( $rewrite ) ) {
			return self::handle_error( $item, $rewrite, $attempts );
		}

		$missing = array();
		if ( BHNA_Settings::get( 'quality_check' ) ) {
			$missing = BHNA_Quality::missing(
				$item['title'] . ' ' . $item['summary'],
				$rewrite['titulo'] . ' ' . implode( ' ', $rewrite['cuerpo'] )
			);
		}

		$post_id = BHNA_Publisher::create_post( $item, $feed, $rewrite, $missing );
		if ( is_wp_error( $post_id ) ) {
			$error = new WP_Error( 'bhna_post', 'No se pudo crear la entrada: ' . $post_id->get_error_message(), array( 'retryable' => true ) );
			return self::handle_error( $item, $error, $attempts, $rewrite );
		}

		BHNA_Items::update(
			$item['id'],
			array(
				'status'        => BHNA_Items::DONE,
				'reason'        => $missing ? 'Revisar datos: ' . implode( ', ', $missing ) : '',
				'attempts'      => $attempts,
				'post_id'       => $post_id,
				'quality_flag'  => $missing ? 1 : 0,
				'model'         => $rewrite['model'],
				'input_tokens'  => (int) $item['input_tokens'] + $rewrite['input_tokens'],
				'output_tokens' => (int) $item['output_tokens'] + $rewrite['output_tokens'],
				'processed_at'  => BHNA_Items::now(),
			)
		);

		BHNA_Notifier::draft_ready( $post_id, $item, $missing );
		return 'done';
	}

	private static function handle_error( array $item, WP_Error $error, $attempts, array $usage = array() ) {
		$data      = (array) $error->get_error_data();
		$usage     = array_merge( $data, $usage );
		$retryable = ! empty( $data['retryable'] ) && $attempts < (int) BHNA_Settings::get( 'max_attempts' );

		$fields = array(
			'status'        => $retryable ? BHNA_Items::QUEUED : BHNA_Items::FAILED,
			'reason'        => mb_substr( $error->get_error_message(), 0, 250 ),
			'attempts'      => $attempts,
			'input_tokens'  => (int) $item['input_tokens'] + ( isset( $usage['input_tokens'] ) ? (int) $usage['input_tokens'] : 0 ),
			'output_tokens' => (int) $item['output_tokens'] + ( isset( $usage['output_tokens'] ) ? (int) $usage['output_tokens'] : 0 ),
		);
		if ( isset( $usage['model'] ) ) {
			$fields['model'] = $usage['model'];
		}
		if ( ! $retryable ) {
			$fields['processed_at'] = BHNA_Items::now();
		}
		BHNA_Items::update( $item['id'], $fields );

		$message = sprintf( '"%s": %s', $item['title'], $error->get_error_message() );
		if ( $retryable ) {
			BHNA_Logger::warning( $message . ' (se reintentará)' );
			return 'retried';
		}

		BHNA_Logger::error( $message );
		BHNA_Notifier::error_alert( $error->get_error_code(), $message );
		return 'failed';
	}
}

<?php
/**
 * Sends every lead (quote builder and quick form) to a Smartsheet sheet as a new row.
 *
 * - The API token is entered on the settings screen and stored in the database only;
 *   it is never printed back into a page.
 * - Rows are matched to the sheet's columns by column title, so the owner can reorder,
 *   hide or add columns freely. "Create a sheet for me" builds one with every column.
 * - Sending never slows down or breaks the visitor's form: it runs after the response
 *   is sent, and anything that fails is retried by WP-Cron (and can be retried by hand).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Smartsheet {

	const OPT          = 'jmk_smartsheet';
	const SLUG         = 'jmk-smartsheet';
	const CRON         = 'jmk_smartsheet_retry';
	const META_STATUS  = '_jmk_ss_status';   // pending | sent | failed
	const META_ROW     = '_jmk_ss_row';
	const META_ERROR   = '_jmk_ss_error';
	const META_TRIES   = '_jmk_ss_tries';
	const MAX_TRIES    = 6;
	const SHEET_NAME   = 'Just Move DFW – Website Leads';
	const TIMEOUT      = 15;

	const REGIONS = array(
		'us'  => array( 'United States (smartsheet.com)', 'https://api.smartsheet.com/2.0' ),
		'eu'  => array( 'Europe (smartsheet.eu)', 'https://api.smartsheet.eu/2.0' ),
		'au'  => array( 'Australia (smartsheet.au)', 'https://api.smartsheet.au/2.0' ),
		'gov' => array( 'Smartsheet Gov (smartsheetgov.com)', 'https://api.smartsheetgov.com/2.0' ),
	);

	/**
	 * Column title => Smartsheet column type. Titles are what rows are matched by; never rename.
	 */
	const COLUMNS = array(
		'Name'              => 'TEXT_NUMBER',
		'Phone'             => 'TEXT_NUMBER',
		'Email'             => 'TEXT_NUMBER',
		'Received'          => 'TEXT_NUMBER',
		'Lead source'       => 'TEXT_NUMBER',
		'Estimate #'        => 'TEXT_NUMBER',
		'Move type'         => 'TEXT_NUMBER',
		'Move size'         => 'TEXT_NUMBER',
		'From'              => 'TEXT_NUMBER',
		'To'                => 'TEXT_NUMBER',
		'Move date'         => 'DATE',
		'Flexibility'       => 'TEXT_NUMBER',
		'Crew'              => 'TEXT_NUMBER',
		'Packing service'   => 'TEXT_NUMBER',
		'Packing materials' => 'TEXT_NUMBER',
		'Specialty items'   => 'TEXT_NUMBER',
		'Estimate'          => 'TEXT_NUMBER',
		'Photos'            => 'TEXT_NUMBER',
		'Page'              => 'TEXT_NUMBER',
		'Lead link'         => 'TEXT_NUMBER',
	);

	/** Leads queued in this request, sent once the response has gone out. */
	private static $queue = array();

	public static function init() {
		add_action( 'jmk_lead_received', array( __CLASS__, 'on_lead' ), 10, 2 );
		add_action( 'jmk_quote_received', array( __CLASS__, 'on_lead' ), 10, 2 );
		add_action( self::CRON, array( __CLASS__, 'retry' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 12 );
		add_action( 'admin_post_jmk_ss_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_jmk_ss_sheet', array( __CLASS__, 'handle_sheet' ) );
		add_action( 'admin_post_jmk_ss_create', array( __CLASS__, 'handle_create' ) );
		add_action( 'admin_post_jmk_ss_columns', array( __CLASS__, 'handle_columns' ) );
		add_action( 'admin_post_jmk_ss_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_jmk_ss_backfill', array( __CLASS__, 'handle_backfill' ) );
		add_action( 'admin_post_jmk_ss_resend', array( __CLASS__, 'handle_resend' ) );
		add_action( 'admin_notices', array( __CLASS__, 'leads_notice' ) );
	}

	public static function on_deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function settings() {
		$s = get_option( self::OPT );
		return array_merge(
			array(
				'token'      => '',
				'region'     => 'us',
				'sheet_id'   => '',
				'sheet_name' => '',
				'account'    => '',
				'last_error' => '',
			),
			is_array( $s ) ? $s : array()
		);
	}

	private static function save( array $changes ) {
		update_option( self::OPT, array_merge( self::settings(), $changes ), false );
	}

	public static function connected() {
		$s = self::settings();
		return '' !== $s['token'] && '' !== $s['sheet_id'];
	}

	/* ------------------------------------------------------------------
	 * API
	 * ------------------------------------------------------------------ */

	/**
	 * @return array|WP_Error Decoded JSON body.
	 */
	public static function request( $method, $path, $body = null, $token = null, $region = null ) {
		$s      = self::settings();
		$token  = null === $token ? $s['token'] : $token;
		$region = null === $region ? $s['region'] : $region;
		if ( '' === $token ) {
			return new WP_Error( 'jmk_ss_token', __( 'No Smartsheet API token saved.', 'jmk' ) );
		}
		$base = isset( self::REGIONS[ $region ] ) ? self::REGIONS[ $region ][1] : self::REGIONS['us'][1];
		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$res = wp_remote_request( $base . $path, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'jmk_ss_http', sprintf( /* translators: %s: error */ __( 'Could not reach Smartsheet: %s', 'jmk' ), $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : 'HTTP ' . $code;
			if ( 401 === $code || 403 === $code ) {
				$msg = __( 'Smartsheet did not accept the API token (or it has no access to this sheet). Check the token and the region.', 'jmk' ) . ' (' . $msg . ')';
			}
			return new WP_Error( 'jmk_ss_api', $msg, array( 'status' => $code ) );
		}
		return is_array( $data ) ? $data : array();
	}

	public static function sheets() {
		$res = self::request( 'GET', '/sheets?includeAll=true' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = array();
		foreach ( isset( $res['data'] ) ? $res['data'] : array() as $sh ) {
			if ( isset( $sh['id'], $sh['name'] ) ) {
				$out[ (string) $sh['id'] ] = $sh['name'];
			}
		}
		asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
	}

	/**
	 * Lower-cased column title => column (id, title, type). Cached for 10 minutes.
	 *
	 * @return array|WP_Error
	 */
	public static function columns( $fresh = false ) {
		$s   = self::settings();
		$key = 'jmk_ss_cols_' . md5( $s['sheet_id'] );
		if ( ! $fresh ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$res = self::request( 'GET', '/sheets/' . rawurlencode( $s['sheet_id'] ) . '/columns?includeAll=true' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = array();
		foreach ( isset( $res['data'] ) ? $res['data'] : array() as $c ) {
			if ( isset( $c['id'], $c['title'] ) ) {
				$out[ self::norm( $c['title'] ) ] = array(
					'id'    => $c['id'],
					'title' => $c['title'],
					'type'  => isset( $c['type'] ) ? $c['type'] : 'TEXT_NUMBER',
				);
			}
		}
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	private static function norm( $title ) {
		return strtolower( trim( preg_replace( '/\s+/', ' ', (string) $title ) ) );
	}

	/* ------------------------------------------------------------------
	 * Lead → row
	 * ------------------------------------------------------------------ */

	/**
	 * Column title => value for a stored lead (works for new and old leads alike).
	 */
	public static function row_values( $post_id ) {
		$post   = get_post( $post_id );
		$record = JMK_Leads::quote_record( $post_id );
		$time   = $post && ! empty( $post->post_date_gmt ) ? strtotime( $post->post_date_gmt . ' UTC' ) : time();
		$v      = array(
			'Received'  => wp_date( 'Y-m-d H:i', $time ),
			'Phone'     => (string) get_post_meta( $post_id, '_jmk_phone', true ),
			'Page'      => (string) get_post_meta( $post_id, '_jmk_page_url', true ),
			'Lead link' => admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
		);

		if ( $record ) {
			$q   = $record['quote'];
			$yes = static function ( $b ) {
				return $b ? 'Yes' : 'No';
			};
			$v += array(
				'Name'              => $q['name'],
				'Email'             => $q['email'],
				'Lead source'       => 'Quote builder',
				'Estimate #'        => '#' . (int) $record['est_no'],
				'Move type'         => $q['move_type'],
				'Move size'         => isset( JMK_Quote::SIZES[ $q['size'] ] ) ? JMK_Quote::SIZES[ $q['size'] ] : $q['size'],
				'From'              => $q['from_addr'] . ' (' . $q['from_access'] . ')',
				'To'                => $q['to_addr'] . ' (' . $q['to_access'] . ')',
				'Move date'         => $q['move_date'],
				'Flexibility'       => $q['flex'],
				'Crew'              => $q['crew'] ? $q['crew'] . ' movers' : '',
				'Packing service'   => $yes( ! empty( $q['pack'] ) ),
				'Packing materials' => $yes( ! empty( $q['supplies'] ) ),
				'Specialty items'   => implode( ', ', (array) $q['specialty'] ),
				'Estimate'          => ! empty( $record['view'] ) ? $record['view']['total'] : 'Custom quote',
				'Photos'            => (string) count( (array) $record['files'] ),
			);
			if ( '' === $v['Page'] && ! empty( $record['page_url'] ) ) {
				$v['Page'] = $record['page_url'];
			}
		} else {
			$from   = (string) get_post_meta( $post_id, '_jmk_from_zip', true );
			$to     = (string) get_post_meta( $post_id, '_jmk_to_zip', true );
			$source = (string) get_post_meta( $post_id, '_jmk_source', true );
			$v     += array(
				'Name'        => $post ? $post->post_title : '',
				'Lead source' => 'Quick quote form' . ( '' !== $source ? ' (' . $source . ')' : '' ),
				'Move size'   => (string) get_post_meta( $post_id, '_jmk_size', true ),
				'From'        => '' !== $from ? 'ZIP ' . $from : '',
				'To'          => '' !== $to ? 'ZIP ' . $to : '',
			);
		}
		return $v;
	}

	/**
	 * Smartsheet cells for the sheet's columns; values with no matching column are skipped.
	 *
	 * @return array|WP_Error
	 */
	public static function cells( array $values, array $columns ) {
		$cells = array();
		foreach ( $values as $title => $value ) {
			$key = self::norm( $title );
			if ( ! isset( $columns[ $key ] ) ) {
				continue;
			}
			$value = mb_substr( wp_strip_all_tags( (string) $value ), 0, 4000 );
			if ( '' === $value ) {
				continue;
			}
			$cell = array(
				'columnId' => $columns[ $key ]['id'],
				'value'    => $value,
				'strict'   => false,
			);
			if ( preg_match( '#^https?://#i', $value ) && 'TEXT_NUMBER' === $columns[ $key ]['type'] ) {
				$cell['hyperlink'] = array( 'url' => $value );
			}
			$cells[] = $cell;
		}
		if ( ! $cells ) {
			return new WP_Error( 'jmk_ss_columns', __( 'None of the sheet\'s column titles match the lead fields (Name, Phone, Email…). Use "Add missing columns" or "Create a sheet for me".', 'jmk' ) );
		}
		return $cells;
	}

	/**
	 * Add one lead to the sheet.
	 *
	 * @return true|WP_Error
	 */
	public static function send( $post_id, $force = false ) {
		$post_id = (int) $post_id;
		if ( ! self::connected() ) {
			return new WP_Error( 'jmk_ss_off', __( 'Smartsheet is not connected.', 'jmk' ) );
		}
		if ( ! $force && 'sent' === get_post_meta( $post_id, self::META_STATUS, true ) ) {
			return true;
		}
		// Shutdown push and cron retry can overlap: one sender per lead at a time.
		$lock = 'jmk_ss_lock_' . $post_id;
		if ( get_transient( $lock ) ) {
			return new WP_Error( 'jmk_ss_busy', __( 'Already being sent.', 'jmk' ) );
		}
		set_transient( $lock, 1, 60 );

		$result  = true;
		$columns = self::columns();
		if ( is_wp_error( $columns ) ) {
			$result = $columns;
		} else {
			$cells = self::cells( self::row_values( $post_id ), $columns );
			if ( is_wp_error( $cells ) ) {
				// The sheet may have changed since the columns were cached: look once more.
				$columns = self::columns( true );
				$cells   = is_wp_error( $columns ) ? $columns : self::cells( self::row_values( $post_id ), $columns );
			}
			if ( is_wp_error( $cells ) ) {
				$result = $cells;
			} else {
				$res = self::request(
					'POST',
					'/sheets/' . rawurlencode( self::settings()['sheet_id'] ) . '/rows',
					array(
						array(
							'toBottom' => true,
							'cells'    => $cells,
						),
					)
				);
				if ( is_wp_error( $res ) ) {
					$result = $res;
				} else {
					$row = isset( $res['result'][0]['id'] ) ? $res['result'][0]['id'] : ( isset( $res['result']['id'] ) ? $res['result']['id'] : '' );
					update_post_meta( $post_id, self::META_STATUS, 'sent' );
					update_post_meta( $post_id, self::META_ROW, (string) $row );
					delete_post_meta( $post_id, self::META_ERROR );
				}
			}
		}

		if ( is_wp_error( $result ) ) {
			update_post_meta( $post_id, self::META_STATUS, 'failed' );
			update_post_meta( $post_id, self::META_ERROR, $result->get_error_message() );
			update_post_meta( $post_id, self::META_TRIES, 1 + (int) get_post_meta( $post_id, self::META_TRIES, true ) );
			self::save( array( 'last_error' => current_time( 'mysql' ) . ' – ' . $result->get_error_message() ) );
			self::schedule_retry();
		}
		delete_transient( $lock );
		return $result;
	}

	/* ------------------------------------------------------------------
	 * Queue
	 * ------------------------------------------------------------------ */

	/**
	 * A lead was stored: mark it and send it once the visitor has their response.
	 */
	public static function on_lead( $data, $post_id ) {
		if ( ! self::connected() ) {
			return;
		}
		update_post_meta( $post_id, self::META_STATUS, 'pending' );
		if ( ! self::$queue ) {
			add_action( 'shutdown', array( __CLASS__, 'flush_queue' ), 1 );
		}
		self::$queue[] = (int) $post_id;
		// Safety net in case this request is cut short before shutdown runs.
		self::schedule_retry();
	}

	public static function flush_queue() {
		if ( ! self::$queue ) {
			return;
		}
		// Hand the visitor their response first, then talk to Smartsheet.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
		$ids         = array_unique( self::$queue );
		self::$queue = array();
		foreach ( $ids as $id ) {
			self::send( $id );
		}
	}

	public static function schedule_retry() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON );
		}
	}

	/**
	 * Leads that are pending or failed (and not given up on), oldest first.
	 */
	public static function unsent_ids( $limit = 25, $include_never = false ) {
		$meta = array(
			'relation' => 'OR',
			array(
				'key'   => self::META_STATUS,
				'value' => array( 'pending', 'failed' ),
				'compare' => 'IN',
			),
		);
		if ( $include_never ) {
			$meta[] = array(
				'key'     => self::META_STATUS,
				'compare' => 'NOT EXISTS',
			);
		}
		return get_posts(
			array(
				'post_type'      => JMK_Leads::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	public static function retry() {
		if ( ! self::connected() ) {
			wp_clear_scheduled_hook( self::CRON );
			return;
		}
		// Leads that failed MAX_TRIES times are left for "Send leads not in Smartsheet yet".
		$ids = array_values(
			array_filter(
				self::unsent_ids( 200 ),
				static function ( $id ) {
					return (int) get_post_meta( $id, self::META_TRIES, true ) < self::MAX_TRIES;
				}
			)
		);
		$left = count( $ids ) - 25;
		foreach ( array_slice( $ids, 0, 25 ) as $id ) {
			if ( is_wp_error( self::send( $id ) ) && (int) get_post_meta( $id, self::META_TRIES, true ) < self::MAX_TRIES ) {
				$left++;
			}
		}
		if ( $left <= 0 ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Show the result of a "Send / Retry" click on the Leads list.
	 */
	public static function leads_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . JMK_Leads::POST_TYPE !== $screen->id ) {
			return;
		}
		$notice = get_transient( 'jmk_ss_notice_' . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( 'jmk_ss_notice_' . get_current_user_id() );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $notice[0] ? 'success' : 'error', esc_html( $notice[1] ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( JMK_Admin::SLUG, __( 'Smartsheet', 'jmk' ), __( 'Smartsheet', 'jmk' ), 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	private static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	private static function back( $msg, $ok = true ) {
		set_transient( 'jmk_ss_notice_' . get_current_user_id(), array( $ok, $msg ), 120 );
		wp_safe_redirect( self::url() );
		exit;
	}

	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( $action );
	}

	private static function form_open( $action ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = self::settings();
		$notice = get_transient( 'jmk_ss_notice_' . get_current_user_id() );
		delete_transient( 'jmk_ss_notice_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Smartsheet', 'jmk' ); ?></h1>
			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice notice-<?php echo $notice[0] ? 'success' : 'error'; ?>"><p><?php echo esc_html( $notice[1] ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Every new lead from the quote builder and the quick quote form is added to your Smartsheet as a new row, so all your leads are in one place. Leads are still saved under Just Move Kit → Leads and emailed to you as before.', 'jmk' ); ?></p>

			<div class="card" style="max-width:820px">
				<h2><?php esc_html_e( '1. Connect your Smartsheet account', 'jmk' ); ?></h2>
				<?php if ( '' !== $s['account'] ) : ?>
					<p>✅ <?php echo esc_html( sprintf( /* translators: %s: account */ __( 'Connected as %s', 'jmk' ), $s['account'] ) ); ?></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'In Smartsheet: Account (your picture, bottom left) → Personal Settings → API Access → Generate new access token. Paste it below.', 'jmk' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="jmk_ss_save">
					<?php wp_nonce_field( 'jmk_ss_save' ); ?>
					<table class="form-table" role="presentation">
						<tr><th><label for="jmk-ss-token"><?php esc_html_e( 'API access token', 'jmk' ); ?></label></th><td>
							<input type="password" id="jmk-ss-token" name="token" class="regular-text" autocomplete="off" value=""
								placeholder="<?php echo esc_attr( '' !== $s['token'] ? sprintf( /* translators: %s: last 4 characters */ __( 'Saved (ends in %s) – leave empty to keep', 'jmk' ), substr( $s['token'], -4 ) ) : __( 'Paste your token', 'jmk' ) ); ?>">
							<?php if ( '' !== $s['token'] ) : ?>
								<p><label><input type="checkbox" name="disconnect" value="1"> <?php esc_html_e( 'Disconnect (remove the saved token)', 'jmk' ); ?></label></p>
							<?php endif; ?>
						</td></tr>
						<tr><th><label for="jmk-ss-region"><?php esc_html_e( 'Smartsheet region', 'jmk' ); ?></label></th><td>
							<select id="jmk-ss-region" name="region">
								<?php foreach ( self::REGIONS as $k => $r ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['region'], $k ); ?>><?php echo esc_html( $r[0] ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Most accounts are United States. Pick the one in your Smartsheet web address.', 'jmk' ); ?></p>
						</td></tr>
					</table>
					<?php submit_button( __( 'Save & test connection', 'jmk' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<?php if ( '' !== $s['token'] ) : ?>
				<div class="card" style="max-width:820px">
					<h2><?php esc_html_e( '2. Choose the sheet for your leads', 'jmk' ); ?></h2>
					<?php
					$sheets = self::sheets();
					if ( is_wp_error( $sheets ) ) :
						?>
						<p style="color:#b32d2e"><?php echo esc_html( $sheets->get_error_message() ); ?></p>
					<?php else : ?>
						<?php self::form_open( 'jmk_ss_sheet' ); ?>
							<select name="sheet_id" style="max-width:420px">
								<option value=""><?php esc_html_e( '— Choose a sheet —', 'jmk' ); ?></option>
								<?php foreach ( $sheets as $id => $name ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['sheet_id'], (string) $id ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php submit_button( __( 'Use this sheet', 'jmk' ), 'secondary', 'submit', false ); ?>
						</form>
						<p><?php esc_html_e( 'Or let us make a ready-made sheet with all the lead columns:', 'jmk' ); ?></p>
						<?php self::form_open( 'jmk_ss_create' ); ?>
							<?php submit_button( sprintf( /* translators: %s: sheet name */ __( 'Create "%s" for me', 'jmk' ), self::SHEET_NAME ), 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( self::connected() ) : ?>
				<div class="card" style="max-width:820px">
					<h2><?php echo esc_html( sprintf( /* translators: %s: sheet name */ __( '3. Sending leads to "%s"', 'jmk' ), $s['sheet_name'] ) ); ?></h2>
					<?php
					$cols = self::columns( true );
					if ( is_wp_error( $cols ) ) :
						?>
						<p style="color:#b32d2e"><?php echo esc_html( $cols->get_error_message() ); ?></p>
						<?php
					else :
						$missing = array_values( array_filter( array_keys( self::COLUMNS ), static function ( $t ) use ( $cols ) {
							return ! isset( $cols[ self::norm( $t ) ] );
						} ) );
						?>
						<p><?php esc_html_e( 'Rows are matched to your columns by their titles. Columns with other titles are left empty, and you can move, hide or add columns in Smartsheet at any time.', 'jmk' ); ?></p>
						<?php if ( $missing ) : ?>
							<p><?php echo esc_html( sprintf( /* translators: %s: column list */ __( 'Not in this sheet yet: %s', 'jmk' ), implode( ', ', $missing ) ) ); ?></p>
							<?php self::form_open( 'jmk_ss_columns' ); ?>
								<?php submit_button( __( 'Add missing columns', 'jmk' ), 'secondary', 'submit', false ); ?>
							</form>
						<?php else : ?>
							<p>✅ <?php esc_html_e( 'All lead columns are in the sheet.', 'jmk' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
					<div>
						<?php self::form_open( 'jmk_ss_test' ); ?>
							<?php submit_button( __( 'Send a test row', 'jmk' ), 'secondary', 'submit', false ); ?>
						</form>
						<?php
						$unsent = count( self::unsent_ids( 500, true ) );
						self::form_open( 'jmk_ss_backfill' );
						submit_button( sprintf( /* translators: %d: number of leads */ __( 'Send leads not in Smartsheet yet (%d)', 'jmk' ), $unsent ), 'secondary', 'submit', false, $unsent ? array() : array( 'disabled' => 'disabled' ) );
						?>
						</form>
					</div>
					<?php if ( '' !== $s['last_error'] ) : ?>
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s: error */ __( 'Last problem: %s (failed leads are retried every hour).', 'jmk' ), $s['last_error'] ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_save() {
		self::guard( 'jmk_ss_save' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in guard().
		if ( ! empty( $_POST['disconnect'] ) ) {
			delete_option( self::OPT );
			wp_clear_scheduled_hook( self::CRON );
			self::back( __( 'Smartsheet disconnected and the token removed.', 'jmk' ) );
		}
		$region = isset( $_POST['region'] ) ? sanitize_key( wp_unslash( $_POST['region'] ) ) : 'us';
		$region = isset( self::REGIONS[ $region ] ) ? $region : 'us';
		$token  = isset( $_POST['token'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', wp_unslash( $_POST['token'] ) ) : '';
		// phpcs:enable
		$s     = self::settings();
		$token = '' !== $token ? $token : $s['token'];
		if ( '' === $token ) {
			self::back( __( 'Please paste your Smartsheet API token.', 'jmk' ), false );
		}

		$me = self::request( 'GET', '/users/me', null, $token, $region );
		if ( is_wp_error( $me ) ) {
			// Keep the old working token; never store one that Smartsheet rejected.
			self::back( $me->get_error_message(), false );
		}
		$who     = trim( ( isset( $me['firstName'] ) ? $me['firstName'] : '' ) . ' ' . ( isset( $me['lastName'] ) ? $me['lastName'] : '' ) );
		$email   = isset( $me['email'] ) ? $me['email'] : '';
		$changed = $token !== $s['token'] || $region !== $s['region'];
		self::save(
			array(
				'token'      => $token,
				'region'     => $region,
				'account'    => trim( $who . ( '' !== $email ? ' <' . $email . '>' : '' ) ),
				'last_error' => '',
			) + ( $changed && '' !== $s['token'] ? array( 'sheet_id' => '', 'sheet_name' => '' ) : array() )
		);
		self::back( __( 'Connected to Smartsheet. Now choose or create the sheet for your leads.', 'jmk' ) );
	}

	public static function handle_sheet() {
		self::guard( 'jmk_ss_sheet' );
		$id     = isset( $_POST['sheet_id'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['sheet_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sheets = self::sheets();
		if ( is_wp_error( $sheets ) ) {
			self::back( $sheets->get_error_message(), false );
		}
		if ( '' === $id || ! isset( $sheets[ $id ] ) ) {
			self::back( __( 'Please choose a sheet.', 'jmk' ), false );
		}
		self::save( array( 'sheet_id' => $id, 'sheet_name' => $sheets[ $id ] ) );
		self::back( sprintf( /* translators: %s: sheet */ __( 'New leads will now go to "%s".', 'jmk' ), $sheets[ $id ] ) );
	}

	public static function handle_create() {
		self::guard( 'jmk_ss_create' );
		$cols  = array();
		$first = true;
		foreach ( self::COLUMNS as $title => $type ) {
			$col = array( 'title' => $title, 'type' => $first ? 'TEXT_NUMBER' : $type );
			if ( $first ) {
				$col['primary'] = true;
			}
			$cols[] = $col;
			$first  = false;
		}
		$res = self::request( 'POST', '/sheets', array( 'name' => self::SHEET_NAME, 'columns' => $cols ) );
		if ( is_wp_error( $res ) ) {
			self::back( $res->get_error_message(), false );
		}
		$id = isset( $res['result']['id'] ) ? (string) $res['result']['id'] : '';
		if ( '' === $id ) {
			self::back( __( 'Smartsheet did not return the new sheet. Please try again.', 'jmk' ), false );
		}
		self::save( array( 'sheet_id' => $id, 'sheet_name' => self::SHEET_NAME ) );
		self::back( sprintf( /* translators: %s: sheet */ __( 'Created "%s" in your Smartsheet (Sheets / Home). New leads will go there.', 'jmk' ), self::SHEET_NAME ) );
	}

	public static function handle_columns() {
		self::guard( 'jmk_ss_columns' );
		$have = self::columns( true );
		if ( is_wp_error( $have ) ) {
			self::back( $have->get_error_message(), false );
		}
		$add = array();
		$at  = count( $have );
		foreach ( self::COLUMNS as $title => $type ) {
			if ( ! isset( $have[ self::norm( $title ) ] ) ) {
				$add[] = array( 'title' => $title, 'type' => $type, 'index' => $at++ );
			}
		}
		if ( $add ) {
			$res = self::request( 'POST', '/sheets/' . rawurlencode( self::settings()['sheet_id'] ) . '/columns', $add );
			if ( is_wp_error( $res ) ) {
				self::back( $res->get_error_message(), false );
			}
			self::columns( true );
		}
		/* translators: %d: number of columns */
		self::back( sprintf( _n( 'Added %d column to the sheet.', 'Added %d columns to the sheet.', count( $add ), 'jmk' ), count( $add ) ) );
	}

	public static function handle_test() {
		self::guard( 'jmk_ss_test' );
		$cols = self::columns( true );
		if ( is_wp_error( $cols ) ) {
			self::back( $cols->get_error_message(), false );
		}
		$values = array(
			'Name'        => 'TEST – website connection (you can delete this row)',
			'Phone'       => '(000) 000-0000',
			'Email'       => 'test@example.com',
			'Received'    => wp_date( 'Y-m-d H:i' ),
			'Lead source' => 'Test from WordPress',
			'Estimate'    => '$0',
		);
		$cells = self::cells( $values, $cols );
		if ( is_wp_error( $cells ) ) {
			self::back( $cells->get_error_message(), false );
		}
		$res = self::request( 'POST', '/sheets/' . rawurlencode( self::settings()['sheet_id'] ) . '/rows', array( array( 'toBottom' => true, 'cells' => $cells ) ) );
		if ( is_wp_error( $res ) ) {
			self::back( $res->get_error_message(), false );
		}
		self::back( __( 'Test row added. Open the sheet in Smartsheet to see it, then delete it.', 'jmk' ) );
	}

	public static function handle_backfill() {
		self::guard( 'jmk_ss_backfill' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$sent  = 0;
		$fail  = null;
		$start = time();
		foreach ( self::unsent_ids( 100, true ) as $id ) {
			if ( time() - $start > 90 ) {
				break;
			}
			$r = self::send( $id );
			if ( is_wp_error( $r ) ) {
				$fail = $r;
				break; // Same cause for the rest (token, sheet…): stop and show it.
			}
			$sent++;
		}
		if ( $fail ) {
			/* translators: 1: number sent, 2: error */
			self::back( sprintf( __( '%1$d lead(s) sent, then Smartsheet said: %2$s', 'jmk' ), $sent, $fail->get_error_message() ), false );
		}
		/* translators: %d: number of leads */
		self::back( sprintf( _n( '%d lead sent to Smartsheet.', '%d leads sent to Smartsheet.', $sent, 'jmk' ), $sent ) );
	}

	/**
	 * "Send to Smartsheet" link on a single lead.
	 */
	public static function handle_resend() {
		$id = isset( $_GET['lead'] ) ? (int) $_GET['lead'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::guard( 'jmk_ss_resend_' . $id );
		$r = self::send( $id );
		set_transient( 'jmk_ss_notice_' . get_current_user_id(), array( ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : __( 'Lead sent to Smartsheet.', 'jmk' ) ), 120 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . JMK_Leads::POST_TYPE ) );
		exit;
	}

	/**
	 * Status shown in the Leads list.
	 */
	public static function status_html( $post_id ) {
		$status = get_post_meta( $post_id, self::META_STATUS, true );
		if ( 'sent' === $status ) {
			return '<span style="color:#008a20">✓ ' . esc_html__( 'Sent', 'jmk' ) . '</span>';
		}
		if ( ! self::connected() ) {
			return '<span style="color:#888">—</span>';
		}
		$link = wp_nonce_url( admin_url( 'admin-post.php?action=jmk_ss_resend&lead=' . (int) $post_id ), 'jmk_ss_resend_' . (int) $post_id );
		if ( 'failed' === $status ) {
			$err = (string) get_post_meta( $post_id, self::META_ERROR, true );
			return '<span style="color:#b32d2e" title="' . esc_attr( $err ) . '">✗ ' . esc_html__( 'Failed', 'jmk' ) . '</span><br><a href="' . esc_url( $link ) . '">' . esc_html__( 'Retry', 'jmk' ) . '</a>';
		}
		if ( 'pending' === $status ) {
			return '<span style="color:#996800">' . esc_html__( 'Sending…', 'jmk' ) . '</span><br><a href="' . esc_url( $link ) . '">' . esc_html__( 'Send now', 'jmk' ) . '</a>';
		}
		return '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Send', 'jmk' ) . '</a>';
	}
}

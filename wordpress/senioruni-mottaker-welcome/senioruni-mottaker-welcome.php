<?php
/**
 * Plugin Name: SeniorUni – Mottaker-velkomst
 * Description: Sends the MemberPress welcome email to a Corporate Accounts sub-account (the "Mottaker" on a Familie purchase) only after they have set their password. Does not modify MemberPress, Corporate Accounts or the Vipps plugin.
 * Version:     1.0.2
 * Author:      SeniorUni
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

final class SeniorUni_Mottaker_Welcome {

	const META_PENDING = '_senioruni_mw_pending';
	const META_SENT    = '_senioruni_mw_sent';
	const OPTION       = 'senioruni_mw_template';
	const LOG_OPTION   = 'senioruni_mw_log';
	const LOG_MAX      = 30;
	const PRODUCT_TPL  = '__product_welcome__';

	/** Users handled during this request, so the two password hooks can't double-send. */
	private static $handled = array();

	/** Users whose reset key was issued during this request (i.e. account creation), not a later visit. */
	private static $pending_this_request = array();

	public static function init() {
		// A reset key is generated when the "Set your password" email is created.
		add_action( 'retrieve_password_key', array( __CLASS__, 'mark_pending' ), 10, 1 );

		// Core wp-login.php?action=rp flow.
		add_action( 'after_password_reset', array( __CLASS__, 'on_password_reset' ), 20, 1 );

		// Other paths that set the password, e.g. MemberPress's own reset form, which may
		// save via wp_set_password() or via wp_update_user() (profile_update).
		// Only act when a reset key was issued in an earlier request, so passwords set at
		// account creation are ignored.
		add_action( 'wp_set_password', array( __CLASS__, 'on_wp_set_password' ), 20, 2 );
		add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ), 20, 3 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
			add_action( 'admin_post_senioruni_mw_save', array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_senioruni_mw_test', array( __CLASS__, 'handle_test' ) );
		}
	}

	/* --------------------------------------------------------------------
	 * Trigger logic
	 * ------------------------------------------------------------------ */

	public static function mark_pending( $user_login ) {
		$user = get_user_by( 'login', $user_login );
		if ( $user && ! get_user_meta( $user->ID, self::META_SENT, true ) ) {
			update_user_meta( $user->ID, self::META_PENDING, time() );
			self::$pending_this_request[ $user->ID ] = true;
		}
	}

	public static function on_password_reset( $user ) {
		if ( $user instanceof WP_User ) {
			self::maybe_send( $user->ID, 'after_password_reset' );
		}
	}

	public static function on_wp_set_password( $password, $user_id ) {
		if ( self::is_pending_from_earlier_request( (int) $user_id ) ) {
			self::maybe_send( (int) $user_id, 'wp_set_password' );
		}
	}

	public static function on_profile_update( $user_id, $old_user_data, $userdata = array() ) {
		$password_changed = $old_user_data instanceof WP_User
			&& ! empty( $userdata['user_pass'] )
			&& $userdata['user_pass'] !== $old_user_data->user_pass;

		if ( $password_changed && self::is_pending_from_earlier_request( (int) $user_id ) ) {
			self::maybe_send( (int) $user_id, 'profile_update' );
		}
	}

	private static function is_pending_from_earlier_request( $user_id ) {
		return ! isset( self::$pending_this_request[ $user_id ] )
			&& get_user_meta( $user_id, self::META_PENDING, true );
	}

	private static function maybe_send( $user_id, $source ) {
		if ( isset( self::$handled[ $user_id ] ) ) {
			return;
		}
		self::$handled[ $user_id ] = true;

		if ( get_user_meta( $user_id, self::META_SENT, true ) ) {
			delete_user_meta( $user_id, self::META_PENDING );
			return;
		}

		if ( ! self::is_sub_account( $user_id ) ) {
			// Normal member or the purchaser resetting a password: not our business.
			delete_user_meta( $user_id, self::META_PENDING );
			return;
		}

		$result = self::send_welcome( $user_id, self::get_template() );

		if ( true === $result ) {
			update_user_meta( $user_id, self::META_SENT, time() );
			delete_user_meta( $user_id, self::META_PENDING );
			self::log( $user_id, 'sent', $source );
		} else {
			// Leave the pending flag so a later password set can retry.
			self::log( $user_id, 'failed: ' . $result, $source );
		}
	}

	/**
	 * A MemberPress Corporate Accounts sub-account carries the id of its parent
	 * corporate account in user meta. The purchaser (corporate account owner) does not.
	 */
	public static function is_sub_account( $user_id ) {
		$is_sub = '' !== (string) get_user_meta( $user_id, 'mpca_corporate_account_id', true );
		return (bool) apply_filters( 'senioruni_mw_is_sub_account', $is_sub, $user_id );
	}

	/* --------------------------------------------------------------------
	 * Sending through MemberPress's own templates
	 * ------------------------------------------------------------------ */

	/**
	 * @return true|string True on success, otherwise a reason string.
	 */
	public static function send_welcome( $user_id, $template, $to_override = '' ) {
		if ( ! class_exists( 'MeprEmailFactory' ) || ! class_exists( 'MeprUser' ) ) {
			return 'MemberPress is not active';
		}

		try {
			$usr = new MeprUser( $user_id );
			$txn = self::latest_transaction( $user_id );

			if ( self::PRODUCT_TPL === $template ) {
				if ( ! $txn ) {
					return 'no transaction found for membership-specific email';
				}
				$email = MeprEmailFactory::fetch(
					'MeprUserProductWelcomeEmail',
					'MeprBaseProductEmail',
					array( array( 'product_id' => $txn->product_id ) )
				);
			} else {
				$email = MeprEmailFactory::fetch( $template );
			}

			if ( ! $email ) {
				return "template {$template} not found";
			}
			if ( method_exists( $email, 'enabled' ) && ! $email->enabled() ) {
				return "template {$template} is disabled in MemberPress → Settings → Emails";
			}

			if ( $txn && class_exists( 'MeprTransactionsHelper' ) ) {
				$params = MeprTransactionsHelper::get_email_params( $txn );
			} elseif ( class_exists( 'MeprUsersHelper' ) && method_exists( 'MeprUsersHelper', 'get_email_params' ) ) {
				$params = MeprUsersHelper::get_email_params( $usr );
			} else {
				$params = array();
			}
			$params = apply_filters( 'senioruni_mw_email_params', $params, $user_id, $txn, $template );

			$email->to = $to_override ? $to_override : $usr->formatted_email();
			$email->send( $params );
		} catch ( \Throwable $e ) {
			return get_class( $e ) . ': ' . $e->getMessage();
		}

		return true;
	}

	private static function latest_transaction( $user_id ) {
		if ( ! class_exists( 'MeprDb' ) || ! class_exists( 'MeprTransaction' ) ) {
			return null;
		}
		global $wpdb;
		$mepr_db = new MeprDb();
		$txn_id  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$mepr_db->transactions}
				 WHERE user_id = %d AND status IN ('complete','confirmed')
				 ORDER BY created_at DESC LIMIT 1",
				$user_id
			)
		);
		return $txn_id ? new MeprTransaction( (int) $txn_id ) : null;
	}

	/**
	 * Member-facing MemberPress email templates, keyed by class name.
	 */
	public static function available_templates() {
		$out = array();
		if ( class_exists( 'MeprEmailFactory' ) ) {
			try {
				foreach ( (array) MeprEmailFactory::all( 'MeprBaseOptionsUserEmail' ) as $email ) {
					$class         = get_class( $email );
					$out[ $class ] = ! empty( $email->title ) ? wp_strip_all_tags( $email->title ) : $class;
				}
			} catch ( \Throwable $e ) {
				// Fall through with whatever was collected.
			}
		}
		if ( class_exists( 'MeprUserProductWelcomeEmail' ) ) {
			$out[ self::PRODUCT_TPL ] = 'Membership-specific welcome (set on the membership, e.g. Familie)';
		}
		return $out;
	}

	/**
	 * Saved choice, or the Corporate Accounts "Sub Account Welcome Email" if it can be found.
	 */
	public static function get_template() {
		$saved = get_option( self::OPTION, '' );
		if ( $saved ) {
			return $saved;
		}
		foreach ( self::available_templates() as $class => $title ) {
			if ( preg_match( '/sub.?account.*welcome/i', $class . ' ' . $title ) ) {
				return $class;
			}
		}
		return 'MeprUserWelcomeEmail';
	}

	/* --------------------------------------------------------------------
	 * Log
	 * ------------------------------------------------------------------ */

	private static function log( $user_id, $message, $source ) {
		$log = get_option( self::LOG_OPTION, array() );
		$user = get_userdata( $user_id );
		array_unshift(
			$log,
			array(
				'time'    => current_time( 'mysql' ),
				'user'    => $user ? $user->user_email : "#{$user_id}",
				'message' => $message,
				'source'  => $source,
			)
		);
		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_MAX ), false );
	}

	/* --------------------------------------------------------------------
	 * Admin: Settings → Mottaker-velkomst
	 * ------------------------------------------------------------------ */

	public static function admin_menu() {
		add_options_page( 'Mottaker-velkomst', 'Mottaker-velkomst', 'manage_options', 'senioruni-mw', array( __CLASS__, 'render_page' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$templates = self::available_templates();
		$current   = self::get_template();
		$log       = get_option( self::LOG_OPTION, array() );
		$notice    = isset( $_GET['mw_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['mw_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1>Mottaker-velkomst</h1>
			<p>Sends a welcome email to a MemberPress sub-account (the Mottaker on a Familie purchase) once, right after they set their password. The wording comes from the selected MemberPress template and is edited in <strong>MemberPress → Settings → Emails</strong>.</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $templates ) : ?>
				<div class="notice notice-error"><p>No MemberPress email templates found. Is MemberPress active?</p></div>
			<?php endif; ?>

			<h2>Template</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="senioruni_mw_save">
				<?php wp_nonce_field( 'senioruni_mw_save' ); ?>
				<select name="template">
					<?php foreach ( $templates as $class => $title ) : ?>
						<option value="<?php echo esc_attr( $class ); ?>" <?php selected( $current, $class ); ?>>
							<?php echo esc_html( $title . ( self::PRODUCT_TPL === $class ? '' : " ({$class})" ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( 'Save', 'primary', 'submit', false ); ?>
			</form>
			<p class="description">Make sure this is a <em>welcome</em> template and not the one that sends the "Set your password" link — use the test below to check.</p>

			<h2>Send a test</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="senioruni_mw_test">
				<?php wp_nonce_field( 'senioruni_mw_test' ); ?>
				<p>
					<label>Build the email for this sub-account (email address):<br>
						<input type="email" name="user_email" class="regular-text" required></label>
				</p>
				<p>
					<label>…and send it to:<br>
						<input type="email" name="to" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required></label>
				</p>
				<p class="description">Does not mark the user as welcomed.</p>
				<?php submit_button( 'Send test', 'secondary', 'submit', false ); ?>
			</form>

			<h2>Recent activity</h2>
			<?php if ( ! $log ) : ?>
				<p>Nothing yet.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th>Time</th><th>User</th><th>Result</th><th>Hook</th></tr></thead>
					<tbody>
					<?php foreach ( $log as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['time'] ); ?></td>
							<td><?php echo esc_html( $row['user'] ); ?></td>
							<td><?php echo esc_html( $row['message'] ); ?></td>
							<td><?php echo esc_html( $row['source'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'senioruni_mw_save' );
		$template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		if ( array_key_exists( $template, self::available_templates() ) ) {
			update_option( self::OPTION, $template, false );
			$msg = 'Saved.';
		} else {
			$msg = 'Unknown template, not saved.';
		}
		self::redirect( $msg );
	}

	public static function handle_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'senioruni_mw_test' );
		$user = get_user_by( 'email', sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) ) );
		$to   = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );

		if ( ! $user ) {
			self::redirect( 'No user with that email.' );
		}
		$note   = self::is_sub_account( $user->ID ) ? '' : ' (note: this user is NOT a sub-account)';
		$result = self::send_welcome( $user->ID, self::get_template(), $to );
		self::redirect( true === $result ? "Test sent to {$to}{$note}." : "Test failed: {$result}{$note}" );
	}

	private static function redirect( $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'senioruni-mw', 'mw_notice' => rawurlencode( $msg ) ), admin_url( 'options-general.php' ) ) );
		exit;
	}
}

SeniorUni_Mottaker_Welcome::init();

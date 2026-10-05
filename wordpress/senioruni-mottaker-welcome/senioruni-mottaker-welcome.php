<?php
/**
 * Plugin Name: SeniorUni – Mottaker-velkomst
 * Description: Familie purchases: sends the Mottaker's set-password email from a MemberPress template instead of the English one and their welcome email only after the password is set. New members are added to Mailchimp and get a welcome SMS, and member phone numbers are copied to the field Vipps/SMS login uses. Does not modify MemberPress, Corporate Accounts or the Vipps plugin.
 * Version:     1.2.1
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
	const LOG_MAX      = 100;
	const PRODUCT_TPL  = '__product_welcome__';

	/** Template for the set-password email; '' = auto-detect Sub Account Welcome Email, 'off' = keep the English one. */
	const OPTION_SETPW = 'senioruni_mw_setpw_template';
	const SETPW_OFF    = 'off';

	/** MemberPress checkout phone field, and the field the Vipps/SMS login (SeniorUni Trygg) looks users up by. */
	const PHONE_SOURCE_META = 'mepr_mobilnummer';
	const PHONE_LOGIN_META  = 'suit_mobile';
	const META_PHONE_SYNCED = '_senioruni_mw_phone_synced';

	/** On a new purchase: Mailchimp + welcome SMS, once per member. */
	const OPTION_ONBOARD = 'senioruni_mw_onboard';
	const META_ONBOARDED = '_senioruni_mw_onboarded';
	const CRON_ONBOARD   = 'senioruni_mw_onboard_user';
	const SMS_OWNER_DEFAULT    = 'Hei {navn}! Velkommen til SeniorUni. Du kan logge inn med Vipps her: {lenke}';
	const SMS_MOTTAKER_DEFAULT = 'Hei {navn}! {kjøper} har gitt deg medlemskap i SeniorUni. Du kan logge inn med Vipps her: {lenke}';

	/** Template variable names the reset link is offered under. */
	const RESET_LINK_VARS = array( 'reset_password_link', 'reset_password_url', 'password_reset_link', 'password_reset_url', 'set_password_link', 'set_password_url', 'reset_link' );

	/** Users handled during this request, so the two password hooks can't double-send. */
	private static $handled = array();

	/** True while this plugin is sending mail, so pre_wp_mail doesn't intercept its own emails. */
	private static $sending = false;

	/** Password-link variables (name => URL) for the email being sent, also replaced when URL-encoded. */
	private static $link_vars = array();

	/** Set when an onboarding job was scheduled in this request, so cron is kicked off at shutdown. */
	private static $kick_cron = false;

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

		// The "Auto MPCA" Code Snippet sends a hardcoded English set-password email; swap it for a MemberPress template.
		add_filter( 'pre_wp_mail', array( __CLASS__, 'maybe_replace_set_password_mail' ), 10, 2 );

		// Copy the checkout phone number to the field Vipps/SMS login looks users up by.
		add_action( 'added_user_meta', array( __CLASS__, 'on_user_meta' ), 10, 4 );
		add_action( 'updated_user_meta', array( __CLASS__, 'on_user_meta' ), 10, 4 );

		// New purchase: add to Mailchimp and send the welcome SMS, in the background like SeniorUni Trygg does.
		add_action( 'mepr-event-transaction-completed', array( __CLASS__, 'on_transaction_completed' ), 10, 1 );
		add_action( self::CRON_ONBOARD, array( __CLASS__, 'onboard' ), 10, 1 );
		add_action( 'shutdown', array( __CLASS__, 'maybe_kick_cron' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
			add_action( 'admin_post_senioruni_mw_save', array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_senioruni_mw_test', array( __CLASS__, 'handle_test' ) );
			add_action( 'admin_post_senioruni_mw_sync_phones', array( __CLASS__, 'handle_sync_phones' ) );
			add_action( 'admin_post_senioruni_mw_save_onboard', array( __CLASS__, 'handle_save_onboard' ) );
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
		return self::send_template( $user_id, $template, $to_override );
	}

	/**
	 * Send a MemberPress email template to a user.
	 *
	 * @param callable|null $finalize_params Called with the built params right before sending; returns the final params.
	 * @return true|string True on success, otherwise a reason string.
	 */
	public static function send_template( $user_id, $template, $to_override = '', $finalize_params = null ) {
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

			// {$corporate_name} in the Corporate Accounts templates: the purchaser who set up the membership.
			if ( empty( $params['corporate_name'] ) ) {
				$params['corporate_name'] = self::parent_name( $user_id );
			}

			$params = apply_filters( 'senioruni_mw_email_params', $params, $user_id, $txn, $template );
			if ( $finalize_params ) {
				$params = call_user_func( $finalize_params, $params, $email );
			}

			$email->to     = $to_override ? $to_override : $usr->formatted_email();
			self::$sending = true;
			add_filter( 'wp_mail', array( __CLASS__, 'fill_encoded_link_vars' ) );
			$email->send( $params );
		} catch ( \Throwable $e ) {
			return get_class( $e ) . ': ' . $e->getMessage();
		} finally {
			self::$sending   = false;
			self::$link_vars = array();
			remove_filter( 'wp_mail', array( __CLASS__, 'fill_encoded_link_vars' ) );
		}

		return true;
	}

	/**
	 * A {$var} inside a link may reach the email URL-encoded (%7B$var%7D), which MemberPress may not
	 * replace: fill in the password link there too.
	 */
	public static function fill_encoded_link_vars( $atts ) {
		if ( ! self::$link_vars || ! isset( $atts['message'] ) || ! is_string( $atts['message'] ) ) {
			return $atts;
		}
		foreach ( self::$link_vars as $var => $url ) {
			$atts['message'] = str_ireplace(
				array( '%7B$' . $var . '%7D', '%7B%24' . $var . '%7D', '{$' . $var . '}' ),
				esc_url( $url ),
				$atts['message']
			);
		}
		return $atts;
	}

	/**
	 * Name of the purchaser (corporate account owner) a sub-account belongs to.
	 */
	private static function parent_name( $user_id ) {
		$parent_id = (int) get_user_meta( $user_id, 'su_mpca_parent_user_id', true );

		if ( ! $parent_id && class_exists( 'MPCA_Corporate_Account' ) ) {
			$ca_id = (int) get_user_meta( $user_id, 'mpca_corporate_account_id', true );
			if ( $ca_id ) {
				try {
					$ca        = new MPCA_Corporate_Account( $ca_id );
					$parent_id = ! empty( $ca->user_id ) ? (int) $ca->user_id : 0;
				} catch ( \Throwable $e ) {
					$parent_id = 0;
				}
			}
		}

		$parent = $parent_id ? get_userdata( $parent_id ) : null;
		if ( ! $parent ) {
			return '';
		}
		$name = trim( $parent->first_name . ' ' . $parent->last_name );
		return $name ? $name : $parent->display_name;
	}

	/* --------------------------------------------------------------------
	 * Set-password email: MemberPress template instead of the English one
	 * ------------------------------------------------------------------ */

	/**
	 * Intercepts the English "[SeniorUni] Set your password" email sent by the
	 * Auto MPCA Code Snippet and sends the chosen MemberPress template instead.
	 * If the template can't be sent, the English email goes out as before.
	 */
	public static function maybe_replace_set_password_mail( $return, $atts ) {
		if ( null !== $return || self::$sending ) {
			return $return;
		}

		$subject = isset( $atts['subject'] ) ? (string) $atts['subject'] : '';
		$message = isset( $atts['message'] ) && is_string( $atts['message'] ) ? $atts['message'] : '';
		if ( ! preg_match( '/Set your password\s*$/i', $subject ) || ! preg_match( '#(https?://\S*action=rp\S*)#', $message, $m ) ) {
			return $return;
		}
		$english_url = $m[1];

		$to   = isset( $atts['to'] ) ? $atts['to'] : '';
		$to   = is_array( $to ) ? reset( $to ) : $to;
		$user = get_user_by( 'email', trim( (string) $to ) );
		if ( ! $user || ! self::is_sub_account( $user->ID ) ) {
			return $return;
		}

		$template = self::get_setpw_template();
		if ( self::SETPW_OFF === $template ) {
			return $return;
		}

		$new_url = '';
		$result  = self::send_set_password( $user, $template, '', $english_url, $new_url );

		if ( true === $result ) {
			self::log( $user->ID, 'set-password email sent (MemberPress template)', 'set-password' );
			return true;
		}

		self::log( $user->ID, 'failed: ' . $result . ' (English set-password email sent instead)', 'set-password' );

		// If a new reset key was issued along the way, the link in the English email no longer works: use a working one.
		$fallback_url = $english_url;
		if ( ! self::reset_url_is_valid( $english_url, $user ) ) {
			try {
				$fallback_url = $new_url && self::reset_url_is_valid( $new_url, $user ) ? $new_url : self::new_reset_url( $user );
			} catch ( \Throwable $e ) {
				$fallback_url = $english_url;
			}
		}
		if ( $fallback_url === $english_url ) {
			return $return;
		}

		self::$sending = true;
		$sent          = wp_mail( $atts['to'], $subject, str_replace( $english_url, $fallback_url, $message ), $atts['headers'], $atts['attachments'] );
		self::$sending = false;
		return $sent;
	}

	/**
	 * @param string $existing_url A reset link that is already valid (from the English email), reused if still valid.
	 * @param string $used_url     Out: the reset link put in the email.
	 * @return true|string
	 */
	public static function send_set_password( $user, $template, $to_override = '', $existing_url = '', &$used_url = '' ) {
		$finalize = function ( $params, $email ) use ( $user, $existing_url, &$used_url ) {
			$in_template = self::reset_vars_in_template( $email );
			if ( array() === $in_template ) {
				// Without a personal link the Mottaker could not set a password: let the English email go instead.
				throw new RuntimeException( 'the template has no password link variable such as {$reset_password_link}' );
			}

			// Building the params may itself issue a new reset key, so only reuse the existing link if its key still works.
			$url = $existing_url && self::reset_url_is_valid( $existing_url, $user ) ? $existing_url : self::new_reset_url( $user );
			$used_url = $url;
			foreach ( array_merge( self::RESET_LINK_VARS, (array) $in_template ) as $var ) {
				$params[ $var ]          = $url;
				self::$link_vars[ $var ] = $url;
			}
			return $params;
		};

		return self::send_template( $user->ID, $template, $to_override, $finalize );
	}

	/**
	 * Variables in the template body that look like a password link ({$..reset..} / {$..password..}),
	 * so the link works whatever the template calls it.
	 *
	 * @return array|null Variable names, or null if the template body can't be read.
	 */
	private static function reset_vars_in_template( $email ) {
		$body = '';
		try {
			if ( is_object( $email ) && method_exists( $email, 'body' ) ) {
				$body = (string) $email->body();
			}
		} catch ( \Throwable $e ) {
			$body = '';
		}
		if ( '' === trim( $body ) ) {
			return null;
		}
		// The editor may store {$var} inside a link as %7B$var%7D.
		if ( ! preg_match_all( '/(?:\{|%7B)(?:\$|%24)([a-z0-9_]+)(?:\}|%7D)/i', $body, $m ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( $m[1], function ( $var ) {
			return (bool) preg_match( '/reset|password|passord/i', $var );
		} ) ) );
	}

	private static function reset_url_is_valid( $url, $user ) {
		parse_str( (string) wp_parse_url( html_entity_decode( $url ), PHP_URL_QUERY ), $q );
		if ( empty( $q['key'] ) ) {
			return false;
		}
		return ! is_wp_error( check_password_reset_key( $q['key'], $user->user_login ) );
	}

	private static function new_reset_url( $user ) {
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			throw new RuntimeException( 'could not create reset key: ' . $key->get_error_message() );
		}
		return network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
	}

	public static function get_setpw_template() {
		$saved = get_option( self::OPTION_SETPW, '' );
		if ( $saved ) {
			return $saved;
		}
		foreach ( self::available_templates() as $class => $title ) {
			if ( preg_match( '/sub.?account.*welcome/i', $class . ' ' . $title ) ) {
				return $class;
			}
		}
		return self::SETPW_OFF;
	}

	/* --------------------------------------------------------------------
	 * Phone number for Vipps / SMS login
	 * ------------------------------------------------------------------ */

	public static function on_user_meta( $meta_id, $user_id, $meta_key, $meta_value ) {
		if ( self::PHONE_SOURCE_META === $meta_key ) {
			self::sync_phone( (int) $user_id, $meta_value );
		}
		// The Auto MPCA snippet writes this last, once the Mottaker is attached to the purchaser.
		if ( 'su_mpca_parent_transaction_id' === $meta_key ) {
			self::schedule_onboard( (int) $user_id );
		}
	}

	/**
	 * Norwegian mobile number as 0047XXXXXXXX (the format SeniorUni Trygg stores), or '' if not recognisable.
	 */
	public static function normalize_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );
		if ( 12 === strlen( $digits ) && 0 === strpos( $digits, '0047' ) ) {
			return $digits;
		}
		if ( 10 === strlen( $digits ) && 0 === strpos( $digits, '47' ) ) {
			return '00' . $digits;
		}
		if ( 8 === strlen( $digits ) ) {
			return '0047' . $digits;
		}
		return '';
	}

	/**
	 * Copy the checkout phone to suit_mobile, unless a number from somewhere else (e.g. a Vipps
	 * purchase) is already there, or another user already logs in with this number.
	 *
	 * @return string copied|unchanged|invalid|kept|shared
	 */
	public static function sync_phone( $user_id, $raw ) {
		$phone = self::normalize_phone( is_scalar( $raw ) ? $raw : '' );
		if ( ! $phone ) {
			return 'invalid';
		}

		$current = (string) get_user_meta( $user_id, self::PHONE_LOGIN_META, true );
		if ( self::normalize_phone( $current ) === $phone ) {
			return 'unchanged';
		}
		if ( '' !== $current && get_user_meta( $user_id, self::META_PHONE_SYNCED, true ) !== $current ) {
			return 'kept';
		}

		$owner = self::phone_owner( $phone, $user_id );
		if ( $owner ) {
			self::log( $user_id, "phone {$phone} not copied: already used by user #{$owner}", 'phone' );
			return 'shared';
		}

		update_user_meta( $user_id, self::PHONE_LOGIN_META, $phone );
		update_user_meta( $user_id, self::META_PHONE_SYNCED, $phone );
		return 'copied';
	}

	private static function phone_owner( $phone, $exclude_user_id ) {
		global $wpdb;
		$local    = substr( $phone, -8 );
		$variants = array( $phone, '+47' . $local, '47' . $local, $local );
		$in       = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
		$args     = array_merge( array( self::PHONE_LOGIN_META ), $variants, array( $exclude_user_id ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value IN ($in) AND user_id <> %d LIMIT 1", $args )
		);
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

	/* --------------------------------------------------------------------
	 * New purchase: Mailchimp + welcome SMS
	 * ------------------------------------------------------------------ */

	public static function onboard_settings() {
		$saved = get_option( self::OPTION_ONBOARD, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array_merge(
			array(
				'mailchimp'    => true,
				'sms'          => true,
				'sms_owner'    => self::SMS_OWNER_DEFAULT,
				'sms_mottaker' => self::SMS_MOTTAKER_DEFAULT,
				'products'     => array(), // empty = all memberships
			),
			$saved
		);
	}

	/**
	 * Purchaser or one-person member: a completed MemberPress transaction.
	 * The Mottaker is picked up from the sub-account transaction, or from the snippet's user meta.
	 */
	public static function on_transaction_completed( $event ) {
		$txn = ( is_object( $event ) && method_exists( $event, 'get_data' ) ) ? $event->get_data() : $event;
		if ( ! is_object( $txn ) || empty( $txn->user_id ) ) {
			return;
		}

		if ( isset( $txn->txn_type ) && 'sub_account' === $txn->txn_type ) {
			self::schedule_onboard( (int) $txn->user_id );
			return;
		}

		// Manual transactions: added by an admin, or by SeniorUni Trygg for Vipps purchases
		// (Trygg sends its own SMS and Mailchimp for those).
		if ( ( isset( $txn->gateway ) && 'manual' === $txn->gateway ) || 0 === strpos( (string) ( $txn->trans_num ?? '' ), 'suit-' ) ) {
			return;
		}

		$products = array_map( 'intval', (array) self::onboard_settings()['products'] );
		if ( $products && ! in_array( (int) ( $txn->product_id ?? 0 ), $products, true ) ) {
			return;
		}

		// Renewals and returning members are not new members.
		if ( self::has_earlier_transaction( (int) $txn->user_id, (int) ( $txn->id ?? 0 ) ) ) {
			return;
		}

		self::schedule_onboard( (int) $txn->user_id );
	}

	private static function has_earlier_transaction( $user_id, $txn_id ) {
		if ( ! class_exists( 'MeprDb' ) ) {
			return false;
		}
		global $wpdb;
		$mepr_db = new MeprDb();
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$mepr_db->transactions}
				 WHERE user_id = %d AND id <> %d
				 AND status IN ('complete','confirmed')
				 AND txn_type NOT IN ('subscription_confirmation','sub_account')",
				$user_id,
				$txn_id
			)
		);
	}

	public static function schedule_onboard( $user_id ) {
		if ( $user_id <= 0 || get_user_meta( $user_id, self::META_ONBOARDED, true ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_ONBOARD, array( $user_id ) ) ) {
			// Cron is only started at shutdown, so everything this request saves (phone, Mottaker details) is in place by then.
			wp_schedule_single_event( time(), self::CRON_ONBOARD, array( $user_id ) );
		}
		self::$kick_cron = true;
	}

	/**
	 * Start wp-cron right away instead of waiting for the next visitor (same approach as SeniorUni Trygg).
	 */
	public static function maybe_kick_cron() {
		if ( ! self::$kick_cron ) {
			return;
		}
		self::$kick_cron = false;
		wp_remote_post(
			site_url( 'wp-cron.php?doing_wp_cron=' . sprintf( '%.22F', microtime( true ) ) ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}

	/**
	 * Add a new member to Mailchimp and send their welcome SMS. Runs once per member.
	 */
	public static function onboard( $user_id ) {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );
		if ( ! $user || get_user_meta( $user_id, self::META_ONBOARDED, true ) || user_can( $user_id, 'manage_options' ) ) {
			return;
		}
		update_user_meta( $user_id, self::META_ONBOARDED, time() );

		$is_sub   = self::is_sub_account( $user_id );
		$settings = self::onboard_settings();
		$results  = array();

		if ( $settings['mailchimp'] ) {
			try {
				$results[] = 'Mailchimp: ' . self::mailchimp_add( $user );
			} catch ( \Throwable $e ) {
				$results[] = 'Mailchimp: failed (' . $e->getMessage() . ')';
			}
		}
		if ( $settings['sms'] ) {
			try {
				$results[] = 'SMS: ' . self::send_welcome_sms( $user, $is_sub, $settings );
			} catch ( \Throwable $e ) {
				$results[] = 'SMS: failed (' . $e->getMessage() . ')';
			}
		}

		if ( $results ) {
			self::log( $user_id, implode( '; ', $results ), $is_sub ? 'new Mottaker' : 'new member' );
		}
	}

	/**
	 * Adds or updates the member in the Mailchimp audience set up in SeniorUni Trygg, without tags.
	 * Someone who has unsubscribed before stays unsubscribed.
	 */
	public static function mailchimp_add( $user ) {
		$api_key = (string) get_option( 'suit_mailchimp_api_key', '' );
		$list_id = (string) get_option( 'suit_mailchimp_list_id', '' );
		$dc      = false !== strpos( $api_key, '-' ) ? substr( $api_key, strrpos( $api_key, '-' ) + 1 ) : '';
		if ( '' === $list_id || '' === $dc ) {
			return 'skipped (Mailchimp API key or audience not set in SeniorUni Trygg)';
		}

		$email    = strtolower( $user->user_email );
		$response = wp_remote_request(
			"https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/" . md5( $email ),
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ),
				),
				'body'    => wp_json_encode(
					array(
						'email_address' => $email,
						'status_if_new' => 'subscribed',
						'merge_fields'  => array(
							'FNAME' => (string) $user->first_name,
							'LNAME' => (string) $user->last_name,
						),
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'failed (' . $response->get_error_message() . ')';
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return 'added';
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return 'failed (HTTP ' . $code . ( ! empty( $body['detail'] ) ? ': ' . $body['detail'] : '' ) . ')';
	}

	/**
	 * Welcome SMS through SeniorUni Trygg's Pling account, with the Vipps login link.
	 */
	public static function send_welcome_sms( $user, $is_sub, $settings ) {
		$phone = self::normalize_phone( get_user_meta( $user->ID, self::PHONE_SOURCE_META, true ) );
		if ( ! $phone ) {
			return 'skipped (no Norwegian mobile number)';
		}
		if ( $is_sub && self::phone_owner( $phone, $user->ID ) ) {
			// Shares the purchaser's number: Vipps login with that number opens the purchaser's account.
			return 'skipped (same number as another member)';
		}
		if ( ! class_exists( 'SUIT_Pling' ) ) {
			return 'skipped (SeniorUni Trygg is not active)';
		}

		$buyer   = $is_sub ? self::parent_name( $user->ID ) : '';
		$message = strtr(
			$is_sub ? $settings['sms_mottaker'] : $settings['sms_owner'],
			array(
				'{navn}'   => $user->first_name ? $user->first_name : $user->display_name,
				'{kjøper}' => $buyer ? $buyer : 'Noen',
				'{lenke}'  => home_url( '/vipps-login/' ),
			)
		);

		return SUIT_Pling::send_sms( $phone, $message, 'senioruni-mw-' . $user->ID ) ? 'sent to ' . $phone : 'failed (see SeniorUni Trygg log)';
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
	 * Saved choice, or the membership-specific welcome (the Familie welcome email).
	 * Never defaults to the Sub Account Welcome Email: that one asks the Mottaker to create a password.
	 */
	public static function get_template() {
		$saved = get_option( self::OPTION, '' );
		if ( $saved ) {
			return $saved;
		}
		return class_exists( 'MeprUserProductWelcomeEmail' ) ? self::PRODUCT_TPL : 'MeprUserWelcomeEmail';
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
		$setpw     = self::get_setpw_template();
		$log       = get_option( self::LOG_OPTION, array() );
		$notice    = isset( $_GET['mw_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['mw_notice'] ) ) : '';
		$label     = function ( $class, $title ) {
			return $title . ( self::PRODUCT_TPL === $class ? '' : " ({$class})" );
		};
		?>
		<div class="wrap">
			<h1>Mottaker-velkomst</h1>
			<p>On a Familie purchase the Mottaker (a MemberPress sub-account) first gets a set-password email, then a welcome email once they have set their password. Both texts come from MemberPress templates and are edited in <strong>MemberPress → Settings → Emails</strong> (or on the membership, for a membership-specific welcome).</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $templates ) : ?>
				<div class="notice notice-error"><p>No MemberPress email templates found. Is MemberPress active?</p></div>
			<?php endif; ?>

			<h2>Templates</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="senioruni_mw_save">
				<?php wp_nonce_field( 'senioruni_mw_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">1. Set-password email</th>
						<td>
							<select name="setpw_template">
								<option value="<?php echo esc_attr( self::SETPW_OFF ); ?>" <?php selected( $setpw, self::SETPW_OFF ); ?>>Off: keep the English "[SeniorUni] Set your password" email</option>
								<?php foreach ( $templates as $class => $title ) : ?>
									<?php if ( self::PRODUCT_TPL === $class ) { continue; } ?>
									<option value="<?php echo esc_attr( $class ); ?>" <?php selected( $setpw, $class ); ?>><?php echo esc_html( $label( $class, $title ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Sent right after the purchase, instead of the English email. Must contain the password link, e.g. Sub Account Welcome Email.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">2. Welcome email</th>
						<td>
							<select name="template">
								<?php foreach ( $templates as $class => $title ) : ?>
									<option value="<?php echo esc_attr( $class ); ?>" <?php selected( $current, $class ); ?>><?php echo esc_html( $label( $class, $title ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Sent once, after the Mottaker has set their password. Must <em>not</em> ask them to set a password.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save' ); ?>
			</form>

			<h2>Send a test</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="senioruni_mw_test">
				<?php wp_nonce_field( 'senioruni_mw_test' ); ?>
				<p>
					<label><input type="radio" name="which" value="welcome" checked> Welcome email</label>&nbsp;&nbsp;
					<label><input type="radio" name="which" value="setpw"> Set-password email</label>
				</p>
				<p>
					<label>Build the email for this sub-account (email address):<br>
						<input type="email" name="user_email" class="regular-text" required></label>
				</p>
				<p>
					<label>…and send it to:<br>
						<input type="email" name="to" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required></label>
				</p>
				<p class="description">Does not mark the user as welcomed. Testing the set-password email creates a new password link for that user, so their earlier link stops working: use a test user.</p>
				<?php submit_button( 'Send test', 'secondary', 'submit', false ); ?>
			</form>

			<h2>New members: Mailchimp and welcome SMS</h2>
			<?php
			$ob       = self::onboard_settings();
			$mc_ready = get_option( 'suit_mailchimp_api_key' ) && get_option( 'suit_mailchimp_list_id' );
			$sms_ready = class_exists( 'SUIT_Pling' ) && get_option( 'suit_pling_service_id' );
			$products = get_posts( array( 'post_type' => 'memberpressproduct', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
			?>
			<p>Runs once for each new member who buys a membership with card (MemberPress checkout), and for the Mottaker on a Familie purchase. Renewals and Vipps purchases (handled by SeniorUni Trygg) are skipped. Uses the Mailchimp audience and the Pling SMS account set up in SeniorUni Trygg.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="senioruni_mw_save_onboard">
				<?php wp_nonce_field( 'senioruni_mw_save_onboard' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Mailchimp</th>
						<td>
							<label><input type="checkbox" name="mailchimp" value="1" <?php checked( $ob['mailchimp'] ); ?>> Add new members to Mailchimp (no tags)</label>
							<?php if ( ! $mc_ready ) : ?><p class="description" style="color:#b32d2e;">Mailchimp API key or audience is not set in SeniorUni Trygg.</p><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Welcome SMS</th>
						<td>
							<label><input type="checkbox" name="sms" value="1" <?php checked( $ob['sms'] ); ?>> Send a welcome SMS to new members</label>
							<?php if ( ! $sms_ready ) : ?><p class="description" style="color:#b32d2e;">SeniorUni Trygg is not active, or Pling is not set up there.</p><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mw-sms-owner">SMS to the buyer</label></th>
						<td>
							<textarea id="mw-sms-owner" name="sms_owner" rows="3" class="large-text"><?php echo esc_textarea( $ob['sms_owner'] ); ?></textarea>
							<p class="description">One-person purchase, and the buyer on a Familie purchase. <code>{navn}</code> = first name, <code>{lenke}</code> = Vipps login link.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mw-sms-mottaker">SMS to the Mottaker</label></th>
						<td>
							<textarea id="mw-sms-mottaker" name="sms_mottaker" rows="3" class="large-text"><?php echo esc_textarea( $ob['sms_mottaker'] ); ?></textarea>
							<p class="description">Second person on a Familie purchase. <code>{navn}</code> = their first name, <code>{kjøper}</code> = the buyer's name, <code>{lenke}</code> = Vipps login link. Not sent if they gave the same number as the buyer.</p>
						</td>
					</tr>
					<?php if ( $products ) : ?>
					<tr>
						<th scope="row">Memberships</th>
						<td>
							<?php foreach ( $products as $product ) : ?>
								<label style="display:block;"><input type="checkbox" name="products[]" value="<?php echo esc_attr( $product->ID ); ?>" <?php checked( in_array( (int) $product->ID, array_map( 'intval', (array) $ob['products'] ), true ) ); ?>> <?php echo esc_html( $product->post_title ); ?> (#<?php echo esc_html( $product->ID ); ?>)</label>
							<?php endforeach; ?>
							<p class="description">Only purchases of the ticked memberships count. None ticked = all memberships.</p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<?php submit_button( 'Save' ); ?>
			</form>

			<h2>Phone numbers for Vipps / SMS login</h2>
			<p>When a member's <code><?php echo esc_html( self::PHONE_SOURCE_META ); ?></code> (Mobilnummer) is saved, it is copied to <code><?php echo esc_html( self::PHONE_LOGIN_META ); ?></code>, the field Vipps and SMS login look members up by. A number another member already logs in with is not copied. Use the button once to copy the numbers of existing members.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="senioruni_mw_sync_phones">
				<?php wp_nonce_field( 'senioruni_mw_sync_phones' ); ?>
				<?php submit_button( 'Copy phone numbers for existing members', 'secondary', 'submit', false ); ?>
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
		$templates = self::available_templates();
		$template  = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		$setpw     = isset( $_POST['setpw_template'] ) ? sanitize_text_field( wp_unslash( $_POST['setpw_template'] ) ) : '';

		if ( ! array_key_exists( $template, $templates ) ) {
			self::redirect( 'Unknown welcome template, not saved.' );
		}
		if ( self::SETPW_OFF !== $setpw && ( self::PRODUCT_TPL === $setpw || ! array_key_exists( $setpw, $templates ) ) ) {
			self::redirect( 'Unknown set-password template, not saved.' );
		}
		update_option( self::OPTION, $template, false );
		update_option( self::OPTION_SETPW, $setpw, false );
		self::redirect( 'Saved.' );
	}

	public static function handle_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'senioruni_mw_test' );
		$user  = get_user_by( 'email', sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) ) );
		$to    = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
		$which = ( $_POST['which'] ?? '' ) === 'setpw' ? 'setpw' : 'welcome';

		if ( ! $user ) {
			self::redirect( 'No user with that email.' );
		}
		$note = self::is_sub_account( $user->ID ) ? '' : ' (note: this user is NOT a sub-account)';

		if ( 'setpw' === $which ) {
			$template = self::get_setpw_template();
			if ( self::SETPW_OFF === $template ) {
				self::redirect( 'The set-password template is Off, nothing to test.' );
			}
			$result = self::send_set_password( $user, $template, $to );
		} else {
			$result = self::send_welcome( $user->ID, self::get_template(), $to );
		}
		self::redirect( true === $result ? "Test sent to {$to}{$note}." : "Test failed: {$result}{$note}" );
	}

	public static function handle_save_onboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'senioruni_mw_save_onboard' );

		$owner    = sanitize_textarea_field( wp_unslash( $_POST['sms_owner'] ?? '' ) );
		$mottaker = sanitize_textarea_field( wp_unslash( $_POST['sms_mottaker'] ?? '' ) );
		update_option(
			self::OPTION_ONBOARD,
			array(
				'mailchimp'    => ! empty( $_POST['mailchimp'] ),
				'sms'          => ! empty( $_POST['sms'] ),
				'sms_owner'    => '' !== trim( $owner ) ? $owner : self::SMS_OWNER_DEFAULT,
				'sms_mottaker' => '' !== trim( $mottaker ) ? $mottaker : self::SMS_MOTTAKER_DEFAULT,
				'products'     => array_values( array_filter( array_map( 'absint', (array) ( $_POST['products'] ?? array() ) ) ) ),
			),
			false
		);
		self::redirect( 'Saved.' );
	}

	public static function handle_sync_phones() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'senioruni_mw_sync_phones' );

		global $wpdb;
		$rows   = $wpdb->get_results(
			$wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> '' ORDER BY user_id ASC", self::PHONE_SOURCE_META )
		);
		$counts = array( 'copied' => 0, 'unchanged' => 0, 'invalid' => 0, 'kept' => 0, 'shared' => 0 );
		foreach ( $rows as $row ) {
			$counts[ self::sync_phone( (int) $row->user_id, $row->meta_value ) ]++;
		}
		self::redirect(
			sprintf(
				'Phone numbers: %d copied, %d already set, %d kept (another number already there, e.g. from a Vipps purchase), %d skipped (number used by another member), %d not a Norwegian mobile number.',
				$counts['copied'],
				$counts['unchanged'],
				$counts['kept'],
				$counts['shared'],
				$counts['invalid']
			)
		);
	}

	private static function redirect( $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'senioruni-mw', 'mw_notice' => rawurlencode( $msg ) ), admin_url( 'options-general.php' ) ) );
		exit;
	}
}

SeniorUni_Mottaker_Welcome::init();

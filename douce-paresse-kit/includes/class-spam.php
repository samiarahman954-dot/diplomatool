<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * Layered spam protection for the kit forms.
 *
 *  1. Honeypot field                         (bots fill every field)
 *  2. Signed, single-use JS token + time trap (bots that post directly / too fast)
 *  3. Cloudflare Turnstile                   (invisible / managed challenge)
 *  4. Rate limits per IP and per e-mail      (real visitor IP behind Cloudflare proxy or Tunnel)
 *  5. Content rules: links, BBCode/HTML, blocked words, WordPress "Disallowed comment keys",
 *     foreign-script ratio, length limits
 *  6. E-mail rules: disposable domains, domain without MX/A record
 *  7. Akismet (when the Akismet plugin is active and connected)
 *  8. Duplicate detection
 *
 * "spam"  = silently accepted (the bot learns nothing) and logged under Messages.
 * "error" = shown to the visitor (a human can fix it: wait, solve the challenge, fix the e-mail…).
 */
final class Spam {

	const OPTION = 'dpk_spam';
	const PAGE   = 'dpk-spam';

	/** @var Spam|null */
	private static $instance = null;

	/** Cloudflare published edge ranges (https://www.cloudflare.com/ips/). */
	const CF_RANGES = array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
		'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
		'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
		'2a06:98c0::/29', '2c0f:f248::/32',
	);

	const DISPOSABLE = array(
		'mailinator.com', 'guerrillamail.com', 'guerrillamail.info', 'sharklasers.com', 'grr.la', '10minutemail.com',
		'10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmailo.com', 'throwawaymail.com',
		'yopmail.com', 'yopmail.fr', 'yopmail.net', 'trashmail.com', 'trashmail.de', 'getnada.com', 'nada.email',
		'dispostable.com', 'maildrop.cc', 'mailnesia.com', 'mintemail.com', 'mohmal.com', 'emailondeck.com',
		'fakeinbox.com', 'spamgourmet.com', 'mytemp.email', 'tempinbox.com', 'burnermail.io', 'mailpoof.com',
		'tmail.ws', 'tmpmail.org', 'tmpmail.net', 'moakt.com', 'inboxkitten.com', 'byom.de', 'jetable.org',
		'cool.fr.nf', 'courriel.fr.nf', 'speed.1s.fr', 'mail-temp.com', 'discard.email', 'spambox.us',
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_dpk_form_token', array( $this, 'ajax_token' ) );
		add_action( 'wp_ajax_nopriv_dpk_form_token', array( $this, 'ajax_token' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'dpk_purge_spam', array( $this, 'purge' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'flush_elementor' ) );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'turnstile'       => '',
			'ts_site'         => '',
			'ts_secret'       => '',
			'ts_theme'        => 'light',
			'ts_appearance'   => 'interaction-only',
			'min_seconds'     => 4,
			'max_age_hours'   => 12,
			'rate_ip'         => 5,
			'rate_email'      => 3,
			'max_links'       => 2,
			'block_words'     => "viagra\ncialis\ncasino\nbitcoin\ncrypto\nforex\nloan\nseo service\nbacklink\nguest post\nporn\nsex\nescort\nrank your website\nfirst page of google\nweb design services\nlead generation",
			'use_wp_list'     => 'yes',
			'foreign_scripts' => 'yes',
			'disposable'      => 'yes',
			'check_mx'        => 'yes',
			'akismet'         => 'yes',
			'duplicates'      => 'yes',
			'cloudflare_ip'   => 'auto',
			'keep_spam'       => 'yes',
		);
	}

	public static function get() {
		$opt = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $opt ) ? $opt : array(), self::defaults() );
	}

	public static function turnstile_enabled() {
		$o = self::get();
		return 'yes' === $o['turnstile'] && '' !== $o['ts_site'] && '' !== $o['ts_secret'];
	}

	public function register_setting() {
		register_setting(
			'dpk_spam_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$d   = self::defaults();
		$out = array();
		foreach ( array( 'turnstile', 'use_wp_list', 'foreign_scripts', 'disposable', 'check_mx', 'akismet', 'duplicates', 'keep_spam' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? '' : 'yes';
		}
		$out['ts_site']       = sanitize_text_field( isset( $in['ts_site'] ) ? $in['ts_site'] : '' );
		$out['ts_secret']     = sanitize_text_field( isset( $in['ts_secret'] ) ? $in['ts_secret'] : '' );
		$out['ts_theme']      = isset( $in['ts_theme'] ) && in_array( $in['ts_theme'], array( 'light', 'dark', 'auto' ), true ) ? $in['ts_theme'] : $d['ts_theme'];
		$out['ts_appearance'] = isset( $in['ts_appearance'] ) && in_array( $in['ts_appearance'], array( 'always', 'interaction-only' ), true ) ? $in['ts_appearance'] : $d['ts_appearance'];
		$out['cloudflare_ip'] = isset( $in['cloudflare_ip'] ) && in_array( $in['cloudflare_ip'], array( 'off', 'auto', 'always' ), true ) ? $in['cloudflare_ip'] : $d['cloudflare_ip'];
		$out['min_seconds']   = max( 0, min( 60, (int) ( isset( $in['min_seconds'] ) ? $in['min_seconds'] : $d['min_seconds'] ) ) );
		$out['max_age_hours'] = max( 1, min( 72, (int) ( isset( $in['max_age_hours'] ) ? $in['max_age_hours'] : $d['max_age_hours'] ) ) );
		$out['rate_ip']       = max( 0, min( 100, (int) ( isset( $in['rate_ip'] ) ? $in['rate_ip'] : $d['rate_ip'] ) ) );
		$out['rate_email']    = max( 0, min( 100, (int) ( isset( $in['rate_email'] ) ? $in['rate_email'] : $d['rate_email'] ) ) );
		$out['max_links']     = max( 0, min( 20, (int) ( isset( $in['max_links'] ) ? $in['max_links'] : $d['max_links'] ) ) );
		$out['block_words']   = sanitize_textarea_field( isset( $in['block_words'] ) ? $in['block_words'] : '' );
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Visitor IP (direct, Cloudflare proxy, Cloudflare Tunnel)
	 * ------------------------------------------------------------------ */

	public static function ip_in_range( $ip, $cidr ) {
		list( $subnet, $bits ) = array_pad( explode( '/', $cidr ), 2, null );
		$ip_bin  = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$net_bin = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
			return false;
		}
		$bits = null === $bits ? strlen( $ip_bin ) * 8 : (int) $bits;
		$full = intdiv( $bits, 8 );
		if ( substr( $ip_bin, 0, $full ) !== substr( $net_bin, 0, $full ) ) {
			return false;
		}
		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}
		$mask = chr( ( 0xff << ( 8 - $rest ) ) & 0xff );
		return ( $ip_bin[ $full ] & $mask ) === ( $net_bin[ $full ] & $mask );
	}

	public static function is_cloudflare( $ip ) {
		foreach ( self::CF_RANGES as $range ) {
			if ( self::ip_in_range( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	/** Loopback / private address: what PHP sees when the request arrives through cloudflared (Tunnel). */
	public static function is_local( $ip ) {
		foreach ( array( '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1/128', 'fc00::/7' ) as $range ) {
			if ( self::ip_in_range( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	public static function client_ip( $server = null ) {
		$server = null === $server ? $_SERVER : $server; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$remote = isset( $server['REMOTE_ADDR'] ) ? trim( (string) $server['REMOTE_ADDR'] ) : '';
		$cf     = isset( $server['HTTP_CF_CONNECTING_IP'] ) ? trim( (string) $server['HTTP_CF_CONNECTING_IP'] ) : '';
		$mode   = self::get()['cloudflare_ip'];
		if ( $cf && filter_var( $cf, FILTER_VALIDATE_IP ) && 'off' !== $mode ) {
			if ( 'always' === $mode || self::is_cloudflare( $remote ) || self::is_local( $remote ) ) {
				return $cf;
			}
		}
		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
	}

	/* ------------------------------------------------------------------
	 * Signed time token (works with page caching, unlike a nonce in the HTML)
	 * ------------------------------------------------------------------ */

	private static function sign( $time ) {
		return hash_hmac( 'sha256', 'dpk-form|' . $time, wp_salt( 'nonce' ) );
	}

	public static function issue_token( $time = null ) {
		$time = null === $time ? time() : (int) $time;
		return $time . '.' . self::sign( $time );
	}

	/**
	 * @return true|string true when valid, otherwise an error code (missing|invalid|fast|expired|used).
	 */
	public static function check_token( $token, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		$o   = self::get();
		if ( ! is_string( $token ) || ! preg_match( '/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m ) ) {
			return $token ? 'invalid' : 'missing';
		}
		if ( ! hash_equals( self::sign( $m[1] ), $m[2] ) ) {
			return 'invalid';
		}
		$age = $now - (int) $m[1];
		if ( $age < (int) $o['min_seconds'] ) {
			return 'fast';
		}
		if ( $age > (int) $o['max_age_hours'] * HOUR_IN_SECONDS ) {
			return 'expired';
		}
		if ( get_transient( 'dpk_tok_' . md5( $token ) ) ) {
			return 'used';
		}
		return true;
	}

	public static function burn_token( $token ) {
		set_transient( 'dpk_tok_' . md5( $token ), 1, (int) self::get()['max_age_hours'] * HOUR_IN_SECONDS );
	}

	public function ajax_token() {
		nocache_headers();
		wp_send_json_success(
			array(
				'token' => self::issue_token(),
				'min'   => (int) self::get()['min_seconds'],
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Checks
	 * ------------------------------------------------------------------ */

	private static function count_hit( $key, $window ) {
		$key  = 'dpk_rl_' . md5( $key );
		$data = get_transient( $key );
		$data = is_array( $data ) && $data['until'] > time() ? $data : array( 'n' => 0, 'until' => time() + $window );
		++$data['n'];
		set_transient( $key, $data, max( 1, $data['until'] - time() ) );
		return $data['n'];
	}

	public static function count_links( $text ) {
		$text = preg_replace( '/[^\s@]+@[^\s@]+/', ' ', (string) $text ); // e-mail addresses are not links.
		return preg_match_all( '~(https?://|www\.|\[url|<a\s|\b[a-z0-9-]+\.(com|net|org|ru|cn|info|biz|xyz|top|io|co|site|online|shop)\b)~i', $text );
	}

	/** Share of letters outside the Latin script (Cyrillic, CJK… spam on a French site). */
	public static function foreign_ratio( $text ) {
		$letters = preg_match_all( '/\p{L}/u', $text );
		if ( $letters < 20 ) {
			return 0;
		}
		return preg_match_all( '/[^\P{L}\p{Latin}]/u', $text ) / $letters;
	}

	public static function blocked_word( $text, $list ) {
		$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		foreach ( preg_split( '/\r\n|\r|\n|,/', (string) $list ) as $word ) {
			$word = trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( $word ) : strtolower( $word ) );
			if ( '' !== $word && preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $word, '/' ) . '(?![\p{L}\p{N}])/u', $hay ) ) {
				return $word;
			}
		}
		return '';
	}

	public static function verify_turnstile( $response, $ip ) {
		$o = self::get();
		if ( '' === (string) $response ) {
			return false;
		}
		$res = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $o['ts_secret'],
					'response' => $response,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		return ! empty( $body['success'] );
	}

	private static function akismet_says_spam( $values, $email, $ip ) {
		if ( ! class_exists( '\Akismet' ) || ! method_exists( '\Akismet', 'get_api_key' ) || ! \Akismet::get_api_key() ) {
			return false;
		}
		$name = '';
		$text = array();
		foreach ( $values as $v ) {
			if ( '' === $name && in_array( $v['key'], array( 'nom', 'name', 'prenom' ), true ) ) {
				$name = $v['value'];
			}
			$text[] = $v['value'];
		}
		$data = array(
			'blog'                 => home_url(),
			'user_ip'              => $ip,
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'comment_type'         => 'contact-form',
			'comment_author'       => $name,
			'comment_author_email' => $email,
			'comment_content'      => implode( "\n", $text ),
			'blog_lang'            => get_locale(),
			'blog_charset'         => get_option( 'blog_charset' ),
		);
		try {
			$res = \Akismet::http_post( \Akismet::build_query( $data ), 'comment-check' );
			return isset( $res[1] ) && 'true' === trim( $res[1] );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Run every check.
	 *
	 * @param array  $values   list of [ key, label, type, value ].
	 * @param string $email    first valid e-mail of the form ('' if none).
	 * @param array  $request  raw POST (token, honeypot, turnstile).
	 * @return array{status:string,reason?:string,message?:string,field?:string}
	 */
	public static function check( $values, $email, $request ) {
		$o  = self::get();
		$ip = self::client_ip();

		// 1. Honeypot.
		if ( ! empty( $request['dpk_hp'] ) ) {
			return array( 'status' => 'spam', 'reason' => 'honeypot' );
		}

		// 2. Token + time trap.
		$token = isset( $request['dpk_token'] ) ? (string) $request['dpk_token'] : '';
		$tok   = self::check_token( $token );
		if ( true !== $tok ) {
			$messages = array(
				'fast' => __( 'Merci de patienter quelques secondes avant d’envoyer le formulaire.', 'douce-paresse-kit' ),
				'used' => __( 'Ce formulaire a déjà été envoyé. Merci de recharger la page.', 'douce-paresse-kit' ),
			);
			return array(
				'status'  => 'error',
				'reason'  => 'token-' . $tok,
				'retry'   => true,
				'message' => isset( $messages[ $tok ] ) ? $messages[ $tok ] : __( 'La session a expiré. Merci de réessayer.', 'douce-paresse-kit' ),
			);
		}

		// 3. Rate limit per IP (every attempt with a valid token counts).
		if ( $o['rate_ip'] && self::count_hit( 'ip|' . $ip, 10 * MINUTE_IN_SECONDS ) > (int) $o['rate_ip'] ) {
			return array( 'status' => 'error', 'reason' => 'rate-ip', 'message' => __( 'Trop de messages envoyés. Merci de réessayer dans quelques minutes.', 'douce-paresse-kit' ) );
		}

		// 4. Cloudflare Turnstile.
		if ( self::turnstile_enabled() ) {
			$cf = isset( $request['cf-turnstile-response'] ) ? (string) $request['cf-turnstile-response'] : '';
			if ( ! self::verify_turnstile( $cf, $ip ) ) {
				return array( 'status' => 'error', 'reason' => 'turnstile', 'message' => __( 'La vérification anti-spam a échoué. Merci de réessayer.', 'douce-paresse-kit' ) );
			}
		}

		// 5. E-mail rules.
		if ( $email ) {
			$domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );
			if ( 'yes' === $o['disposable'] && in_array( $domain, self::DISPOSABLE, true ) ) {
				return array( 'status' => 'error', 'reason' => 'disposable', 'field' => 'email', 'message' => __( 'Merci d’utiliser une adresse e-mail permanente.', 'douce-paresse-kit' ) );
			}
			if ( 'yes' === $o['check_mx'] && function_exists( 'checkdnsrr' ) && ! checkdnsrr( $domain . '.', 'MX' ) && ! checkdnsrr( $domain . '.', 'A' ) ) {
				return array( 'status' => 'error', 'reason' => 'mx', 'field' => 'email', 'message' => __( 'Ce nom de domaine e-mail n’existe pas.', 'douce-paresse-kit' ) );
			}
			if ( $o['rate_email'] && self::count_hit( 'mail|' . strtolower( $email ), HOUR_IN_SECONDS ) > (int) $o['rate_email'] ) {
				return array( 'status' => 'error', 'reason' => 'rate-email', 'message' => __( 'Trop de messages envoyés. Merci de réessayer plus tard.', 'douce-paresse-kit' ) );
			}
		}

		// 6. Content rules.
		$all = implode( "\n", wp_list_pluck( $values, 'value' ) );
		if ( preg_match( '~\[(url|link)[=\]]|<\s*a\s+href~i', $all ) ) {
			return array( 'status' => 'spam', 'reason' => 'markup' );
		}
		if ( self::count_links( $all ) > (int) $o['max_links'] ) {
			return array( 'status' => 'spam', 'reason' => 'links' );
		}
		$word = self::blocked_word( $all, $o['block_words'] );
		if ( '' !== $word ) {
			return array( 'status' => 'spam', 'reason' => 'word: ' . $word );
		}
		if ( 'yes' === $o['use_wp_list'] && function_exists( 'wp_check_comment_disallowed_list' ) ) {
			$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			if ( wp_check_comment_disallowed_list( '', $email, '', $all, $ip, $ua ) ) {
				return array( 'status' => 'spam', 'reason' => 'wp-disallowed-list' );
			}
		}
		if ( 'yes' === $o['foreign_scripts'] && self::foreign_ratio( $all ) > 0.3 ) {
			return array( 'status' => 'spam', 'reason' => 'foreign-script' );
		}

		// 7. Akismet.
		if ( 'yes' === $o['akismet'] && self::akismet_says_spam( $values, $email, $ip ) ) {
			return array( 'status' => 'spam', 'reason' => 'akismet' );
		}

		// 8. Duplicates.
		if ( 'yes' === $o['duplicates'] ) {
			$hash = 'dpk_dup_' . md5( strtolower( $email ) . '|' . preg_replace( '/\s+/', ' ', strtolower( $all ) ) );
			if ( get_transient( $hash ) ) {
				return array( 'status' => 'duplicate', 'reason' => 'duplicate' );
			}
			set_transient( $hash, 1, HOUR_IN_SECONDS );
		}

		return array( 'status' => 'ok' );
	}

	public function flush_elementor() {
		if ( did_action( 'elementor/loaded' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/* ------------------------------------------------------------------
	 * Spam log housekeeping
	 * ------------------------------------------------------------------ */

	public function schedule() {
		if ( ! wp_next_scheduled( 'dpk_purge_spam' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'dpk_purge_spam' );
		}
	}

	/** Spam entries (and the IP stored with them) are deleted after 30 days. */
	public function purge() {
		$ids = get_posts(
			array(
				'post_type'   => Forms::CPT,
				'post_status' => 'any',
				'meta_key'    => '_dpk_spam', // phpcs:ignore WordPress.DB.SlowDBQuery
				'date_query'  => array( array( 'before' => '30 days ago' ) ),
				'fields'      => 'ids',
				'numberposts' => 200,
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/* ------------------------------------------------------------------
	 * Settings screen
	 * ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page( Admin::SLUG, __( 'Anti-spam', 'douce-paresse-kit' ), __( 'Anti-spam', 'douce-paresse-kit' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	private function checkbox( $o, $key, $label, $help = '' ) {
		printf(
			'<label class="dpk-spam-row"><input type="checkbox" name="%1$s[%2$s]" value="yes" %3$s> <strong>%4$s</strong>%5$s</label>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			checked( 'yes', $o[ $key ], false ),
			esc_html( $label ),
			$help ? '<span class="description"> — ' . esc_html( $help ) . '</span>' : ''
		);
	}

	private function number( $o, $key, $label, $help ) {
		printf(
			'<label class="dpk-spam-row"><strong>%3$s</strong> <input type="number" min="0" class="small-text" name="%1$s[%2$s]" value="%4$d"> <span class="description">%5$s</span></label>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_html( $label ),
			(int) $o[ $key ],
			esc_html( $help )
		);
	}

	public function render() {
		$o      = self::get();
		$name   = self::OPTION;
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$cfh    = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';
		?>
		<div class="wrap dpk-admin">
			<h1><?php esc_html_e( 'Douce Paresse — Anti-spam', 'douce-paresse-kit' ); ?></h1>
			<p class="dpk-lead"><?php esc_html_e( 'Protection for the Contact and Professional request forms. The honeypot, signed time token, rate limits, content and e-mail rules work without any account. Cloudflare Turnstile adds a challenge on top.', 'douce-paresse-kit' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'dpk_spam_group' ); ?>

				<div class="dpk-card">
					<h2><?php esc_html_e( 'Cloudflare Turnstile', 'douce-paresse-kit' ); ?></h2>
					<p><?php echo wp_kses_post( __( 'Free. Create a widget at <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">dash.cloudflare.com → Turnstile</a>, add your domain, then paste the two keys here. Your site does not need to use Cloudflare DNS.', 'douce-paresse-kit' ) ); ?></p>
					<?php $this->checkbox( $o, 'turnstile', __( 'Enable Turnstile on the forms', 'douce-paresse-kit' ) ); ?>
					<p><label><strong><?php esc_html_e( 'Site key', 'douce-paresse-kit' ); ?></strong><br><input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[ts_site]" value="<?php echo esc_attr( $o['ts_site'] ); ?>" autocomplete="off"></label></p>
					<p><label><strong><?php esc_html_e( 'Secret key', 'douce-paresse-kit' ); ?></strong><br><input type="password" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[ts_secret]" value="<?php echo esc_attr( $o['ts_secret'] ); ?>" autocomplete="new-password"></label></p>
					<p>
						<label><?php esc_html_e( 'Appearance', 'douce-paresse-kit' ); ?>
							<select name="<?php echo esc_attr( $name ); ?>[ts_appearance]">
								<option value="interaction-only" <?php selected( $o['ts_appearance'], 'interaction-only' ); ?>><?php esc_html_e( 'Only when a challenge is needed (recommended)', 'douce-paresse-kit' ); ?></option>
								<option value="always" <?php selected( $o['ts_appearance'], 'always' ); ?>><?php esc_html_e( 'Always visible', 'douce-paresse-kit' ); ?></option>
							</select>
						</label>
						<label><?php esc_html_e( 'Theme', 'douce-paresse-kit' ); ?>
							<select name="<?php echo esc_attr( $name ); ?>[ts_theme]">
								<?php foreach ( array( 'light', 'dark', 'auto' ) as $t ) : ?>
									<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $o['ts_theme'], $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'Status:', 'douce-paresse-kit' ); ?> <strong><?php echo self::turnstile_enabled() ? esc_html__( 'active', 'douce-paresse-kit' ) : esc_html__( 'not active', 'douce-paresse-kit' ); ?></strong></p>
				</div>

				<div class="dpk-card">
					<h2><?php esc_html_e( 'Cloudflare proxy / Tunnel — real visitor IP', 'douce-paresse-kit' ); ?></h2>
					<p><?php esc_html_e( 'Behind Cloudflare (orange cloud) or a Cloudflare Tunnel (cloudflared), PHP sees Cloudflare’s or the tunnel’s address instead of the visitor. The rate limits then need the CF-Connecting-IP header.', 'douce-paresse-kit' ); ?></p>
					<p>
						<select name="<?php echo esc_attr( $name ); ?>[cloudflare_ip]">
							<option value="auto" <?php selected( $o['cloudflare_ip'], 'auto' ); ?>><?php esc_html_e( 'Automatic — trust CF-Connecting-IP only from Cloudflare IP ranges or a local tunnel (recommended)', 'douce-paresse-kit' ); ?></option>
							<option value="always" <?php selected( $o['cloudflare_ip'], 'always' ); ?>><?php esc_html_e( 'Always trust CF-Connecting-IP (only if the server is reachable exclusively through Cloudflare)', 'douce-paresse-kit' ); ?></option>
							<option value="off" <?php selected( $o['cloudflare_ip'], 'off' ); ?>><?php esc_html_e( 'Off — use the direct connection address', 'douce-paresse-kit' ); ?></option>
						</select>
					</p>
					<p class="description">
						<?php
						/* translators: 1: REMOTE_ADDR 2: CF header 3: detected IP */
						echo esc_html( sprintf( __( 'This request: connection %1$s · CF-Connecting-IP %2$s · detected visitor IP %3$s', 'douce-paresse-kit' ), $remote ? $remote : '—', $cfh ? $cfh : '—', self::client_ip() ) );
						?>
					</p>
				</div>

				<div class="dpk-card">
					<h2><?php esc_html_e( 'Bots & flooding', 'douce-paresse-kit' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Always on: invisible honeypot field and a signed single-use token that requires JavaScript.', 'douce-paresse-kit' ); ?></p>
					<?php
					$this->number( $o, 'min_seconds', __( 'Minimum time before sending', 'douce-paresse-kit' ), __( 'seconds after the page loads (humans never send faster)', 'douce-paresse-kit' ) );
					$this->number( $o, 'max_age_hours', __( 'Form expires after', 'douce-paresse-kit' ), __( 'hours (a fresh token is fetched automatically)', 'douce-paresse-kit' ) );
					$this->number( $o, 'rate_ip', __( 'Max messages per IP', 'douce-paresse-kit' ), __( 'per 10 minutes (0 = unlimited)', 'douce-paresse-kit' ) );
					$this->number( $o, 'rate_email', __( 'Max messages per e-mail address', 'douce-paresse-kit' ), __( 'per hour (0 = unlimited)', 'douce-paresse-kit' ) );
					$this->checkbox( $o, 'duplicates', __( 'Ignore identical messages sent again within an hour', 'douce-paresse-kit' ) );
					?>
				</div>

				<div class="dpk-card">
					<h2><?php esc_html_e( 'Content & e-mail rules', 'douce-paresse-kit' ); ?></h2>
					<?php
					$this->number( $o, 'max_links', __( 'Max links in a message', 'douce-paresse-kit' ), __( 'more = spam; BBCode [url] and HTML links are always spam', 'douce-paresse-kit' ) );
					$this->checkbox( $o, 'foreign_scripts', __( 'Mostly non-Latin text is spam', 'douce-paresse-kit' ), __( 'Cyrillic, Chinese… (the site is in French)', 'douce-paresse-kit' ) );
					$this->checkbox( $o, 'use_wp_list', __( 'Use WordPress “Disallowed Comment Keys”', 'douce-paresse-kit' ), __( 'Settings → Discussion', 'douce-paresse-kit' ) );
					$this->checkbox( $o, 'disposable', __( 'Refuse disposable e-mail addresses', 'douce-paresse-kit' ) );
					$this->checkbox( $o, 'check_mx', __( 'Refuse e-mail domains that do not exist', 'douce-paresse-kit' ), __( 'DNS MX/A lookup', 'douce-paresse-kit' ) );
					$this->checkbox( $o, 'akismet', __( 'Ask Akismet', 'douce-paresse-kit' ), class_exists( '\Akismet' ) ? __( 'Akismet plugin detected', 'douce-paresse-kit' ) : __( 'only works when the Akismet plugin is active and connected', 'douce-paresse-kit' ) );
					?>
					<p><label><strong><?php esc_html_e( 'Blocked words', 'douce-paresse-kit' ); ?></strong> <span class="description"><?php esc_html_e( 'one per line, whole words', 'douce-paresse-kit' ); ?></span><br>
						<textarea name="<?php echo esc_attr( $name ); ?>[block_words]" rows="6" class="large-text code"><?php echo esc_textarea( $o['block_words'] ); ?></textarea></label></p>
				</div>

				<div class="dpk-card">
					<h2><?php esc_html_e( 'Spam log', 'douce-paresse-kit' ); ?></h2>
					<?php $this->checkbox( $o, 'keep_spam', __( 'Keep blocked messages under Douce Paresse → Messages', 'douce-paresse-kit' ), __( 'marked “Spam”, deleted automatically after 30 days', 'douce-paresse-kit' ) ); ?>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

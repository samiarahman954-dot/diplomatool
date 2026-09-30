<?php
/**
 * Instant quote builder: pricing settings, shortcode/widget renderer,
 * server-side estimate, photo uploads, lead storage and emails.
 *
 * The browser never decides the price: the estimate is computed here from the
 * saved pricing and returned to the builder, so what the customer sees, what
 * lands in Leads and what is emailed are always the same numbers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Quote {

	const ACTION        = 'jmk_submit_quote';
	const SHORTCODE     = 'jmk_quote_builder';
	const OPT_PRICING   = 'jmk_quote_pricing';
	const OPT_COMPANY   = 'jmk_company';
	const OPT_EMAIL_CUS = 'jmk_quote_email_customer';
	const SETTINGS_SLUG = 'jmk-quote';
	const UPLOAD_SUBDIR = 'jmk-quotes';

	const MAX_FILES     = 10;
	const MAX_FILE_MB   = 10;
	const RATE_MAX      = 20; // per IP; see JMK_Leads::RATE_MAX
	const MAIL_CAP      = 3;  // customer copies per email address per hour
	const RATE_WINDOW   = 600;
	const EST_NO_OFFSET = 4000;

	const SIZES = array(
		'few'    => 'Just a few items',
		'studio' => 'Studio / 1 room',
		'br1'    => '1 Bedroom',
		'br2'    => '2 Bedroom',
		'br3'    => '3 Bedroom',
		'br4'    => '4+ Bedroom',
	);

	const SIZE_EMOJI = array(
		'few'    => '📦',
		'studio' => '🛏️',
		'br1'    => '🏡',
		'br2'    => '🏘️',
		'br3'    => '🏠',
		'br4'    => '🏰',
	);

	/** value => [emoji, label, priced instantly?] */
	const MOVE_TYPES = array(
		'Local move'               => array( '🏠', 'Local move (within DFW metro)', true ),
		'Long-distance move'       => array( '🛣️', 'Long-distance move', false ),
		'Office / Commercial'      => array( '🏢', 'Office / Commercial', false ),
		'Labor only (load/unload)' => array( '💪', 'Labor only (load / unload)', true ),
	);

	const ACCESS = array( 'Ground floor / house', 'Stairs', 'Elevator' );

	const FLEX = array(
		'Exact date'       => 'Exact',
		'Flexible ±3 days' => 'Flexible',
		'Just exploring'   => 'Exploring',
	);

	const ALLOWED_MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
		'heic'         => 'image/heic',
		'pdf'          => 'application/pdf',
	);

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_files' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );
		add_action( 'admin_post_jmk_save_quote_settings', array( __CLASS__, 'save_settings' ) );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function default_pricing() {
		return array(
			'crew_rate'  => array( 2 => 119, 3 => 159, 4 => 199 ),
			'travel_fee' => 89,
			'hours'      => array(
				'few'    => array( 1, 2 ),
				'studio' => array( 2, 3 ),
				'br1'    => array( 3, 4 ),
				'br2'    => array( 4, 6 ),
				'br3'    => array( 6, 8 ),
				'br4'    => array( 8, 11 ),
			),
			'crew_rec'   => array(
				'few'    => 2,
				'studio' => 2,
				'br1'    => 2,
				'br2'    => 3,
				'br3'    => 3,
				'br4'    => 4,
			),
			'pack_hours' => array( 1.5, 3 ),
			'supplies'   => array(
				'few'    => 40,
				'studio' => 60,
				'br1'    => 90,
				'br2'    => 140,
				'br3'    => 190,
				'br4'    => 260,
			),
			'specialty'  => array(
				'Piano'           => 150,
				'Pool table'      => 200,
				'Gun safe'        => 250,
				'Large appliance' => 75,
				'Treadmill / gym' => 75,
			),
			'stairs_hrs' => 0.5,
		);
	}

	public static function pricing() {
		$saved = get_option( self::OPT_PRICING );
		$def   = self::default_pricing();
		if ( ! is_array( $saved ) ) {
			return $def;
		}
		// Specialty is a free list: a saved (even empty) list replaces the default.
		$out              = array_replace_recursive( $def, array_diff_key( $saved, array( 'specialty' => 1 ) ) );
		$out['specialty'] = isset( $saved['specialty'] ) && is_array( $saved['specialty'] ) ? $saved['specialty'] : $def['specialty'];
		return $out;
	}

	public static function company() {
		$def = array(
			'name'     => 'Just Move DFW',
			'tagline'  => 'Licensed & Insured · Grand Prairie, TX',
			'phone'    => '(972) 638-7479',
			'email'    => 'justmovedfw@gmail.com',
			'site'     => 'justmovedfw.com',
			'location' => 'Grand Prairie, TX 75054',
			'logo'     => '',
		);
		$saved = get_option( self::OPT_COMPANY );
		$out   = is_array( $saved ) ? array_merge( $def, array_intersect_key( $saved, $def ) ) : $def;
		if ( '' === $out['logo'] ) {
			$out['logo'] = JMK_URL . 'assets/img/jmk-logo.jpg';
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Estimate (single source of truth)
	 * ------------------------------------------------------------------ */

	public static function is_instant( $move_type ) {
		return isset( self::MOVE_TYPES[ $move_type ] ) && self::MOVE_TYPES[ $move_type ][2];
	}

	/**
	 * @param array $q Validated quote input (size, crew, pack, supplies, specialty, from_access, to_access).
	 * @param array $p Pricing.
	 */
	public static function calc( array $q, array $p ) {
		$size = isset( $p['hours'][ $q['size'] ] ) ? $q['size'] : 'br2';
		$lo   = (float) $p['hours'][ $size ][0];
		$hi   = (float) $p['hours'][ $size ][1];
		if ( $q['pack'] ) {
			$lo += (float) $p['pack_hours'][0];
			$hi += (float) $p['pack_hours'][1];
		}
		if ( 'Stairs' === $q['from_access'] ) {
			$hi += (float) $p['stairs_hrs'];
		}
		if ( 'Stairs' === $q['to_access'] ) {
			$hi += (float) $p['stairs_hrs'];
		}
		$crew = $q['crew'] ? (int) $q['crew'] : (int) $p['crew_rec'][ $size ];
		$rate = isset( $p['crew_rate'][ $crew ] ) ? (float) $p['crew_rate'][ $crew ] : (float) $p['crew_rate'][2];

		$supplies  = $q['supplies'] ? (float) $p['supplies'][ $size ] : 0.0;
		$specialty = 0.0;
		foreach ( $q['specialty'] as $item ) {
			$specialty += isset( $p['specialty'][ $item ] ) ? (float) $p['specialty'][ $item ] : 0.0;
		}
		$travel = (float) $p['travel_fee'];
		$extra  = $travel + $supplies + $specialty;

		return array(
			'size'       => $size,
			'lo'         => $lo,
			'hi'         => $hi,
			'crew'       => $crew,
			'rate'       => $rate,
			'labor_lo'   => $rate * $lo,
			'labor_hi'   => $rate * $hi,
			'supplies'   => $supplies,
			'specialty'  => $specialty,
			'travel'     => $travel,
			'total_lo'   => $rate * $lo + $extra,
			'total_hi'   => $rate * $hi + $extra,
		);
	}

	public static function money( $n ) {
		return '$' . number_format( round( (float) $n ) );
	}

	private static function num( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Display rows shared by the builder, the PDF and the emails.
	 */
	public static function present( array $c, array $specialty ) {
		$hrs  = self::num( $c['lo'] ) . '–' . self::num( $c['hi'] );
		$rows = array(
			array( 'Crew & truck', $c['crew'] . ' movers × $' . self::num( $c['rate'] ) . '/hr × ' . $hrs . ' hrs', self::money( $c['labor_lo'] ) . ' – ' . self::money( $c['labor_hi'] ) ),
			array( 'Travel / trip fee', 'Flat', self::money( $c['travel'] ) ),
		);
		if ( $c['supplies'] > 0 ) {
			$rows[] = array( 'Packing materials', self::SIZES[ $c['size'] ], self::money( $c['supplies'] ) );
		}
		if ( $c['specialty'] > 0 ) {
			$rows[] = array( 'Specialty items', implode( ', ', $specialty ), self::money( $c['specialty'] ) );
		}
		return array(
			'rows'  => $rows,
			'total' => self::money( $c['total_lo'] ) . ' – ' . self::money( $c['total_hi'] ),
			'basis' => $c['crew'] . ' movers · ' . $hrs . ' hrs @ $' . self::num( $c['rate'] ) . '/hr · truck included',
		);
	}

	/* ------------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------------ */

	public static function register_assets() {
		wp_register_style(
			'jmk-quote-fonts',
			'https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&family=Hanken+Grotesk:wght@400;500;600;700&display=swap',
			array(),
			null
		);
		wp_register_style( 'jmk-quote', JMK_URL . 'assets/css/jmk-quote.css', array( 'jmk-quote-fonts' ), JMK_VERSION );
		wp_register_script( 'jmk-quote', JMK_URL . 'assets/js/jmk-quote.js', array(), JMK_VERSION, true );
	}

	/**
	 * Load CSS in <head> for posts that contain the shortcode (avoids a flash of
	 * unstyled content); render() enqueues again as a fallback for other contexts.
	 */
	public static function maybe_enqueue() {
		$post = get_post();
		if ( $post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			wp_enqueue_style( 'jmk-quote' );
		}
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'brand'      => '',
				'tagline'    => '',
				'logo'       => '',
				'background' => 'yes',
			),
			$atts,
			self::SHORTCODE
		);
		return self::render(
			array(
				'brand'      => $atts['brand'],
				'tagline'    => $atts['tagline'],
				'logo'       => $atts['logo'],
				'background' => 'no' !== strtolower( $atts['background'] ),
			)
		);
	}

	/**
	 * Builder markup. Used by the shortcode and the Elementor widget.
	 *
	 * @param array $args brand, tagline, logo (URL), background (bool).
	 * @return string
	 */
	public static function render( array $args = array() ) {
		wp_enqueue_style( 'jmk-quote' );
		wp_enqueue_script( 'jmk-quote' );

		$co      = self::company();
		$p       = self::pricing();
		$brand   = ! empty( $args['brand'] ) ? $args['brand'] : $co['name'];
		$tagline = ! empty( $args['tagline'] ) ? $args['tagline'] : $co['tagline'];
		$logo    = ! empty( $args['logo'] ) ? $args['logo'] : $co['logo'];
		$bg      = ! isset( $args['background'] ) || $args['background'];

		$config = array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'action'    => self::ACTION,
			'jspdf'     => JMK_URL . 'assets/js/vendor/jspdf.umd.min.js',
			'logo'      => $logo,
			'company'   => array(
				'name'     => $brand,
				'phone'    => $co['phone'],
				'email'    => $co['email'],
				'site'     => $co['site'],
				'location' => $co['location'],
			),
			'crewRec'   => $p['crew_rec'],
			'sizes'     => self::SIZES,
			'maxFiles'  => self::MAX_FILES,
			'maxFileMb' => self::MAX_FILE_MB,
			// Leave headroom under PHP's post_max_size / upload_max_filesize.
			'maxTotal'  => (int) floor( wp_max_upload_size() * 0.9 ),
			'i18n'      => array(
				'sending'   => __( 'Sending…', 'jmk' ),
				'getQuote'  => __( 'Get my estimate', 'jmk' ),
				'next'      => __( 'Next', 'jmk' ),
				'step'      => __( 'Step %1$d of %2$d', 'jmk' ),
				'ready'     => __( 'Estimate ready', 'jmk' ),
				'error'     => __( 'We could not send your request. Please try again or call us.', 'jmk' ),
				'tooBig'    => __( 'Some files were too large and were skipped.', 'jmk' ),
				'tooMany'   => __( 'You can add up to %d files.', 'jmk' ),
				'pdf'       => __( 'Preparing PDF…', 'jmk' ),
				'pdfError'  => __( 'Could not create the PDF. Please try again.', 'jmk' ),
				'crewHint'  => __( 'Recommended for a %1$s move: %2$d movers. Tap to change.', 'jmk' ),
				'custom'    => __( '%s are priced individually based on distance, volume, and timing. A coordinator will reach out shortly with your detailed quote.', 'jmk' ),
				'badEmail'  => __( 'Please enter a valid email address.', 'jmk' ),
			),
		);

		$check = '<span class="jmq-mark"><svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="#fff" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
		$uid   = 'jmq' . wp_generate_password( 6, false, false );

		ob_start();
		?>
		<div class="jmq<?php echo $bg ? ' jmq-bg' : ''; ?>" data-jmq="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
		<div class="jmq-card">
			<div class="jmq-header">
				<div class="jmq-brand">
					<div class="jmq-logo"><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $brand ); ?>"></div>
					<div>
						<div class="jmq-brand-name"><?php echo esc_html( $brand ); ?></div>
						<div class="jmq-brand-sub"><?php echo esc_html( $tagline ); ?></div>
					</div>
				</div>
				<div class="jmq-progress-wrap">
					<div class="jmq-progress"><div class="jmq-progress-bar"></div></div>
					<div class="jmq-progress-label" aria-live="polite"></div>
				</div>
				<svg class="jmq-wave" viewBox="0 0 560 26" preserveAspectRatio="none" height="26" aria-hidden="true"><path d="M0 14 C 110 0, 180 26, 290 13 S 470 0, 560 12 L560 26 L0 26 Z" fill="#ffffff"/></svg>
			</div>

			<form class="jmq-body" novalidate>
				<div class="jmq-step is-active" data-step="1">
					<div class="jmq-q"><?php esc_html_e( 'What kind of', 'jmk' ); ?> <span class="jmq-accent"><?php esc_html_e( 'move?', 'jmk' ); ?></span></div>
					<div class="jmq-opts" data-single="moveType" role="radiogroup">
						<?php foreach ( self::MOVE_TYPES as $val => $mt ) : ?>
							<button type="button" class="jmq-opt" role="radio" aria-checked="false" data-val="<?php echo esc_attr( $val ); ?>"><?php echo $check; // phpcs:ignore ?><span class="jmq-emoji" aria-hidden="true"><?php echo esc_html( $mt[0] ); ?></span><?php echo esc_html( $mt[1] ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="jmq-step" data-step="2">
					<div class="jmq-q"><?php esc_html_e( 'How big is the', 'jmk' ); ?> <span class="jmq-accent"><?php esc_html_e( 'move?', 'jmk' ); ?></span></div>
					<div class="jmq-sub"><?php esc_html_e( 'Pick the closest match — this sets the crew and estimated hours.', 'jmk' ); ?></div>
					<div class="jmq-opts" data-single="size" role="radiogroup">
						<?php foreach ( self::SIZES as $val => $label ) : ?>
							<button type="button" class="jmq-opt" role="radio" aria-checked="false" data-val="<?php echo esc_attr( $val ); ?>"><?php echo $check; // phpcs:ignore ?><span class="jmq-emoji" aria-hidden="true"><?php echo esc_html( self::SIZE_EMOJI[ $val ] ); ?></span><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="jmq-step" data-step="3">
					<div class="jmq-q"><?php esc_html_e( 'Where are we', 'jmk' ); ?> <span class="jmq-accent"><?php esc_html_e( 'moving?', 'jmk' ); ?></span></div>
					<?php
					foreach ( array(
						'from' => __( '📍 Picking up from', 'jmk' ),
						'to'   => __( '🏁 Delivering to', 'jmk' ),
					) as $k => $tag ) :
						?>
						<div class="jmq-ab">
							<div class="jmq-tag"><?php echo esc_html( $tag ); ?></div>
							<div class="jmq-field"><label for="<?php echo esc_attr( $uid . '-' . $k ); ?>a"><?php esc_html_e( 'Address', 'jmk' ); ?></label>
								<input class="jmq-input" id="<?php echo esc_attr( $uid . '-' . $k ); ?>a" name="<?php echo esc_attr( $k ); ?>_addr" autocomplete="street-address" maxlength="200" placeholder="<?php esc_attr_e( 'Street, city, ZIP', 'jmk' ); ?>"></div>
							<div class="jmq-field"><label for="<?php echo esc_attr( $uid . '-' . $k ); ?>s"><?php esc_html_e( 'Access', 'jmk' ); ?></label>
								<select class="jmq-input" id="<?php echo esc_attr( $uid . '-' . $k ); ?>s" name="<?php echo esc_attr( $k ); ?>_access">
									<?php foreach ( self::ACCESS as $a ) : ?>
										<option><?php echo esc_html( $a ); ?></option>
									<?php endforeach; ?>
								</select></div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="jmq-step" data-step="4">
					<div class="jmq-q"><?php esc_html_e( "When's the", 'jmk' ); ?> <span class="jmq-accent"><?php esc_html_e( 'move?', 'jmk' ); ?></span></div>
					<div class="jmq-field"><label for="<?php echo esc_attr( $uid ); ?>-d"><?php esc_html_e( 'Preferred date', 'jmk' ); ?></label>
						<input class="jmq-input" id="<?php echo esc_attr( $uid ); ?>-d" name="move_date" type="date"></div>
					<div class="jmq-field"><span class="jmq-label"><?php esc_html_e( 'How firm is that date?', 'jmk' ); ?></span>
						<div class="jmq-toggle" data-toggle="flex">
							<?php
							$first = true;
							foreach ( self::FLEX as $val => $label ) :
								?>
								<button type="button" class="jmq-chip jmq-t<?php echo $first ? ' is-selected' : ''; ?>" aria-pressed="<?php echo $first ? 'true' : 'false'; ?>" data-val="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></button>
								<?php
								$first = false;
							endforeach;
							?>
						</div>
					</div>
				</div>

				<div class="jmq-step" data-step="5">
					<div class="jmq-q"><?php esc_html_e( 'Services &', 'jmk' ); ?> <span class="jmq-accent"><?php esc_html_e( 'details', 'jmk' ); ?></span></div>
					<div class="jmq-sub"><?php esc_html_e( 'Optional — pick what applies, then add photos if you can.', 'jmk' ); ?></div>
					<div class="jmq-field"><span class="jmq-label"><?php esc_html_e( 'Crew size', 'jmk' ); ?></span>
						<div class="jmq-toggle" data-toggle="crew">
							<?php foreach ( array( 2, 3, 4 ) as $n ) : ?>
								<button type="button" class="jmq-chip jmq-t" aria-pressed="false" data-val="<?php echo (int) $n; ?>">
									<?php
									/* translators: %d: number of movers */
									echo esc_html( sprintf( __( '%d movers', 'jmk' ), $n ) );
									?>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="jmq-hint jmq-crew-hint"><?php esc_html_e( "We'll recommend a crew based on your move size.", 'jmk' ); ?></div>
					</div>
					<?php
					foreach ( array(
						'pack'     => __( 'Full packing service?', 'jmk' ),
						'supplies' => __( 'Need packing materials / boxes?', 'jmk' ),
					) as $k => $label ) :
						?>
						<div class="jmq-field"><span class="jmq-label"><?php echo esc_html( $label ); ?></span>
							<div class="jmq-toggle" data-toggle="<?php echo esc_attr( $k ); ?>">
								<button type="button" class="jmq-chip jmq-t is-selected" aria-pressed="true" data-val="no"><?php esc_html_e( 'No', 'jmk' ); ?></button>
								<button type="button" class="jmq-chip jmq-t" aria-pressed="false" data-val="yes"><?php esc_html_e( 'Yes', 'jmk' ); ?></button>
							</div>
						</div>
					<?php endforeach; ?>
					<?php if ( ! empty( $p['specialty'] ) ) : ?>
						<div class="jmq-field"><span class="jmq-label"><?php esc_html_e( 'Any specialty items?', 'jmk' ); ?></span>
							<div class="jmq-chips" data-multi="specialty">
								<?php foreach ( array_keys( $p['specialty'] ) as $item ) : ?>
									<button type="button" class="jmq-chip" aria-pressed="false" data-val="<?php echo esc_attr( $item ); ?>"><?php echo esc_html( $item ); ?></button>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
					<div class="jmq-field">
						<span class="jmq-label"><?php esc_html_e( 'Photos of your place (optional)', 'jmk' ); ?></span>
						<input type="file" class="jmq-file" accept="image/*,application/pdf" multiple hidden>
						<button type="button" class="jmq-upload">
							<span class="jmq-upico" aria-hidden="true">📷</span>
							<span class="jmq-uptext"><?php esc_html_e( 'Tap to add photos or files', 'jmk' ); ?></span>
							<span class="jmq-uphint"><?php esc_html_e( 'A quick look at your rooms helps us nail down the crew and time', 'jmk' ); ?></span>
						</button>
						<div class="jmq-thumbs"></div>
						<div class="jmq-hint jmq-file-msg" role="status"></div>
					</div>
				</div>

				<div class="jmq-step" data-step="6">
					<div class="jmq-q"><?php esc_html_e( 'Where should we send your', 'jmk' ); ?> <span class="jmq-accent"><?php esc_html_e( 'quote?', 'jmk' ); ?></span></div>
					<div class="jmq-sub"><?php esc_html_e( "We'll reach out to lock in your details.", 'jmk' ); ?></div>
					<div class="jmq-field"><label for="<?php echo esc_attr( $uid ); ?>-n"><?php esc_html_e( 'Full name', 'jmk' ); ?></label>
						<input class="jmq-input" id="<?php echo esc_attr( $uid ); ?>-n" name="name" autocomplete="name" maxlength="100" placeholder="<?php esc_attr_e( 'First and last name', 'jmk' ); ?>"></div>
					<div class="jmq-field"><label for="<?php echo esc_attr( $uid ); ?>-p"><?php esc_html_e( 'Phone number', 'jmk' ); ?></label>
						<input class="jmq-input" id="<?php echo esc_attr( $uid ); ?>-p" name="phone" type="tel" autocomplete="tel" maxlength="30" placeholder="(555) 555-5555"></div>
					<div class="jmq-field"><label for="<?php echo esc_attr( $uid ); ?>-e"><?php esc_html_e( 'Email address', 'jmk' ); ?></label>
						<input class="jmq-input" id="<?php echo esc_attr( $uid ); ?>-e" name="email" type="email" autocomplete="email" maxlength="120" placeholder="you@email.com"></div>
					<div class="jmq-hint"><?php esc_html_e( 'Phone or email is required. Add your email to get a copy of the estimate.', 'jmk' ); ?></div>
					<div class="jmq-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="jmk_hp" value="" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore></label></div>
					<div class="jmq-error" role="alert"></div>
				</div>

				<div class="jmq-step" data-step="7">
					<div class="jmq-quote-head"><span class="jmq-quote-badge">★ <?php esc_html_e( 'Your moving estimate', 'jmk' ); ?></span></div>
					<div class="jmq-local">
						<div class="jmq-total-box">
							<div class="jmq-total-label"><?php esc_html_e( 'Estimated total', 'jmk' ); ?></div>
							<div class="jmq-total-amt">$0</div>
							<div class="jmq-total-sub">—</div>
						</div>
						<div class="jmq-lines"></div>
						<button type="button" class="jmq-dlbtn">⬇ &nbsp;<?php esc_html_e( 'Download my estimate (PDF)', 'jmk' ); ?></button>
						<div class="jmq-note-card"><b><?php esc_html_e( 'How moving estimates work:', 'jmk' ); ?></b> <?php esc_html_e( 'local moves are billed by the hour, so your final price depends on the actual time on move day. This range covers the typical span for a move your size. Your coordinator confirms everything before booking.', 'jmk' ); ?></div>
					</div>
					<div class="jmq-custom" hidden>
						<div class="jmq-callout">
							<div class="jmq-callout-t"><?php esc_html_e( "We'll build you a custom quote", 'jmk' ); ?></div>
							<div class="jmq-callout-d"></div>
						</div>
						<button type="button" class="jmq-dlbtn">⬇ &nbsp;<?php esc_html_e( 'Download my request summary (PDF)', 'jmk' ); ?></button>
					</div>
					<div class="jmq-status"><span aria-hidden="true">✓</span><span class="jmq-status-text"></span></div>
				</div>
			</form>

			<div class="jmq-footer">
				<button type="button" class="jmq-btn jmq-btn-back" hidden><?php esc_html_e( 'Back', 'jmk' ); ?></button>
				<button type="button" class="jmq-btn jmq-btn-skip" hidden><?php esc_html_e( 'Skip', 'jmk' ); ?></button>
				<button type="button" class="jmq-btn jmq-btn-next" disabled><?php esc_html_e( 'Next', 'jmk' ); ?></button>
				<button type="button" class="jmq-btn jmq-btn-restart" hidden><?php esc_html_e( 'Start a new quote', 'jmk' ); ?></button>
			</div>
			<div class="jmq-footnote"><?php echo esc_html( implode( ' · ', array_filter( array( $brand, $co['location'], $co['phone'], $co['email'], 'Licensed & Insured' ) ) ) ); ?></div>
		</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * Submission
	 * ------------------------------------------------------------------ */

	private static function fail( $message, $status = 400 ) {
		wp_send_json_error( array( 'message' => $message ), $status );
	}

	private static function post( $key, $max = 200 ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public form, see JMK_Leads::handle().
		$v = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		return mb_substr( $v, 0, $max );
	}

	/**
	 * Validate the posted builder fields.
	 *
	 * @return array|WP_Error
	 */
	public static function validate( array $in, array $p ) {
		$q = array(
			'move_type'   => $in['move_type'],
			'size'        => $in['size'],
			'from_addr'   => $in['from_addr'],
			'from_access' => $in['from_access'],
			'to_addr'     => $in['to_addr'],
			'to_access'   => $in['to_access'],
			'move_date'   => $in['move_date'],
			'flex'        => $in['flex'],
			'crew'        => (int) $in['crew'],
			'pack'        => 'yes' === $in['pack'],
			'supplies'    => 'yes' === $in['supplies'],
			'specialty'   => array(),
			'name'        => $in['name'],
			'phone'       => $in['phone'],
			'email'       => $in['email'],
		);

		if ( ! isset( self::MOVE_TYPES[ $q['move_type'] ] ) ) {
			return new WP_Error( 'move_type', __( 'Please choose the kind of move.', 'jmk' ) );
		}
		if ( ! isset( self::SIZES[ $q['size'] ] ) ) {
			return new WP_Error( 'size', __( 'Please choose the size of the move.', 'jmk' ) );
		}
		if ( '' === $q['from_addr'] || '' === $q['to_addr'] ) {
			return new WP_Error( 'addr', __( 'Please add both addresses.', 'jmk' ) );
		}
		foreach ( array( 'from_access', 'to_access' ) as $k ) {
			if ( ! in_array( $q[ $k ], self::ACCESS, true ) ) {
				$q[ $k ] = self::ACCESS[0];
			}
		}
		$date = DateTime::createFromFormat( '!Y-m-d', $q['move_date'], wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $q['move_date'] ) {
			return new WP_Error( 'date', __( 'Please pick a valid move date.', 'jmk' ) );
		}
		// One day of slack for visitors in time zones behind the site's.
		if ( $date < new DateTime( 'yesterday', wp_timezone() ) ) {
			return new WP_Error( 'date', __( 'Please pick a date that is not in the past.', 'jmk' ) );
		}
		if ( ! isset( self::FLEX[ $q['flex'] ] ) ) {
			$q['flex'] = 'Exact date';
		}
		if ( ! in_array( $q['crew'], array( 2, 3, 4 ), true ) ) {
			$q['crew'] = 0;
		}
		foreach ( (array) $in['specialty'] as $item ) {
			if ( isset( $p['specialty'][ $item ] ) && ! in_array( $item, $q['specialty'], true ) ) {
				$q['specialty'][] = $item;
			}
		}
		if ( '' === $q['name'] ) {
			return new WP_Error( 'name', __( 'Please add your name.', 'jmk' ) );
		}
		if ( '' === $q['phone'] && '' === $q['email'] ) {
			return new WP_Error( 'contact', __( 'Please add a phone number or email so we can reach you.', 'jmk' ) );
		}
		if ( '' !== $q['email'] && ! is_email( $q['email'] ) ) {
			return new WP_Error( 'email', __( 'Please enter a valid email address.', 'jmk' ) );
		}
		if ( '' !== $q['phone'] && strlen( preg_replace( '/\D/', '', $q['phone'] ) ) < 7 ) {
			return new WP_Error( 'phone', __( 'Please enter a valid phone number.', 'jmk' ) );
		}
		return $q;
	}

	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form, see JMK_Leads::handle().
		if ( ! empty( $_POST['jmk_hp'] ) ) {
			wp_send_json_success( array( 'ignored' => true ) );
		}

		$ip_key = 'jmk_rlq_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$hits   = (int) get_transient( $ip_key );
		if ( $hits >= self::RATE_MAX ) {
			self::fail( __( 'Too many requests. Please call us instead.', 'jmk' ), 429 );
		}

		$specialty = isset( $_POST['specialty'] ) && is_array( $_POST['specialty'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['specialty'] ) )
			: array();
		// phpcs:enable

		$p = self::pricing();
		$q = self::validate(
			array(
				'move_type'   => self::post( 'move_type' ),
				'size'        => self::post( 'size' ),
				'from_addr'   => self::post( 'from_addr' ),
				'from_access' => self::post( 'from_access' ),
				'to_addr'     => self::post( 'to_addr' ),
				'to_access'   => self::post( 'to_access' ),
				'move_date'   => self::post( 'move_date', 10 ),
				'flex'        => self::post( 'flex' ),
				'crew'        => self::post( 'crew', 2 ),
				'pack'        => self::post( 'pack', 3 ),
				'supplies'    => self::post( 'supplies', 3 ),
				'specialty'   => $specialty,
				'name'        => self::post( 'name', 100 ),
				'phone'       => self::post( 'phone', 30 ),
				'email'       => sanitize_email( self::post( 'email', 120 ) ),
			),
			$p
		);
		if ( is_wp_error( $q ) ) {
			self::fail( $q->get_error_message() );
		}

		set_transient( $ip_key, $hits + 1, self::RATE_WINDOW );

		$instant = self::is_instant( $q['move_type'] );
		$calc    = $instant ? self::calc( $q, $p ) : null;
		if ( $calc ) {
			$q['crew'] = $calc['crew'];
		} elseif ( ! $q['crew'] ) {
			$q['crew'] = (int) $p['crew_rec'][ $q['size'] ];
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => JMK_Leads::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $q['name'] . ' – ' . self::SIZES[ $q['size'] ],
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			self::fail( __( 'Something went wrong. Please call us instead.', 'jmk' ), 500 );
		}

		$est_no   = self::EST_NO_OFFSET + (int) $post_id;
		$est_date = wp_date( 'm/d/Y' );
		// Photos are a bonus: an upload problem must never lose the lead or its emails.
		try {
			$files = self::handle_files();
		} catch ( \Throwable $e ) {
			$files = array();
			remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		}
		$view     = $calc ? self::present( $calc, $q['specialty'] ) : null;

		$record = array(
			'quote'    => $q,
			'instant'  => $instant,
			'calc'     => $calc,
			'view'     => $view,
			'est_no'   => $est_no,
			'est_date' => $est_date,
			'files'    => $files,
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'page_url' => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '',
		);

		update_post_meta( $post_id, '_jmk_kind', 'quote' );
		update_post_meta( $post_id, '_jmk_quote', $record );
		update_post_meta( $post_id, '_jmk_phone', $q['phone'] );
		update_post_meta( $post_id, '_jmk_email', $q['email'] );
		update_post_meta( $post_id, '_jmk_size', self::SIZES[ $q['size'] ] );
		update_post_meta( $post_id, '_jmk_source', 'quote-builder' );
		update_post_meta( $post_id, '_jmk_page_url', $record['page_url'] );

		self::email_admin( $record, $post_id );
		$customer_emailed = false;
		if ( '' !== $q['email'] && get_option( self::OPT_EMAIL_CUS, '1' ) ) {
			$customer_emailed = self::email_customer( $record );
		}

		/** Fires after a builder quote is stored. @see jmk_lead_received */
		do_action( 'jmk_quote_received', $record, $post_id );

		wp_send_json_success(
			array(
				'estNo'    => $est_no,
				'estDate'  => $est_date,
				'instant'  => $instant,
				'view'     => $view,
				'quote'    => array(
					'moveType'   => $q['move_type'],
					'size'       => self::SIZES[ $q['size'] ],
					'from'       => $q['from_addr'] . ' (' . $q['from_access'] . ')',
					'to'         => $q['to_addr'] . ' (' . $q['to_access'] . ')',
					'date'       => $q['move_date'] . '  ·  ' . $q['flex'],
					'specialty'  => implode( ', ', $q['specialty'] ),
					'name'       => $q['name'],
					'contact'    => '' !== $q['email'] ? $q['email'] : $q['phone'],
				),
				'files'    => count( $files ),
				'emailed'  => $customer_emailed,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Uploads
	 * ------------------------------------------------------------------ */

	private static function handle_files() {
		if ( empty( $_FILES['files'] ) || ! is_array( $_FILES['files']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return array();
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$raw   = $_FILES['files']; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
		$out   = array();
		$count = min( count( $raw['name'] ), self::MAX_FILES );

		add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( UPLOAD_ERR_OK !== (int) $raw['error'][ $i ] || (int) $raw['size'][ $i ] > self::MAX_FILE_MB * MB_IN_BYTES ) {
				continue;
			}
			$orig  = sanitize_file_name( $raw['name'][ $i ] );
			$check = wp_check_filetype_and_ext( $raw['tmp_name'][ $i ], $orig, self::ALLOWED_MIMES );
			if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
				continue;
			}
			$file = array(
				// Random, unguessable name: these are private photos of customers' homes.
				'name'     => wp_generate_password( 24, false, false ) . '.' . $check['ext'],
				'type'     => $check['type'],
				'tmp_name' => $raw['tmp_name'][ $i ],
				'error'    => 0,
				'size'     => (int) $raw['size'][ $i ],
			);
			$res = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => self::ALLOWED_MIMES,
				)
			);
			if ( empty( $res['error'] ) && ! empty( $res['file'] ) ) {
				$out[] = array(
					'name' => $orig,
					'url'  => $res['url'],
					'file' => $res['file'],
					'type' => $res['type'],
				);
			}
		}
		remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );

		return $out;
	}

	/**
	 * uploads/jmk-quotes/YYYY/MM, with an index.php so the folder can't be listed.
	 */
	public static function upload_dir( $dirs ) {
		$dirs['subdir'] = '/' . self::UPLOAD_SUBDIR . $dirs['subdir'];
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

		$root = trailingslashit( $dirs['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( ! file_exists( $root . '/index.php' ) && wp_mkdir_p( $root ) ) {
			file_put_contents( $root . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dirs;
	}

	public static function delete_files( $post_id ) {
		if ( JMK_Leads::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$record = get_post_meta( $post_id, '_jmk_quote', true );
		if ( empty( $record['files'] ) ) {
			return;
		}
		$base = wp_normalize_path( trailingslashit( wp_get_upload_dir()['basedir'] ) . self::UPLOAD_SUBDIR . '/' );
		foreach ( $record['files'] as $f ) {
			$path = wp_normalize_path( (string) $f['file'] );
			// Only ever delete inside our own upload folder.
			if ( 0 === strpos( $path, $base ) && file_exists( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Emails
	 * ------------------------------------------------------------------ */

	/**
	 * HTML summary table used in both emails and the admin lead screen.
	 */
	public static function summary_html( array $r, $with_contact = true ) {
		$q    = $r['quote'];
		$rows = array(
			'Estimate no.' => '#' . $r['est_no'] . ' · ' . $r['est_date'],
			'Move type'    => $q['move_type'],
			'Size'         => self::SIZES[ $q['size'] ],
			'From'         => $q['from_addr'] . ' (' . $q['from_access'] . ')',
			'To'           => $q['to_addr'] . ' (' . $q['to_access'] . ')',
			'Date'         => $q['move_date'] . ' · ' . $q['flex'],
			'Crew'         => $q['crew'] . ' movers',
			'Full packing' => $q['pack'] ? 'Yes' : 'No',
			'Materials'    => $q['supplies'] ? 'Yes' : 'No',
		);
		if ( $q['specialty'] ) {
			$rows['Specialty'] = implode( ', ', $q['specialty'] );
		}
		if ( $with_contact ) {
			$rows = array(
				'Name'  => $q['name'],
				'Phone' => $q['phone'],
				'Email' => $q['email'],
			) + $rows;
		}

		$td   = 'style="padding:8px 12px;border-bottom:1px solid #e2e9ef;vertical-align:top"';
		$html = '<table cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;border-collapse:collapse;font:14px/1.45 Arial,sans-serif;color:#0b1620">';
		foreach ( $rows as $k => $v ) {
			if ( '' === (string) $v ) {
				continue;
			}
			$html .= '<tr><td ' . $td . ' width="130"><b>' . esc_html( $k ) . '</b></td><td ' . $td . '>' . esc_html( $v ) . '</td></tr>';
		}
		$html .= '</table>';

		if ( $r['view'] ) {
			$html .= '<table cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;border-collapse:collapse;margin-top:16px;font:14px/1.45 Arial,sans-serif;color:#0b1620">';
			foreach ( $r['view']['rows'] as $row ) {
				$html .= '<tr><td ' . $td . '>' . esc_html( $row[0] ) . '<br><span style="color:#6b7c88;font-size:12px">' . esc_html( $row[1] ) . '</span></td><td ' . $td . ' align="right"><b>' . esc_html( $row[2] ) . '</b></td></tr>';
			}
			$html .= '<tr><td style="padding:12px;background:#0c3a52;color:#fff"><b>Estimated total</b></td><td style="padding:12px;background:#0c3a52;color:#fff" align="right"><b>' . esc_html( $r['view']['total'] ) . '</b></td></tr></table>';
		} else {
			$html .= '<p style="font:14px/1.5 Arial,sans-serif;color:#0b1620;margin-top:16px"><b>Custom quote needed</b> — long-distance and commercial moves are priced individually.</p>';
		}
		return $html;
	}

	private static function email_admin( array $r, $post_id ) {
		$to = get_option( JMK_Leads::OPT_EMAIL );
		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		if ( ! is_email( $to ) ) {
			return;
		}
		$q    = $r['quote'];
		$body = self::summary_html( $r );
		if ( $r['files'] ) {
			$body .= '<p style="font:14px Arial,sans-serif"><b>Photos / files:</b><br>';
			foreach ( $r['files'] as $f ) {
				$body .= '<a href="' . esc_url( $f['url'] ) . '">' . esc_html( $f['name'] ) . '</a><br>';
			}
			$body .= '</p>';
		}
		$body .= '<p style="font:14px Arial,sans-serif"><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ) ) . '">Open in WordPress</a></p>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( '' !== $q['email'] ) {
			$headers[] = 'Reply-To: ' . self::header_safe( $q['name'] ) . ' <' . $q['email'] . '>';
		}
		wp_mail(
			$to,
			/* translators: 1: estimate number, 2: customer name, 3: move size */
			sprintf( __( 'Quote #%1$d: %2$s – %3$s', 'jmk' ), $r['est_no'], $q['name'], self::SIZES[ $q['size'] ] ),
			$body,
			$headers
		);
	}

	private static function email_customer( array $r ) {
		$co   = self::company();
		$q    = $r['quote'];

		// Stop the form from being used to flood someone else's inbox.
		$cap_key = 'jmk_mailcap_' . md5( strtolower( $q['email'] ) );
		$sent    = (int) get_transient( $cap_key );
		if ( $sent >= self::MAIL_CAP ) {
			return false;
		}
		set_transient( $cap_key, $sent + 1, HOUR_IN_SECONDS );

		$body = '<div style="font:15px/1.55 Arial,sans-serif;color:#0b1620;max-width:560px">'
			. '<p>' . esc_html( sprintf( /* translators: %s: first name */ __( 'Hi %s,', 'jmk' ), strtok( $q['name'], ' ' ) ) ) . '</p>'
			. '<p>' . esc_html(
				$r['instant']
					? __( 'Thanks for requesting a quote. Here is your moving estimate — a coordinator will contact you shortly to confirm the details.', 'jmk' )
					: __( 'Thanks for your request. Long-distance and commercial moves are priced individually, so a coordinator will contact you shortly with your detailed quote.', 'jmk' )
			) . '</p></div>'
			. self::summary_html( $r, false );
		if ( $r['instant'] ) {
			$body .= '<p style="font:13px/1.55 Arial,sans-serif;color:#6b7c88;max-width:560px">' . esc_html__( 'Local moves are billed by the hour, so the final price depends on the actual time on move day. This range covers the typical span for a move your size.', 'jmk' ) . '</p>';
		}
		$body .= '<p style="font:14px/1.55 Arial,sans-serif;color:#0b1620">' . esc_html( $co['name'] ) . '<br>' . esc_html( $co['phone'] ) . ' · ' . esc_html( $co['email'] ) . '<br>' . esc_html( $co['site'] ) . '</p>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $co['email'] ) ) {
			$headers[] = 'Reply-To: ' . self::header_safe( $co['name'] ) . ' <' . $co['email'] . '>';
		}
		return (bool) wp_mail(
			$q['email'],
			/* translators: 1: company name, 2: estimate number */
			sprintf( __( 'Your %1$s moving estimate #%2$d', 'jmk' ), $co['name'], $r['est_no'] ),
			$body,
			$headers
		);
	}

	private static function header_safe( $s ) {
		return trim( str_replace( array( "\r", "\n", '<', '>', '"', ',' ), '', $s ) );
	}

	/* ------------------------------------------------------------------
	 * Admin: settings screen
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page(
			JMK_Admin::SLUG,
			__( 'Quote Builder', 'jmk' ),
			__( 'Quote Builder', 'jmk' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$p  = self::pricing();
		$co = self::company();
		$co_raw = get_option( self::OPT_COMPANY );
		$num = static function ( $name, $value, $step = '1' ) {
			printf( '<input type="number" min="0" step="%s" name="%s" value="%s" class="small-text">', esc_attr( $step ), esc_attr( $name ), esc_attr( $value ) );
		};
		$specialty_lines = array();
		foreach ( $p['specialty'] as $k => $v ) {
			$specialty_lines[] = $k . ' | ' . $v;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Quote Builder', 'jmk' ); ?></h1>
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'jmk' ); ?></p></div>
			<?php endif; ?>

			<div class="card" style="max-width:900px">
				<h2><?php esc_html_e( 'Add it to a page', 'jmk' ); ?></h2>
				<p><?php esc_html_e( 'Elementor: drag the "JM Quote Builder" widget (Just Move DFW category). Anywhere else: use the shortcode', 'jmk' ); ?> <code>[<?php echo esc_html( self::SHORTCODE ); ?>]</code></p>
				<p><?php esc_html_e( 'Every submission is saved under Just Move Kit → Leads (with photos), emailed to you, and — if the customer gave an email — emailed to the customer with their estimate.', 'jmk' ); ?></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jmk_save_quote_settings">
				<?php wp_nonce_field( 'jmk_save_quote_settings' ); ?>

				<h2><?php esc_html_e( 'Pricing', 'jmk' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Local and labor-only moves get an instant estimate from these numbers. Long-distance and commercial moves always get a custom quote.', 'jmk' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Hourly rate incl. truck', 'jmk' ); ?></th><td>
						<?php foreach ( array( 2, 3, 4 ) as $n ) : ?>
							<label style="margin-right:14px"><?php echo (int) $n; ?> <?php esc_html_e( 'movers', 'jmk' ); ?> $<?php $num( "crew_rate[$n]", $p['crew_rate'][ $n ] ); ?>/hr</label>
						<?php endforeach; ?>
					</td></tr>
					<tr><th><?php esc_html_e( 'Travel / trip fee (flat)', 'jmk' ); ?></th><td>$<?php $num( 'travel_fee', $p['travel_fee'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Extra hours for full packing', 'jmk' ); ?></th><td>
						<?php $num( 'pack_hours[0]', $p['pack_hours'][0], '0.25' ); ?> – <?php $num( 'pack_hours[1]', $p['pack_hours'][1], '0.25' ); ?> <?php esc_html_e( 'hrs', 'jmk' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Extra hours per location with stairs', 'jmk' ); ?></th><td><?php $num( 'stairs_hrs', $p['stairs_hrs'], '0.25' ); ?> <?php esc_html_e( 'hrs (added to the high end)', 'jmk' ); ?></td></tr>
				</table>

				<table class="widefat striped" style="max-width:900px">
					<thead><tr>
						<th><?php esc_html_e( 'Move size', 'jmk' ); ?></th>
						<th><?php esc_html_e( 'Labor hours (low – high)', 'jmk' ); ?></th>
						<th><?php esc_html_e( 'Recommended crew', 'jmk' ); ?></th>
						<th><?php esc_html_e( 'Packing materials $', 'jmk' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( self::SIZES as $k => $label ) : ?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td><?php $num( "hours[$k][0]", $p['hours'][ $k ][0], '0.25' ); ?> – <?php $num( "hours[$k][1]", $p['hours'][ $k ][1], '0.25' ); ?></td>
							<td><select name="crew_rec[<?php echo esc_attr( $k ); ?>]">
								<?php foreach ( array( 2, 3, 4 ) as $n ) : ?>
									<option value="<?php echo (int) $n; ?>" <?php selected( (int) $p['crew_rec'][ $k ], $n ); ?>><?php echo (int) $n; ?></option>
								<?php endforeach; ?>
							</select></td>
							<td><?php $num( "supplies[$k]", $p['supplies'][ $k ] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Specialty items', 'jmk' ); ?></th><td>
						<textarea name="specialty" rows="6" class="large-text code"><?php echo esc_textarea( implode( "\n", $specialty_lines ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line: Name | price. These appear as options in the builder.', 'jmk' ); ?></p>
					</td></tr>
				</table>

				<h2><?php esc_html_e( 'Company details', 'jmk' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Shown in the builder header, on the PDF estimate and in customer emails.', 'jmk' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$fields = array(
						'name'     => __( 'Company name', 'jmk' ),
						'tagline'  => __( 'Tagline', 'jmk' ),
						'phone'    => __( 'Phone', 'jmk' ),
						'email'    => __( 'Email', 'jmk' ),
						'site'     => __( 'Website', 'jmk' ),
						'location' => __( 'Location', 'jmk' ),
						'logo'     => __( 'Logo URL (JPG or PNG)', 'jmk' ),
					);
					foreach ( $fields as $k => $label ) :
						$val = 'logo' === $k ? ( is_array( $co_raw ) && isset( $co_raw['logo'] ) ? $co_raw['logo'] : '' ) : $co[ $k ];
						?>
						<tr><th><label for="jmk-co-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="text" class="regular-text" id="jmk-co-<?php echo esc_attr( $k ); ?>" name="company[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $val ); ?>">
							<?php if ( 'logo' === $k ) : ?>
								<p class="description"><?php esc_html_e( 'Leave empty to use the bundled Just Move DFW logo. Use a JPG or PNG from your Media Library so it also appears on the PDF.', 'jmk' ); ?></p>
							<?php endif; ?></td></tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Emails', 'jmk' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Customer copy', 'jmk' ); ?></th><td>
						<label><input type="checkbox" name="email_customer" value="1" <?php checked( (bool) get_option( self::OPT_EMAIL_CUS, '1' ) ); ?>>
						<?php esc_html_e( 'Email the estimate to the customer when they give an email address', 'jmk' ); ?></label>
						<p class="description"><?php esc_html_e( 'Your notification address is set on the Just Move Kit screen. Tip: use an SMTP plugin (e.g. WP Mail SMTP) so emails reliably reach inboxes.', 'jmk' ); ?></p>
					</td></tr>
				</table>

				<?php submit_button( __( 'Save quote settings', 'jmk' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jmk' ), 403 );
		}
		check_admin_referer( 'jmk_save_quote_settings' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- every value is cast below.
		$in  = wp_unslash( $_POST );
		$def = self::default_pricing();
		$f   = static function ( $v, $fallback ) {
			return is_numeric( $v ) && $v >= 0 ? (float) $v : (float) $fallback;
		};

		$p = array(
			'travel_fee' => $f( $in['travel_fee'] ?? null, $def['travel_fee'] ),
			'stairs_hrs' => $f( $in['stairs_hrs'] ?? null, $def['stairs_hrs'] ),
			'pack_hours' => array(
				$f( $in['pack_hours'][0] ?? null, $def['pack_hours'][0] ),
				$f( $in['pack_hours'][1] ?? null, $def['pack_hours'][1] ),
			),
		);
		foreach ( array( 2, 3, 4 ) as $n ) {
			$p['crew_rate'][ $n ] = $f( $in['crew_rate'][ $n ] ?? null, $def['crew_rate'][ $n ] );
		}
		foreach ( array_keys( self::SIZES ) as $k ) {
			$lo = $f( $in['hours'][ $k ][0] ?? null, $def['hours'][ $k ][0] );
			$hi = $f( $in['hours'][ $k ][1] ?? null, $def['hours'][ $k ][1] );
			$p['hours'][ $k ]    = array( min( $lo, $hi ), max( $lo, $hi ) );
			$rec                 = (int) ( $in['crew_rec'][ $k ] ?? 0 );
			$p['crew_rec'][ $k ] = in_array( $rec, array( 2, 3, 4 ), true ) ? $rec : $def['crew_rec'][ $k ];
			$p['supplies'][ $k ] = $f( $in['supplies'][ $k ] ?? null, $def['supplies'][ $k ] );
		}
		if ( $p['pack_hours'][0] > $p['pack_hours'][1] ) {
			$p['pack_hours'] = array( $p['pack_hours'][1], $p['pack_hours'][0] );
		}
		$p['specialty'] = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $in['specialty'] ?? '' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			$name  = sanitize_text_field( $parts[0] );
			if ( '' !== $name && isset( $parts[1] ) && is_numeric( $parts[1] ) ) {
				$p['specialty'][ $name ] = max( 0, (float) $parts[1] );
			}
		}
		update_option( self::OPT_PRICING, $p );

		$company = array();
		foreach ( array( 'name', 'tagline', 'phone', 'site', 'location' ) as $k ) {
			$company[ $k ] = sanitize_text_field( $in['company'][ $k ] ?? '' );
		}
		$company['email'] = sanitize_email( $in['company']['email'] ?? '' );
		$company['logo']  = esc_url_raw( $in['company']['logo'] ?? '' );
		update_option( self::OPT_COMPANY, array_filter( $company, 'strlen' ) );

		update_option( self::OPT_EMAIL_CUS, empty( $in['email_customer'] ) ? '0' : '1' );
		// phpcs:enable

		wp_safe_redirect( add_query_arg( array( 'page' => self::SETTINGS_SLUG, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
}

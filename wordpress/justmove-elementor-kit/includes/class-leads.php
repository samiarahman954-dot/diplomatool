<?php
/**
 * Quote-form leads: AJAX endpoint, private "jmk_lead" post type, email alert.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class JMK_Leads {

	const ACTION    = 'jmk_submit_lead';
	const POST_TYPE = 'jmk_lead';
	const OPT_EMAIL = 'jmk_lead_email';

	/** Max submissions per visitor in RATE_WINDOW seconds. */
	const RATE_MAX    = 5;
	const RATE_WINDOW = 600;

	const FIELDS = array(
		'phone'    => 'Phone',
		'from_zip' => 'From ZIP',
		'to_zip'   => 'To ZIP',
		'size'     => 'Move size',
		'source'   => 'Source',
		'page_url' => 'Page',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_box' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Leads', 'jmk' ),
					'singular_name' => __( 'Lead', 'jmk' ),
					'all_items'     => __( 'Leads', 'jmk' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => JMK_Admin::SLUG,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	public static function handle() {
		// Honeypot: bots fill every field.
		if ( ! empty( $_POST['website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_send_json_success();
		}

		$ip_key = 'jmk_rl_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$hits   = (int) get_transient( $ip_key );
		if ( $hits >= self::RATE_MAX ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please call us instead.', 'jmk' ) ), 429 );
		}

		// Public lead form on cacheable pages: a nonce would go stale in page caches,
		// so spam is handled by the honeypot and rate limit above.
		// phpcs:disable WordPress.Security.NonceVerification
		$lead = array(
			'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'phone'    => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'from_zip' => isset( $_POST['from_zip'] ) ? sanitize_text_field( wp_unslash( $_POST['from_zip'] ) ) : '',
			'to_zip'   => isset( $_POST['to_zip'] ) ? sanitize_text_field( wp_unslash( $_POST['to_zip'] ) ) : '',
			'size'     => isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : '',
			'source'   => isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '',
			'page_url' => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '',
		);
		// phpcs:enable

		if ( '' === $lead['name'] || '' === $lead['phone'] ) {
			wp_send_json_error( array( 'message' => __( 'Please add your name and phone so we can send your quote.', 'jmk' ) ), 400 );
		}
		foreach ( $lead as $k => $v ) {
			$lead[ $k ] = mb_substr( $v, 0, 200 );
		}

		set_transient( $ip_key, $hits + 1, self::RATE_WINDOW );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $lead['name'],
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please call us instead.', 'jmk' ) ), 500 );
		}
		foreach ( self::FIELDS as $k => $label ) {
			update_post_meta( $post_id, '_jmk_' . $k, $lead[ $k ] );
		}

		self::notify( $lead, $post_id );

		/**
		 * Fires after a quote lead is stored. Hook CRMs / SMS here.
		 *
		 * @param array $lead    Sanitized lead fields.
		 * @param int   $post_id Lead post ID.
		 */
		do_action( 'jmk_lead_received', $lead, $post_id );

		wp_send_json_success();
	}

	private static function notify( array $lead, $post_id ) {
		$to = get_option( self::OPT_EMAIL, get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}
		$lines = array( 'Name: ' . $lead['name'] );
		foreach ( self::FIELDS as $k => $label ) {
			if ( '' !== $lead[ $k ] ) {
				$lines[] = $label . ': ' . $lead[ $k ];
			}
		}
		$lines[] = '';
		$lines[] = admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );

		wp_mail(
			$to,
			/* translators: %s: lead name */
			sprintf( __( 'New moving quote request: %s', 'jmk' ), $lead['name'] ),
			implode( "\n", $lines )
		);
	}

	public static function columns( $cols ) {
		return array(
			'cb'         => $cols['cb'],
			'title'      => __( 'Name', 'jmk' ),
			'jmk_phone'  => __( 'Phone', 'jmk' ),
			'jmk_route'  => __( 'From → To', 'jmk' ),
			'jmk_size'   => __( 'Move size', 'jmk' ),
			'date'       => $cols['date'],
		);
	}

	public static function column( $col, $post_id ) {
		switch ( $col ) {
			case 'jmk_phone':
				$phone = get_post_meta( $post_id, '_jmk_phone', true );
				printf( '<a href="%s">%s</a>', esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $phone ) ), esc_html( $phone ) );
				break;
			case 'jmk_route':
				echo esc_html( trim( get_post_meta( $post_id, '_jmk_from_zip', true ) . ' → ' . get_post_meta( $post_id, '_jmk_to_zip', true ), ' →' ) );
				break;
			case 'jmk_size':
				echo esc_html( get_post_meta( $post_id, '_jmk_size', true ) );
				break;
		}
	}

	public static function meta_box() {
		add_meta_box(
			'jmk_lead_details',
			__( 'Lead details', 'jmk' ),
			static function ( $post ) {
				echo '<table class="widefat striped"><tbody>';
				foreach ( self::FIELDS as $k => $label ) {
					printf(
						'<tr><th style="width:140px">%s</th><td>%s</td></tr>',
						esc_html( $label ),
						esc_html( get_post_meta( $post->ID, '_jmk_' . $k, true ) )
					);
				}
				echo '</tbody></table>';
			},
			self::POST_TYPE,
			'normal',
			'high'
		);
	}
}

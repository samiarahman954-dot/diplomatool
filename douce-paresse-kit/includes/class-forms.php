<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the kit forms: validation against the widget's own settings, anti-spam (DPK\Spam),
 * e-mail, and a stored copy under Douce Paresse → Messages.
 */
final class Forms {

	const CPT = 'dpk_message';

	/** Maximum length per field type (characters). */
	const MAX_TEXT = 300;
	const MAX_AREA = 5000;

	/** @var Forms|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'wp_ajax_dpk_form', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_dpk_form', array( $this, 'handle' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'views_edit-' . self::CPT, array( $this, 'views' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_list' ) );
	}

	public function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Messages', 'douce-paresse-kit' ),
					'singular_name' => __( 'Message', 'douce-paresse-kit' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Admin::SLUG,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Messages list
	 * ------------------------------------------------------------------ */

	public function columns( $cols ) {
		$date = isset( $cols['date'] ) ? $cols['date'] : null;
		unset( $cols['date'] );
		$cols['dpk_status'] = __( 'Status', 'douce-paresse-kit' );
		if ( $date ) {
			$cols['date'] = $date;
		}
		return $cols;
	}

	public function column( $col, $id ) {
		if ( 'dpk_status' !== $col ) {
			return;
		}
		$spam = get_post_meta( $id, '_dpk_spam', true );
		if ( $spam ) {
			echo '<strong style="color:#b32d2e">' . esc_html__( 'Spam', 'douce-paresse-kit' ) . '</strong> — ' . esc_html( $spam );
			return;
		}
		echo get_post_meta( $id, '_dpk_mailed', true )
			? esc_html__( 'E-mail sent', 'douce-paresse-kit' )
			: '<span style="color:#996800">' . esc_html__( 'E-mail not sent (copy saved here)', 'douce-paresse-kit' ) . '</span>';
	}

	public function views( $views ) {
		$count = count(
			get_posts(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'any',
					'meta_key'    => '_dpk_spam', // phpcs:ignore WordPress.DB.SlowDBQuery
					'fields'      => 'ids',
					'numberposts' => -1,
				)
			)
		);
		$url           = add_query_arg( array( 'post_type' => self::CPT, 'dpk_spam' => 1 ), admin_url( 'edit.php' ) );
		$current       = ! empty( $_GET['dpk_spam'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views['spam'] = '<a href="' . esc_url( $url ) . '"' . ( $current ? ' class="current"' : '' ) . '>' . esc_html__( 'Spam', 'douce-paresse-kit' ) . ' <span class="count">(' . (int) $count . ')</span></a>';
		return $views;
	}

	public function filter_list( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || self::CPT !== $q->get( 'post_type' ) ) {
			return;
		}
		$spam = ! empty( $_GET['dpk_spam'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$q->set(
			'meta_query',
			array(
				array(
					'key'     => '_dpk_spam',
					'compare' => $spam ? 'EXISTS' : 'NOT EXISTS',
				),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Submission
	 * ------------------------------------------------------------------ */

	private function find( $elements, $id ) {
		foreach ( (array) $elements as $el ) {
			if ( isset( $el['id'] ) && $el['id'] === $id ) {
				return $el;
			}
			if ( ! empty( $el['elements'] ) ) {
				$found = $this->find( $el['elements'], $id );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	private function fail( $message, $fields = array(), $extra = array() ) {
		wp_send_json_error(
			array_merge(
				array(
					'message' => $message,
					'fields'  => (object) $fields,
				),
				$extra
			)
		);
	}

	private function store( $title, $body, $meta ) {
		$id = wp_insert_post(
			array(
				'post_type'    => self::CPT,
				'post_status'  => 'private',
				'post_title'   => $title,
				'post_content' => $body,
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		return $id;
	}

	public function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form: protected by the signed token in DPK\Spam (a nonce would break with page caching).
		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$widget_id = isset( $_POST['widget_id'] ) ? sanitize_key( wp_unslash( $_POST['widget_id'] ) ) : '';
		if ( ! $post_id || ! $widget_id || ! did_action( 'elementor/loaded' ) ) {
			$this->fail( __( 'Formulaire introuvable.', 'douce-paresse-kit' ) );
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		$element  = $document ? $this->find( $document->get_elements_data(), $widget_id ) : null;
		if ( ! $element || empty( $element['widgetType'] ) || ! in_array( $element['widgetType'], array( 'dpk-pro-form', 'dpk-contact' ), true ) ) {
			$this->fail( __( 'Formulaire introuvable.', 'douce-paresse-kit' ) );
		}
		$widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $element );
		if ( ! $widget ) {
			$this->fail( __( 'Formulaire introuvable.', 'douce-paresse-kit' ) );
		}
		$s = $widget->get_settings_for_display();

		$errors      = array();
		$values      = array();
		$reply_to    = '';
		$email_field = '';
		foreach ( (array) $s['fields'] as $f ) {
			$name  = sanitize_key( ! empty( $f['name'] ) ? $f['name'] : $f['_id'] );
			$type  = isset( $f['type'] ) ? $f['type'] : 'text';
			$raw   = isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$value = 'textarea' === $type ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			$max   = 'textarea' === $type ? self::MAX_AREA : self::MAX_TEXT;
			$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );

			if ( $len > $max ) {
				$errors[ $name ] = __( 'Texte trop long.', 'douce-paresse-kit' );
			} elseif ( ! empty( $f['required'] ) && 'yes' === $f['required'] && '' === trim( $value ) ) {
				$errors[ $name ] = __( 'Ce champ est requis.', 'douce-paresse-kit' );
			} elseif ( 'email' === $type && '' !== $value ) {
				if ( ! is_email( $value ) ) {
					$errors[ $name ] = __( 'Adresse e-mail invalide.', 'douce-paresse-kit' );
				} elseif ( ! $reply_to ) {
					$reply_to    = $value;
					$email_field = $name;
				}
			} elseif ( 'select' === $type && '' !== $value ) {
				$options = array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $f['options'] ) );
				if ( ! in_array( $value, $options, true ) ) {
					$errors[ $name ] = __( 'Veuillez choisir une option.', 'douce-paresse-kit' );
				}
			} elseif ( 'number' === $type && '' !== $value && ! is_numeric( $value ) ) {
				$errors[ $name ] = __( 'Merci d’indiquer un nombre.', 'douce-paresse-kit' );
			}
			$values[] = array(
				'key'   => $name,
				'label' => $f['label'],
				'type'  => $type,
				'value' => $value,
			);
		}
		if ( $errors ) {
			$this->fail( __( 'Merci de vérifier les champs indiqués.', 'douce-paresse-kit' ), $errors );
		}

		// Anti-spam.
		$request = array();
		foreach ( array( 'dpk_hp', 'dpk_token', 'cf-turnstile-response' ) as $k ) {
			$request[ $k ] = isset( $_POST[ $k ] ) && is_string( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
		}
		// phpcs:enable
		$verdict = Spam::check( $values, $reply_to, $request );

		if ( 'error' === $verdict['status'] ) {
			$fields = ! empty( $verdict['field'] ) && $email_field ? array( $email_field => $verdict['message'] ) : array();
			$this->fail( $verdict['message'], $fields, array( 'retry' => ! empty( $verdict['retry'] ), 'reason' => $verdict['reason'] ) );
		}
		Spam::burn_token( $request['dpk_token'] );

		$subject = ! empty( $s['email_subject'] ) ? $s['email_subject'] : __( 'Nouveau message', 'douce-paresse-kit' );
		$lines   = array();
		foreach ( $values as $v ) {
			$lines[] = $v['label'] . ' : ' . ( '' === $v['value'] ? '—' : $v['value'] );
		}
		$lines[] = '';
		$lines[] = __( 'Page :', 'douce-paresse-kit' ) . ' ' . get_permalink( $post_id );
		$body    = implode( "\n", $lines );
		$title   = $subject . ( $reply_to ? ' — ' . $reply_to : '' );

		if ( 'duplicate' === $verdict['status'] ) {
			wp_send_json_success(); // Already received: do not e-mail twice.
		}
		if ( 'spam' === $verdict['status'] ) {
			if ( 'yes' === Spam::get()['keep_spam'] ) {
				$this->store(
					'[Spam] ' . $title,
					$body,
					array(
						'_dpk_spam' => $verdict['reason'],
						'_dpk_ip'   => Spam::client_ip(),
					)
				);
			}
			wp_send_json_success(); // The bot is told it worked and learns nothing.
		}

		$message_id = $this->store( $title, $body, array() );
		$to         = ! empty( $s['recipient'] ) && is_email( $s['recipient'] ) ? $s['recipient'] : get_option( 'admin_email' );
		$headers    = $reply_to ? array( 'Reply-To: ' . $reply_to ) : array();
		$sent       = wp_mail( $to, $subject, $body, $headers );
		if ( $message_id ) {
			update_post_meta( $message_id, '_dpk_mailed', $sent ? 1 : 0 );
		}
		if ( ! $sent && ! $message_id ) {
			$this->fail( __( 'Une erreur est survenue. Merci de réessayer.', 'douce-paresse-kit' ) );
		}
		wp_send_json_success();
	}
}

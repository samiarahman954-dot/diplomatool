<?php
namespace DPK;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the kit forms: validation against the widget's own settings, e-mail, and a stored copy.
 */
final class Forms {

	const CPT = 'dpk_message';

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

	private function fail( $message, $fields = array() ) {
		wp_send_json_error(
			array(
				'message' => $message,
				'fields'  => (object) $fields,
			)
		);
	}

	public function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified just below.
		if ( ! check_ajax_referer( 'dpk_form', 'nonce', false ) ) {
			$this->fail( __( 'La session a expiré. Merci de recharger la page.', 'douce-paresse-kit' ) );
		}
		if ( ! empty( $_POST['dpk_hp'] ) ) {
			wp_send_json_success(); // Bot: pretend everything went fine.
		}

		$ip_key = 'dpk_rl_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$hits   = (int) get_transient( $ip_key );
		if ( $hits >= 10 ) {
			$this->fail( __( 'Trop de messages envoyés. Merci de réessayer plus tard.', 'douce-paresse-kit' ) );
		}

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

		$errors   = array();
		$values   = array();
		$reply_to = '';
		foreach ( (array) $s['fields'] as $f ) {
			$name  = sanitize_key( ! empty( $f['name'] ) ? $f['name'] : $f['_id'] );
			$type  = isset( $f['type'] ) ? $f['type'] : 'text';
			$raw   = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$value = 'textarea' === $type ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );

			if ( ! empty( $f['required'] ) && 'yes' === $f['required'] && '' === trim( $value ) ) {
				$errors[ $name ] = __( 'Ce champ est requis.', 'douce-paresse-kit' );
			} elseif ( 'email' === $type && '' !== $value ) {
				if ( ! is_email( $value ) ) {
					$errors[ $name ] = __( 'Adresse e-mail invalide.', 'douce-paresse-kit' );
				} elseif ( ! $reply_to ) {
					$reply_to = $value;
				}
			} elseif ( 'select' === $type && '' !== $value ) {
				$options = array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $f['options'] ) );
				if ( ! in_array( $value, $options, true ) ) {
					$errors[ $name ] = __( 'Veuillez choisir une option.', 'douce-paresse-kit' );
				}
			}
			$values[] = array(
				'label' => $f['label'],
				'value' => $value,
			);
		}
		// phpcs:enable

		if ( $errors ) {
			$this->fail( __( 'Merci de vérifier les champs indiqués.', 'douce-paresse-kit' ), $errors );
		}

		set_transient( $ip_key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		$subject = ! empty( $s['email_subject'] ) ? $s['email_subject'] : __( 'Nouveau message', 'douce-paresse-kit' );
		$lines   = array();
		foreach ( $values as $v ) {
			$lines[] = $v['label'] . ' : ' . ( '' === $v['value'] ? '—' : $v['value'] );
		}
		$lines[] = '';
		$lines[] = __( 'Page :', 'douce-paresse-kit' ) . ' ' . get_permalink( $post_id );
		$body    = implode( "\n", $lines );

		$message_id = wp_insert_post(
			array(
				'post_type'    => self::CPT,
				'post_status'  => 'private',
				'post_title'   => $subject . ( $reply_to ? ' — ' . $reply_to : '' ),
				'post_content' => $body,
			)
		);

		$to = ! empty( $s['recipient'] ) && is_email( $s['recipient'] ) ? $s['recipient'] : get_option( 'admin_email' );
		$headers = array();
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		$sent = wp_mail( $to, $subject, $body, $headers );
		if ( $message_id && ! is_wp_error( $message_id ) ) {
			update_post_meta( $message_id, '_dpk_mailed', $sent ? 1 : 0 );
		}

		if ( ! $sent && ( ! $message_id || is_wp_error( $message_id ) ) ) {
			$this->fail( __( 'Une erreur est survenue. Merci de réessayer.', 'douce-paresse-kit' ) );
		}
		wp_send_json_success();
	}
}

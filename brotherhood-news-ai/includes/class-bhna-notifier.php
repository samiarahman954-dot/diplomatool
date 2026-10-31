<?php
/**
 * Editor alerts (email and the private Telegram group) and error alerts for the administrator.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Notifier {

	/** The same error is reported at most once per this period, so a feed that stays down doesn't spam. */
	const ERROR_THROTTLE = HOUR_IN_SECONDS;

	public static function draft_ready( $post_id, array $item, array $missing ) {
		$title     = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
		$edit_link = admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
		$warning   = $missing ? 'Revisar datos: no aparecen en el texto reescrito: ' . implode( ', ', $missing ) : '';

		if ( BHNA_Settings::get( 'notify_email' ) ) {
			$lines = array(
				'Hay un nuevo borrador listo para revisar.',
				'',
				'Titular: ' . $title,
				'Fuente: ' . $item['source_name'] . ' – ' . $item['source_url'],
				$warning ? '⚠ ' . $warning : '',
				'',
				'Editar: ' . $edit_link,
			);
			self::email( '[Borrador nuevo] ' . $title, implode( "\n", array_filter( $lines, 'is_string' ) ) );
		}

		if ( BHNA_Settings::get( 'telegram_enabled' ) ) {
			$text = "📰 <b>Nuevo borrador para revisar</b>\n\n"
				. '<b>' . esc_html( $title ) . "</b>\n"
				. 'Fuente: ' . esc_html( $item['source_name'] ) . "\n"
				. ( $warning ? '⚠️ ' . esc_html( $warning ) . "\n" : '' )
				. "\n" . '<a href="' . esc_url( $edit_link ) . '">Abrir en WordPress</a>';
			self::telegram( $text );
		}

		if ( BHNA_Settings::get( 'slack_enabled' ) ) {
			$text = ':newspaper: *Nuevo borrador para revisar*\n*' . self::slack_escape( $title ) . '*\nFuente: ' . self::slack_escape( $item['source_name'] )
				. ( $warning ? "\n:warning: " . self::slack_escape( $warning ) : '' )
				. "\n<" . $edit_link . '|Abrir en WordPress>';
			self::slack( $text );
		}
	}

	public static function error_alert( $key, $message ) {
		if ( ! BHNA_Settings::get( 'error_alerts' ) ) {
			return;
		}
		$transient = 'bhna_err_' . md5( $key );
		if ( get_transient( $transient ) ) {
			return;
		}
		set_transient( $transient, 1, self::ERROR_THROTTLE );

		wp_mail( get_option( 'admin_email' ), '[Brotherhood News AI] Error', $message . "\n\nRevisa el registro en WordPress: " . admin_url( 'admin.php?page=bhna-log' ) );

		if ( BHNA_Settings::get( 'telegram_enabled' ) ) {
			self::telegram( "🚨 <b>Error en Brotherhood News AI</b>\n\n" . esc_html( $message ) );
		}
		if ( BHNA_Settings::get( 'slack_enabled' ) ) {
			self::slack( ":rotating_light: *Error en Brotherhood News AI*\n" . self::slack_escape( $message ) );
		}
	}

	/** Slack mrkdwn: only &, < and > must be escaped. */
	public static function slack_escape( $text ) {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), (string) $text );
	}

	/**
	 * Posts to a Slack incoming webhook.
	 *
	 * @return true|WP_Error
	 */
	public static function slack( $text ) {
		$webhook = trim( (string) BHNA_Settings::get( 'slack_webhook' ) );
		if ( 0 !== strpos( $webhook, 'https://hooks.slack.com/' ) ) {
			return new WP_Error( 'bhna_slack_config', 'Falta la URL del webhook de Slack (https://hooks.slack.com/…).' );
		}
		$response = wp_remote_post(
			$webhook,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'text' => $text ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			BHNA_Logger::error( 'Slack: ' . $response->get_error_message() );
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$message = trim( wp_remote_retrieve_body( $response ) );
			BHNA_Logger::error( 'Slack: ' . $message );
			return new WP_Error( 'bhna_slack', $message ? $message : 'respuesta inesperada' );
		}
		return true;
	}

	public static function email( $subject, $body ) {
		$recipients = array_filter( array_map( 'trim', explode( ',', (string) BHNA_Settings::get( 'email_recipients' ) ) ), 'is_email' );
		if ( ! $recipients ) {
			return false;
		}
		return wp_mail( $recipients, $subject, $body );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function telegram( $html, $chat_id = null, $preview = false ) {
		$token   = trim( (string) BHNA_Settings::get( 'telegram_bot_token' ) );
		$chat_id = null === $chat_id ? trim( (string) BHNA_Settings::get( 'telegram_chat_id' ) ) : $chat_id;
		if ( '' === $token || '' === $chat_id ) {
			return new WP_Error( 'bhna_telegram_config', 'Faltan el token del bot o el ID del chat de Telegram.' );
		}

		$response = wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
			array(
				'timeout' => 15,
				'body'    => array(
					'chat_id'                  => $chat_id,
					'text'                     => $html,
					'parse_mode'               => 'HTML',
					'disable_web_page_preview' => $preview ? 'false' : 'true',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			BHNA_Logger::error( 'Telegram: ' . $response->get_error_message() );
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['ok'] ) ) {
			$message = isset( $data['description'] ) ? $data['description'] : 'respuesta inesperada';
			BHNA_Logger::error( 'Telegram: ' . $message );
			return new WP_Error( 'bhna_telegram', $message );
		}
		return true;
	}
}

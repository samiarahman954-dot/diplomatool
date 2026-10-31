<?php
/**
 * Editor alerts (email and the private Telegram group) and error alerts for the administrator.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Notifier {

	/** The same error is reported at most once per this period, so a feed that stays down doesn't spam. */
	const ERROR_THROTTLE = HOUR_IN_SECONDS;

	public static function draft_ready( $post_id, array $item, array $missing ) {
		$title     = get_the_title( $post_id );
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

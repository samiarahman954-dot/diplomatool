<?php
/**
 * Automatic sharing of published articles: X (Twitter) and the public Telegram channel.
 * Facebook and LinkedIn appear in the settings as prepared networks, to be connected later.
 *
 * Only articles that reach "publish" are shared (never drafts). Sharing runs a few seconds after
 * publishing, in the background, so the editor's "Publish" click is never slowed down.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Social {

	const HOOK        = 'bhna_social_share';
	const X_ENDPOINT  = 'https://api.x.com/2/tweets';
	const X_ME        = 'https://api.x.com/2/users/me';
	const X_MAX_CHARS = 280;
	const X_URL_CHARS = 23; // X counts every link as 23 characters.

	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );
		add_action( self::HOOK, array( __CLASS__, 'share' ) );
	}

	public static function on_status_change( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
			return;
		}
		if ( ! self::any_network_enabled() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK, array( (int) $post->ID ) ) ) {
			wp_schedule_single_event( time() + 30, self::HOOK, array( (int) $post->ID ) );
		}
	}

	public static function any_network_enabled() {
		return BHNA_Settings::get( 'x_enabled' ) || BHNA_Settings::get( 'tg_channel_enabled' );
	}

	public static function should_share( WP_Post $post ) {
		if ( 'publish' !== $post->post_status || get_post_meta( $post->ID, '_bhna_no_share', true ) ) {
			return false;
		}
		// Optionally limit sharing to articles created by the news system.
		return BHNA_Settings::get( 'social_share_all' ) || get_post_meta( $post->ID, '_bhna_item_id', true );
	}

	/** Shares a post on every enabled network it hasn't been shared on yet. */
	public static function share( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! self::should_share( $post ) ) {
			return;
		}

		$networks = array();
		if ( BHNA_Settings::get( 'x_enabled' ) ) {
			$networks['x'] = array( __CLASS__, 'share_x' );
		}
		if ( BHNA_Settings::get( 'tg_channel_enabled' ) ) {
			$networks['telegram'] = array( __CLASS__, 'share_telegram' );
		}

		$retry = false;
		foreach ( $networks as $network => $callback ) {
			if ( get_post_meta( $post->ID, '_bhna_shared_' . $network, true ) ) {
				continue;
			}
			$result = call_user_func( $callback, $post );
			if ( is_wp_error( $result ) ) {
				$attempts = (int) get_post_meta( $post->ID, '_bhna_share_attempts_' . $network, true ) + 1;
				update_post_meta( $post->ID, '_bhna_share_attempts_' . $network, $attempts );
				$message = sprintf( 'No se pudo compartir "%s" en %s: %s', $post->post_title, self::network_label( $network ), $result->get_error_message() );
				if ( $attempts < 3 ) {
					$retry = true;
					BHNA_Logger::warning( $message . ' (se reintentará)' );
				} else {
					BHNA_Logger::error( $message );
					BHNA_Notifier::error_alert( 'share_' . $network, $message );
				}
				continue;
			}
			update_post_meta( $post->ID, '_bhna_shared_' . $network, time() );
			BHNA_Logger::info( sprintf( 'Compartido en %s: "%s"', self::network_label( $network ), $post->post_title ) );
		}

		if ( $retry ) {
			wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, self::HOOK, array( (int) $post->ID ) );
		}
	}

	public static function network_label( $network ) {
		$labels = array( 'x' => 'X (Twitter)', 'telegram' => 'el canal de Telegram' );
		return isset( $labels[ $network ] ) ? $labels[ $network ] : $network;
	}

	/* ------------------------------------------------------------------ */
	/* Message templates                                                   */
	/* ------------------------------------------------------------------ */

	public static function excerpt( WP_Post $post ) {
		$excerpt = $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 35, '…' );
		return trim( html_entity_decode( wp_strip_all_tags( $excerpt ), ENT_QUOTES, 'UTF-8' ) );
	}

	public static function title( WP_Post $post ) {
		return trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Text for X: the template with {titulo}, {extracto} and {enlace}, shortened to fit 280 characters
	 * (the link always counts as 23).
	 */
	public static function x_text( WP_Post $post ) {
		$template = (string) BHNA_Settings::get( 'x_template' );
		$link     = get_permalink( $post );
		$title    = self::title( $post );
		$excerpt  = strpos( $template, '{extracto}' ) !== false ? self::excerpt( $post ) : '';

		$fixed     = str_replace( array( '{titulo}', '{extracto}', '{enlace}' ), '', $template );
		$available = self::X_MAX_CHARS - self::length( $fixed ) - ( strpos( $template, '{enlace}' ) !== false ? self::X_URL_CHARS : 0 );

		// Shorten the excerpt first, then the title.
		$excerpt = self::truncate( $excerpt, max( 0, $available - self::length( $title ) ) );
		$title   = self::truncate( $title, max( 0, $available - self::length( $excerpt ) ) );

		$text = str_replace( array( '{titulo}', '{extracto}', '{enlace}' ), array( $title, $excerpt, $link ), $template );
		return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	/** Telegram channel message (HTML). Telegram builds the preview card with the article's image. */
	public static function telegram_text( WP_Post $post ) {
		$template = (string) BHNA_Settings::get( 'tg_channel_template' );
		$link     = esc_url( get_permalink( $post ) );
		$text     = str_replace(
			array( '{titulo}', '{extracto}', '{enlace}' ),
			array( esc_html( self::title( $post ) ), esc_html( self::excerpt( $post ) ), $link ),
			$template
		);
		return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	public static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	public static function truncate( $text, $max ) {
		if ( self::length( $text ) <= $max ) {
			return $text;
		}
		if ( $max <= 1 ) {
			return '';
		}
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max - 1, 'UTF-8' ) : substr( $text, 0, $max - 1 );
		return rtrim( $cut ) . '…';
	}

	/* ------------------------------------------------------------------ */
	/* Telegram channel                                                    */
	/* ------------------------------------------------------------------ */

	public static function share_telegram( WP_Post $post ) {
		$channel = trim( (string) BHNA_Settings::get( 'tg_channel_id' ) );
		if ( '' === $channel ) {
			return new WP_Error( 'bhna_tg_channel', 'Falta el ID del canal de Telegram.' );
		}
		return BHNA_Notifier::telegram( self::telegram_text( $post ), $channel, true );
	}

	/** Checks that the bot can see the channel, without posting anything. */
	public static function check_telegram_channel() {
		$token   = trim( (string) BHNA_Settings::get( 'telegram_bot_token' ) );
		$channel = trim( (string) BHNA_Settings::get( 'tg_channel_id' ) );
		if ( '' === $token || '' === $channel ) {
			return new WP_Error( 'bhna_tg_config', 'Faltan el token del bot o el ID del canal.' );
		}
		$response = wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/getChat',
			array(
				'timeout' => 15,
				'body'    => array( 'chat_id' => $channel ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['ok'] ) ) {
			return new WP_Error( 'bhna_tg', isset( $data['description'] ) ? $data['description'] : 'respuesta inesperada' );
		}
		return isset( $data['result']['title'] ) ? $data['result']['title'] : $channel;
	}

	/* ------------------------------------------------------------------ */
	/* X (Twitter)                                                         */
	/* ------------------------------------------------------------------ */

	public static function share_x( WP_Post $post ) {
		$response = self::x_request( 'POST', self::X_ENDPOINT, array( 'text' => self::x_text( $post ) ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 201 !== $response['code'] ) {
			return self::x_error( $response );
		}
		return isset( $response['data']['data']['id'] ) ? $response['data']['data']['id'] : true;
	}

	/** Verifies the X credentials (reads the account name, posts nothing). */
	public static function check_x() {
		$response = self::x_request( 'GET', self::X_ME );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== $response['code'] ) {
			return self::x_error( $response );
		}
		return isset( $response['data']['data']['username'] ) ? '@' . $response['data']['data']['username'] : 'cuenta verificada';
	}

	private static function x_error( array $response ) {
		$data    = $response['data'];
		$message = 'HTTP ' . $response['code'];
		if ( isset( $data['detail'] ) ) {
			$message .= ': ' . $data['detail'];
		} elseif ( isset( $data['title'] ) ) {
			$message .= ': ' . $data['title'];
		}
		if ( 429 === $response['code'] ) {
			$message .= ' (límite de publicaciones de X alcanzado)';
		}
		return new WP_Error( 'bhna_x_' . $response['code'], $message );
	}

	/**
	 * Signed request to the X API v2 with OAuth 1.0a user context (the account's own keys).
	 *
	 * @return array|WP_Error {code: int, data: array}
	 */
	public static function x_request( $method, $url, array $json = null ) {
		$keys = array(
			'consumer_key'    => trim( (string) BHNA_Settings::get( 'x_api_key' ) ),
			'consumer_secret' => trim( (string) BHNA_Settings::get( 'x_api_secret' ) ),
			'token'           => trim( (string) BHNA_Settings::get( 'x_access_token' ) ),
			'token_secret'    => trim( (string) BHNA_Settings::get( 'x_access_secret' ) ),
		);
		if ( in_array( '', $keys, true ) ) {
			return new WP_Error( 'bhna_x_config', 'Faltan claves de la API de X en los ajustes.' );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array( 'Authorization' => self::oauth_header( $method, $url, $keys ) ),
		);
		if ( null !== $json ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $json );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'data' => (array) json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	}

	/**
	 * OAuth 1.0a HMAC-SHA1 Authorization header. A JSON body is not part of the signature.
	 */
	public static function oauth_header( $method, $url, array $keys, $nonce = null, $timestamp = null ) {
		$oauth = array(
			'oauth_consumer_key'     => $keys['consumer_key'],
			'oauth_nonce'            => null !== $nonce ? $nonce : wp_generate_password( 32, false, false ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => null !== $timestamp ? (string) $timestamp : (string) time(),
			'oauth_token'            => $keys['token'],
			'oauth_version'          => '1.0',
		);

		$params = $oauth;
		$query  = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $query ) {
			parse_str( $query, $query_params );
			$params = array_merge( $params, $query_params );
		}
		$base_url = strtok( $url, '?' );

		$encoded = array();
		foreach ( $params as $key => $value ) {
			$encoded[ rawurlencode( $key ) ] = rawurlencode( (string) $value );
		}
		ksort( $encoded );
		$pairs = array();
		foreach ( $encoded as $key => $value ) {
			$pairs[] = $key . '=' . $value;
		}

		$base_string = strtoupper( $method ) . '&' . rawurlencode( $base_url ) . '&' . rawurlencode( implode( '&', $pairs ) );
		$signing_key = rawurlencode( $keys['consumer_secret'] ) . '&' . rawurlencode( $keys['token_secret'] );

		$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base_string, $signing_key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$header = array();
		foreach ( $oauth as $key => $value ) {
			$header[] = rawurlencode( $key ) . '="' . rawurlencode( $value ) . '"';
		}
		return 'OAuth ' . implode( ', ', $header );
	}
}

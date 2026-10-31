<?php
/**
 * Plugin settings and feed list, stored as WordPress options.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Settings {

	const OPTION       = 'bhna_settings';
	const FEEDS_OPTION = 'bhna_feeds';

	/** Models offered in the settings screen, with USD prices per million tokens (input, output). */
	const MODELS = array(
		'claude-opus-5-5'   => array( 'label' => 'Claude Opus 5.5 (máxima calidad)', 'in' => 4.00, 'out' => 20.00 ),
		'claude-sonnet-5-5' => array( 'label' => 'Claude Sonnet 5.5 (equilibrado)', 'in' => 2.00, 'out' => 10.00 ),
		'claude-haiku-5-5'  => array( 'label' => 'Claude Haiku 5.5 (económico)', 'in' => 0.10, 'out' => 0.50 ),
	);

	const EFFORTS = array( 'low', 'medium', 'high' );

	const COUNTRIES = array(
		'es'    => 'España',
		'us'    => 'EE. UU.',
		'cu'    => 'Cuba',
		'other' => 'Otro',
	);

	const GROUPS = array(
		'noticias'      => 'Noticias',
		'fraternidades' => 'Fraternidades',
	);

	public static function defaults() {
		return array(
			'api_key'              => '',
			'model'                => 'claude-opus-5-5',
			'effort'               => 'medium',
			'system_prompt'        => self::default_prompt(),
			'post_status'          => 'draft',
			'post_author'          => 0,
			'include_keywords'     => '',
			'exclude_keywords'     => '',
			'daily_limit'          => 30,
			'batch_size'           => 3,
			'interval'             => 30,
			'max_attempts'         => 3,
			'attribution_text'     => 'Con información original de {medio}',
			'attribution_link'     => 1,
			'seo_meta'             => 1,
			'quality_check'        => 1,
			'notify_email'         => 1,
			'email_recipients'     => get_option( 'admin_email' ),
			'telegram_enabled'     => 0,
			'telegram_bot_token'   => '',
			'telegram_chat_id'     => '',
			'error_alerts'         => 1,
			'delete_on_uninstall'  => 0,
		);
	}

	public static function default_prompt() {
		return "Eres un redactor profesional de un periódico digital en español.\n\n"
			. "Recibirás el título y el resumen de una noticia publicada por otro medio. Escribe una noticia breve y original a partir de esa información:\n"
			. "- Redáctala desde cero, con una estructura nueva y tus propias palabras. No copies frases del original.\n"
			. "- Escribe un titular totalmente nuevo.\n"
			. "- Usa SOLO la información del título y el resumen. No inventes datos, cifras, nombres, fechas, citas ni contexto que no aparezcan en ellos.\n"
			. "- Mantén exactos los nombres propios, las cifras y las fechas.\n"
			. "- Tono periodístico, claro y neutral. Extensión: entre 150 y 300 palabras, en 3 a 5 párrafos.\n"
			. "- No menciones el medio original en el texto: la atribución se añade automáticamente.\n"
			. "- El extracto debe tener una o dos frases. La meta descripción, como máximo 155 caracteres.\n"
			. "- Propón entre 2 y 5 etiquetas cortas.";
	}

	public static function get( $key = null ) {
		$settings = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $settings;
		}
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	public static function update( array $values ) {
		$settings = array_merge( self::get(), $values );
		update_option( self::OPTION, $settings, false );
	}

	/** The API key can also be defined in wp-config.php so it never lives in the database. */
	public static function api_key() {
		if ( defined( 'BHNA_ANTHROPIC_API_KEY' ) && BHNA_ANTHROPIC_API_KEY ) {
			return BHNA_ANTHROPIC_API_KEY;
		}
		return (string) self::get( 'api_key' );
	}

	public static function feeds() {
		$feeds = get_option( self::FEEDS_OPTION, null );
		if ( ! is_array( $feeds ) ) {
			$feeds = self::default_feeds();
		}
		return $feeds;
	}

	public static function feed( $feed_id ) {
		foreach ( self::feeds() as $feed ) {
			if ( $feed['id'] === $feed_id ) {
				return $feed;
			}
		}
		return null;
	}

	public static function save_feeds( array $feeds ) {
		update_option( self::FEEDS_OPTION, array_values( $feeds ), false );
	}

	public static function new_feed( array $values = array() ) {
		return wp_parse_args(
			$values,
			array(
				'id'          => 'feed_' . wp_generate_password( 8, false, false ),
				'name'        => '',
				'url'         => '',
				'country'     => 'es',
				'group'       => 'noticias',
				'category_id' => 0,
				'include'     => '',
				'exclude'     => '',
				'enabled'     => 0,
			)
		);
	}

	/** Initial feed list agreed with the client. URLs marked empty must be filled in from the admin. */
	public static function default_feeds() {
		return array(
			self::new_feed( array( 'id' => 'elmundo_portada', 'name' => 'El Mundo', 'url' => 'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml', 'country' => 'es', 'enabled' => 1 ) ),
			self::new_feed( array( 'id' => 'elmundo_economia', 'name' => 'El Mundo', 'url' => 'https://e00-elmundo.uecdn.es/elmundo/rss/economia.xml', 'country' => 'es', 'enabled' => 1 ) ),
			self::new_feed( array( 'id' => 'elmundo_cultura', 'name' => 'El Mundo', 'url' => 'https://e00-elmundo.uecdn.es/elmundo/rss/cultura.xml', 'country' => 'es', 'enabled' => 1 ) ),
			self::new_feed( array( 'id' => 'us_internacional', 'name' => 'Internacional en Español (EE. UU.)', 'url' => '', 'country' => 'us', 'enabled' => 0 ) ),
			self::new_feed( array( 'id' => 'cubadebate_general', 'name' => 'Cubadebate', 'url' => 'https://www.cubadebate.cu/feed/', 'country' => 'cu', 'enabled' => 1 ) ),
			self::new_feed( array( 'id' => 'cubadebate_cultura', 'name' => 'Cubadebate', 'url' => '', 'country' => 'cu', 'enabled' => 0 ) ),
		);
	}

	/** Splits a textarea of keywords (one per line or comma separated) into a clean list. */
	public static function keyword_list( $text ) {
		$parts = preg_split( '/[\r\n,]+/', (string) $text );
		$parts = array_map( 'trim', $parts );
		return array_values( array_unique( array_filter( $parts, 'strlen' ) ) );
	}
}

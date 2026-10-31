<?php
/**
 * Recommended configuration for Brotherhood Herald and the setup checklist.
 *
 * "Apply" fills in what can be filled automatically: feed URLs, WordPress categories (created if
 * missing), feed → category mapping and keyword lists. Credentials (API keys, Telegram, X, Amazon IDs)
 * can only come from the client and stay empty.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Setup {

	/** Words that mark a Cubadebate story as culture/society (client's \"Cultura/Sociedad\"), so the shared feed can be split into two sections. */
	const CULTURE_KEYWORDS = "cultura\nmúsica\nmúsica urbana\nreguetón\nconcierto\narte\nartista\nexposición\ncine\nteatro\ndanza\nliteratura\nlibro\nfestival\nmuseo\nfarándula\nsociedad\nsalud\neducación\nescuela\nuniversidad\nfamilia\njóvenes\nmujeres\ncomunidad\nvivienda\ntransporte";

	/** Topics from the client's editorial lines and example keywords. */
	const INCLUDE_KEYWORDS = "IA\ninteligencia artificial\nsubasta\nreforma\nconcierto\narancel\naranceles\nimportaciones\nexportaciones\ncomercio\neconomía\ninflación\nempresas\ninversión\nciberseguridad\ntecnología\ngadget\nmóvil\nciencia\nenergía solar\nfotovoltaica\nenergías renovables\nmúsica\nmúsica urbana\narte\ncultura\ncine\nfestival\nCongreso\nSenado\nDiputados\nley\nelecciones\nGobierno\nCuba\nLa Habana\nCaribe\nEstados Unidos\nmasonería\nfraternidad\nfilantropía";

	const EXCLUDE_KEYWORDS = "horóscopo\nlotería\nsorteo\nesquela\nobituario\nfútbol\nbaloncesto\nLaLiga\npatrocinado\npublirreportaje\nreceta";

	/**
	 * Feeds in priority order: when the same story appears in several feeds, the first one that
	 * accepts it wins. That's why sections (Economía, Cultura) come before the front page.
	 */
	public static function recommended_feeds() {
		return array(
			array( 'id' => 'elmundo_economia', 'name' => 'El Mundo', 'url' => 'https://e00-elmundo.uecdn.es/elmundo/rss/economia.xml', 'country' => 'es', 'category' => 'Economía', 'include' => '' ),
			array( 'id' => 'elmundo_cultura', 'name' => 'El Mundo', 'url' => 'https://e00-elmundo.uecdn.es/elmundo/rss/cultura.xml', 'country' => 'es', 'category' => 'Cultura', 'include' => '' ),
			array( 'id' => 'elmundo_portada', 'name' => 'El Mundo', 'url' => 'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml', 'country' => 'es', 'category' => 'España', 'include' => '' ),
			array( 'id' => 'us_internacional', 'name' => 'El Diario NY', 'url' => 'https://eldiariony.com/feed/', 'country' => 'us', 'category' => 'Estados Unidos', 'include' => '' ),
			array( 'id' => 'cubadebate_cultura', 'name' => 'Cubadebate', 'url' => 'https://www.cubadebate.cu/feed/', 'country' => 'cu', 'category' => 'Cultura y Sociedad', 'include' => self::CULTURE_KEYWORDS ),
			array( 'id' => 'cubadebate_general', 'name' => 'Cubadebate', 'url' => 'https://www.cubadebate.cu/feed/', 'country' => 'cu', 'category' => 'Cuba', 'include' => '' ),
		);
	}

	/** Returns the category id, creating the category if it doesn't exist. */
	public static function ensure_category( $name ) {
		$term = get_term_by( 'name', $name, 'category' );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $name, 'category' );
		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	/**
	 * Fills in the recommended configuration without overwriting what the admin already customised:
	 * empty URLs, categories and keyword lists are filled; existing values are kept.
	 *
	 * @return string[] Human-readable list of what changed.
	 */
	public static function apply() {
		$changes = array();
		$current = array();
		foreach ( BHNA_Settings::feeds() as $feed ) {
			$current[ $feed['id'] ] = $feed;
		}

		$feeds = array();
		foreach ( self::recommended_feeds() as $rec ) {
			$feed = isset( $current[ $rec['id'] ] ) ? $current[ $rec['id'] ] : BHNA_Settings::new_feed( array( 'id' => $rec['id'] ) );
			unset( $current[ $rec['id'] ] );

			if ( '' === $feed['url'] ) {
				$feed['url']     = $rec['url'];
				$feed['enabled'] = 1;
				$changes[]       = sprintf( 'Feed "%s – %s" configurado.', $rec['name'], $rec['category'] );
			}
			if ( '' === $feed['name'] || ( 'us_internacional' === $rec['id'] && false !== strpos( $feed['name'], 'Internacional en Español' ) ) ) {
				$feed['name'] = $rec['name'];
			}
			if ( empty( $feed['category_id'] ) ) {
				$feed['category_id'] = self::ensure_category( $rec['category'] );
				if ( $feed['category_id'] ) {
					$changes[] = sprintf( 'Categoría "%s" asignada a %s.', $rec['category'], $rec['name'] );
				}
			}
			if ( '' === trim( $feed['include'] ) && $rec['include'] ) {
				$feed['include'] = $rec['include'];
				$changes[]       = sprintf( 'Palabras de la sección añadidas a %s – %s.', $rec['name'], $rec['category'] );
			}
			if ( empty( $feed['country'] ) ) {
				$feed['country'] = $rec['country'];
			}
			$feeds[]         = $feed;
		}

		// Feeds the admin added by hand stay, after the recommended ones.
		foreach ( $current as $feed ) {
			$feeds[] = $feed;
		}
		BHNA_Settings::save_feeds( $feeds );

		$settings = BHNA_Settings::get();
		$updates  = array();
		if ( '' === trim( $settings['include_keywords'] ) ) {
			$updates['include_keywords'] = self::INCLUDE_KEYWORDS;
			$changes[]                   = 'Palabras clave de inclusión añadidas.';
		}
		if ( '' === trim( $settings['exclude_keywords'] ) ) {
			$updates['exclude_keywords'] = self::EXCLUDE_KEYWORDS;
			$changes[]                   = 'Palabras clave de exclusión añadidas.';
		}
		if ( $updates ) {
			BHNA_Settings::update( $updates );
		}

		update_option( 'bhna_setup_applied', time(), false );
		return $changes;
	}

	/**
	 * Setup checklist shown on the dashboard.
	 *
	 * @return array[] Each item: label, done, hint, url.
	 */
	public static function checklist() {
		$s        = BHNA_Settings::get();
		$feeds    = BHNA_Settings::feeds();
		$active   = array_filter(
			$feeds,
			function ( $f ) {
				return $f['enabled'] && $f['url'];
			}
		);
		$mapped   = array_filter(
			$active,
			function ( $f ) {
				return ! empty( $f['category_id'] );
			}
		);
		$products = wp_count_posts( BHNA_Amazon::CPT );
		$settings = admin_url( 'admin.php?page=bhna-settings' );

		return array(
			array(
				'label' => 'Clave API de Anthropic',
				'done'  => '' !== BHNA_Settings::api_key(),
				'hint'  => 'Necesaria para reescribir las noticias.',
				'url'   => $settings . '#claude',
			),
			array(
				'label' => 'Fuentes RSS activas',
				'done'  => count( $active ) > 0,
				'hint'  => sprintf( '%d activas de %d.', count( $active ), count( $feeds ) ),
				'url'   => admin_url( 'admin.php?page=bhna-feeds' ),
			),
			array(
				'label' => 'Categorías asignadas a las fuentes',
				'done'  => $active && count( $mapped ) === count( $active ),
				'hint'  => sprintf( '%d de %d fuentes con categoría.', count( $mapped ), count( $active ) ),
				'url'   => admin_url( 'admin.php?page=bhna-feeds' ),
			),
			array(
				'label' => 'Palabras clave del filtro',
				'done'  => '' !== trim( $s['include_keywords'] ),
				'hint'  => 'Sin palabras de inclusión se procesan todas las noticias.',
				'url'   => $settings . '#filtro',
			),
			array(
				'label' => 'Alertas a editores (Telegram)',
				'done'  => $s['telegram_enabled'] && $s['telegram_bot_token'] && $s['telegram_chat_id'],
				'hint'  => 'Token del bot e ID del grupo privado.',
				'url'   => $settings . '#alertas',
			),
			array(
				'label' => 'Cron real del servidor',
				'done'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'hint'  => 'Recomendado en Hostinger para funcionar 24/7 (ver readme).',
				'url'   => '',
			),
			array(
				'label' => 'ID de afiliado de Amazon.com',
				'done'  => $s['amazon_enabled'] && '' !== $s['amazon_tag_com'],
				'hint'  => 'Activa el módulo y añade tu ID (…-20).',
				'url'   => admin_url( 'admin.php?page=bhna-amazon' ),
			),
			array(
				'label' => 'Productos de Amazon',
				'done'  => $products && $products->publish > 0,
				'hint'  => 'Añade productos con su ASIN o enlace.',
				'url'   => admin_url( 'post-new.php?post_type=' . BHNA_Amazon::CPT ),
			),
			array(
				'label' => 'Publicación en X',
				'done'  => $s['x_enabled'] && $s['x_api_key'] && $s['x_access_token'],
				'hint'  => 'Claves de la app de X con permisos de escritura.',
				'url'   => admin_url( 'admin.php?page=bhna-social' ),
			),
			array(
				'label' => 'Canal público de Telegram',
				'done'  => $s['tg_channel_enabled'] && $s['tg_channel_id'],
				'hint'  => 'Bot como administrador del canal.',
				'url'   => admin_url( 'admin.php?page=bhna-social' ),
			),
		);
	}
}

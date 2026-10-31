<?php
/**
 * Admin screens: Panel (dashboard), Fuentes (feeds), Ajustes IA (settings) and Registro (log).
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'add_meta_boxes_post', array( __CLASS__, 'add_meta_box' ) );

		foreach ( array( 'save_settings', 'save_feeds', 'run_now', 'retry_item', 'clear_log', 'apply_setup' ) as $action ) {
			add_action( 'admin_post_bhna_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	public static function menu() {
		add_menu_page( 'Noticias IA', 'Noticias IA', self::CAP, 'bhna', array( __CLASS__, 'page_dashboard' ), 'dashicons-rss', 26 );
		add_submenu_page( 'bhna', 'Panel', 'Panel', self::CAP, 'bhna', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'bhna', 'Fuentes RSS', 'Fuentes RSS', self::CAP, 'bhna-feeds', array( __CLASS__, 'page_feeds' ) );
		add_submenu_page( 'bhna', 'Ajustes IA', 'Ajustes IA', self::CAP, 'bhna-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'bhna', 'Registro', 'Registro', self::CAP, 'bhna-log', array( __CLASS__, 'page_log' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Notices & helpers                                                   */
	/* ------------------------------------------------------------------ */

	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'bhna' ) ) {
			return;
		}

		if ( isset( $_GET['bhna_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$message = sanitize_textarea_field( wp_unslash( $_GET['bhna_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$type    = isset( $_GET['bhna_type'] ) && 'error' === $_GET['bhna_type'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), nl2br( esc_html( $message ) ) );
		}

		if ( '' === BHNA_Settings::api_key() && 'toplevel_page_bhna' !== $screen->id ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html( 'Falta la clave API de Anthropic: el sistema no puede reescribir noticias hasta que la añadas.' ),
				esc_url( admin_url( 'admin.php?page=bhna-settings#claude' ) ),
				esc_html( 'Añadir clave' )
			);
		}
	}

	public static function redirect( $page, $message, $type = 'success', $hash = '' ) {
		$url = add_query_arg(
			array(
				'page'      => $page,
				'bhna_msg'  => rawurlencode( $message ),
				'bhna_type' => $type,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url . ( $hash ? '#' . $hash : '' ) );
		exit;
	}

	public static function verify( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html( 'No tienes permisos para hacer esto.' ) );
		}
		check_admin_referer( 'bhna_' . $action );
	}

	public static function status_badge( $status, $quality_flag = false ) {
		$map = array(
			BHNA_Items::SKIPPED    => array( 'Descartada', '' ),
			BHNA_Items::QUEUED     => array( 'En cola', 'info' ),
			BHNA_Items::PROCESSING => array( 'Procesando', 'info' ),
			BHNA_Items::DONE       => array( 'Borrador creado', 'success' ),
			BHNA_Items::FAILED     => array( 'Fallida', 'danger' ),
		);
		$item = isset( $map[ $status ] ) ? $map[ $status ] : array( $status, '' );
		$html = BHNA_UI::badge( $item[0], $item[1] );
		if ( $quality_flag ) {
			$html .= ' ' . BHNA_UI::badge( 'Revisar datos', 'warning' );
		}
		return $html;
	}

	public static function feed_status_badge( array $feed ) {
		if ( '' === $feed['url'] ) {
			return BHNA_UI::badge( 'Sin URL', 'warning' );
		}
		return $feed['enabled'] ? BHNA_UI::badge( 'Activa', 'success' ) : BHNA_UI::badge( 'Inactiva' );
	}

	/* ------------------------------------------------------------------ */
	/* Panel                                                               */
	/* ------------------------------------------------------------------ */

	public static function page_dashboard() {
		$counts    = BHNA_Items::count_by_status();
		$today     = BHNA_Items::done_today();
		$limit     = (int) BHNA_Settings::get( 'daily_limit' );
		$last      = get_option( 'bhna_last_run' );
		$next      = wp_next_scheduled( BHNA_Cron::HOOK );
		$daily     = BHNA_Items::daily_stats( 14 );
		$by_feed   = BHNA_Items::feed_stats();
		$cost_sum  = array_sum( wp_list_pluck( $daily, 'cost' ) );
		$checklist = BHNA_Setup::checklist();
		$done      = count( array_filter( wp_list_pluck( $checklist, 'done' ) ) );
		$percent   = (int) round( 100 * $done / max( 1, count( $checklist ) ) );
		$models    = BHNA_Settings::MODELS;
		$model     = BHNA_Settings::get( 'model' );

		BHNA_UI::page_open(
			'bhna',
			'Panel',
			'Resumen de la automatización de noticias.',
			BHNA_UI::action_button( 'run_now', 'Ejecutar ahora', 'button button-primary' )
		);

		echo '<div class="bhna-kpis">';
		BHNA_UI::kpi( 'Borradores hoy', $today . ' / ' . $limit, 'dashicons-edit-page', $today >= $limit ? 'warning' : '' );
		BHNA_UI::kpi( 'En cola', $counts[ BHNA_Items::QUEUED ] + $counts[ BHNA_Items::PROCESSING ], 'dashicons-clock' );
		BHNA_UI::kpi( 'Borradores creados', $counts[ BHNA_Items::DONE ], 'dashicons-yes-alt', 'success' );
		BHNA_UI::kpi( 'Fallidas', $counts[ BHNA_Items::FAILED ], 'dashicons-warning', $counts[ BHNA_Items::FAILED ] ? 'danger' : '' );
		BHNA_UI::kpi( 'Coste IA (14 días)', '$' . number_format_i18n( $cost_sum, 2 ), 'dashicons-chart-line' );
		echo '</div>';

		echo '<div class="bhna-grid">';

		if ( $percent < 100 ) {
			echo '<div class="bhna-col-12">';
			BHNA_UI::card_open(
				'Primeros pasos',
				'dashicons-flag',
				sprintf( 'Configuración completada al %d%%. Lo que se puede rellenar automáticamente (feeds, categorías y palabras clave) se aplica con un clic; las claves y cuentas las añades tú.', $percent ),
				BHNA_UI::action_button( 'apply_setup', 'Aplicar configuración recomendada', 'button button-primary', 'Se rellenarán los feeds, categorías y palabras clave vacíos. Lo que ya hayas configurado no se modifica. ¿Continuar?' )
			);
			echo '<div class="bhna-progress"><span style="width:' . (int) $percent . '%"></span></div><ul class="bhna-checklist">';
			foreach ( $checklist as $item ) {
				printf(
					'<li><span class="bhna-check %s">%s</span><span class="bhna-check-text">%s<small>%s</small></span>%s</li>',
					$item['done'] ? 'is-done' : 'is-todo',
					$item['done'] ? '✓' : '•',
					esc_html( $item['label'] ),
					esc_html( $item['hint'] ),
					( ! $item['done'] && $item['url'] ) ? '<a class="button button-small" href="' . esc_url( $item['url'] ) . '">Configurar</a>' : ''
				);
			}
			echo '</ul>';
			BHNA_UI::card_close();
			echo '</div>';
		}

		// Chart.
		$max = 1;
		foreach ( $daily as $row ) {
			$max = max( $max, $row['read'], $row['done'] );
		}
		echo '<div class="bhna-col-8">';
		BHNA_UI::card_open( 'Últimos 14 días', 'dashicons-chart-bar', 'Noticias leídas de los feeds y borradores creados por día.' );
		echo '<div class="bhna-chart">';
		foreach ( $daily as $day => $row ) {
			printf(
				'<div class="bhna-chart-col" title="%s: %d leídas, %d borradores, %d fallidas"><div class="bhna-chart-bars"><div class="bhna-chart-bar is-read" style="height:%d%%"></div><div class="bhna-chart-bar is-done" style="height:%d%%"></div></div><div class="bhna-chart-label">%s</div></div>',
				esc_attr( wp_date( 'd/m', strtotime( $day ) ) ),
				(int) $row['read'],
				(int) $row['done'],
				(int) $row['failed'],
				(int) round( 100 * $row['read'] / $max ),
				(int) round( 100 * $row['done'] / $max ),
				esc_html( wp_date( 'd/m', strtotime( $day ) ) )
			);
		}
		echo '</div><div class="bhna-legend"><span class="is-read">Leídas</span><span class="is-done">Borradores</span></div>';
		BHNA_UI::card_close();
		echo '</div>';

		// Status.
		echo '<div class="bhna-col-4">';
		BHNA_UI::card_open( 'Estado', 'dashicons-heart' );
		echo '<table class="bhna-table"><tbody>';
		printf( '<tr><td>Última ejecución</td><td>%s</td></tr>', $last ? esc_html( wp_date( 'd/m/Y H:i', $last['time'] ) ) : '—' );
		printf( '<tr><td>Próxima ejecución</td><td>%s</td></tr>', $next ? esc_html( wp_date( 'd/m/Y H:i', $next ) ) : '—' );
		printf(
			'<tr><td>Cron del servidor</td><td>%s</td></tr>',
			defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? BHNA_UI::badge( 'Activo', 'success' ) : BHNA_UI::badge( 'WP-Cron', 'warning' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		printf( '<tr><td>Modelo</td><td>%s</td></tr>', esc_html( isset( $models[ $model ] ) ? strtok( $models[ $model ]['label'], '(' ) : $model ) );
		printf( '<tr><td>Frecuencia</td><td>cada %d min</td></tr>', (int) BHNA_Settings::get( 'interval' ) );
		printf(
			'<tr><td>Amazon</td><td>%s</td></tr>',
			BHNA_Settings::get( 'amazon_enabled' ) ? BHNA_UI::badge( 'Activo', 'success' ) : BHNA_UI::badge( 'Inactivo' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		printf(
			'<tr><td>Redes sociales</td><td>%s</td></tr>',
			BHNA_Social::any_network_enabled() ? BHNA_UI::badge( 'Activas', 'success' ) : BHNA_UI::badge( 'Inactivas' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '</tbody></table>';
		BHNA_UI::card_close();
		echo '</div>';

		// Per feed.
		echo '<div class="bhna-col-12">';
		BHNA_UI::card_open( 'Por fuente', 'dashicons-rss', '', '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=bhna-feeds' ) ) . '">Gestionar fuentes</a>' );
		echo '<div class="bhna-scroll"><table class="bhna-table"><thead><tr><th>Fuente</th><th>Estado</th><th>Leídas</th><th>Descartadas</th><th>Borradores</th><th>Fallidas</th></tr></thead><tbody>';
		foreach ( BHNA_Settings::feeds() as $feed ) {
			$row = isset( $by_feed[ $feed['id'] ] ) ? $by_feed[ $feed['id'] ] : array( 'read' => 0, 'skipped' => 0, 'done' => 0, 'failed' => 0 );
			printf(
				'<tr><td><strong>%s</strong></td><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td></tr>',
				esc_html( BHNA_Fetcher::label( $feed ) ),
				self::feed_status_badge( $feed ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				(int) $row['read'],
				(int) $row['skipped'],
				(int) $row['done'],
				(int) $row['failed']
			);
		}
		echo '</tbody></table></div>';
		echo '<p class="bhna-help">El coste es una estimación con las tarifas públicas de Anthropic; el importe real está en tu consola de Anthropic.</p>';
		BHNA_UI::card_close();
		echo '</div>';

		echo '</div>';
		BHNA_UI::page_close();
	}

	public static function handle_run_now() {
		self::verify( 'run_now' );
		$result = BHNA_Cron::run();
		$f      = $result['fetched'];
		$p      = $result['processed'];
		$msg    = sprintf( 'Feeds: %d noticias nuevas (%d en cola, %d descartadas). Claude: %d borradores creados, %d fallidas, %d para reintentar.', $f['new'], $f['queued'], $f['skipped'], $p['done'], $p['failed'], $p['retried'] );
		if ( $p['limit_reached'] ) {
			$msg .= ' Se alcanzó el límite diario.';
		}
		self::redirect( 'bhna', $msg );
	}

	public static function handle_apply_setup() {
		self::verify( 'apply_setup' );
		$changes = BHNA_Setup::apply();
		$message = $changes ? "Configuración recomendada aplicada:\n• " . implode( "\n• ", $changes ) : 'Todo lo que se puede rellenar automáticamente ya estaba configurado.';
		$return  = isset( $_POST['bhna_return'] ) && 'bhna-feeds' === $_POST['bhna_return'] ? 'bhna-feeds' : 'bhna'; // phpcs:ignore WordPress.Security.NonceVerification
		self::redirect( $return, $message );
	}

	/* ------------------------------------------------------------------ */
	/* Fuentes                                                             */
	/* ------------------------------------------------------------------ */

	public static function page_feeds() {
		BHNA_UI::page_open(
			'bhna-feeds',
			'Fuentes RSS',
			'Solo se leen el título y el resumen de cada noticia. Si varias fuentes traen la misma noticia, se queda la primera de la lista que la acepte.',
			BHNA_UI::action_button( 'apply_setup', 'Rellenar con la configuración recomendada', 'button', 'Se completarán las URL, categorías y palabras clave vacías. ¿Continuar?', array( 'bhna_return' => 'bhna-feeds' ) )
		);

		BHNA_UI::form_open( 'save_feeds' );
		echo '<div id="bhna-feed-list">';
		foreach ( BHNA_Settings::feeds() as $i => $feed ) {
			self::render_feed_card( $i, $feed );
		}
		echo '</div>';
		echo '<p><button type="button" class="button" id="bhna-add-feed"><span class="dashicons dashicons-plus-alt2" style="vertical-align:-4px;"></span> Añadir fuente</button></p>';
		echo '<div class="bhna-card" style="margin-top:20px;"><div class="bhna-card-head"><div><h3><span class="dashicons dashicons-info-outline"></span>Cómo funcionan las palabras clave</h3>';
		echo '<p>Las palabras de <strong>exclusión</strong> de cada fuente se suman a las globales. Si una fuente tiene sus propias palabras de <strong>inclusión</strong>, se usan solo esas; si no, las globales de Ajustes IA. Así un mismo feed puede repartirse en secciones (por ejemplo, la cultura de Cubadebate).</p></div></div></div>';
		BHNA_UI::savebar( 'Guardar fuentes' );
		echo '</form>';

		echo '<script type="text/html" id="bhna-feed-template">';
		self::render_feed_card( '__INDEX__', BHNA_Settings::new_feed( array( 'id' => '', 'enabled' => 1 ) ) );
		echo '</script>';

		BHNA_UI::page_close();
	}

	private static function render_feed_card( $i, array $feed ) {
		$name    = 'feeds[' . $i . ']';
		$is_new  = '' === $feed['id'];
		$classes = 'bhna-feed' . ( $feed['enabled'] ? '' : ' is-disabled' );
		$country = isset( BHNA_Settings::COUNTRIES[ $feed['country'] ] ) ? BHNA_Settings::COUNTRIES[ $feed['country'] ] : '';
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $feed['id'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[delete]" value="0" data-delete-flag>
			<div class="bhna-feed-top">
				<?php echo BHNA_UI::toggle( $name . '[enabled]', $feed['enabled'], '', 'bhna-feed-toggle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="bhna-feed-name"><?php echo esc_html( $is_new ? 'Nueva fuente' : BHNA_Fetcher::label( $feed ) ); ?></span>
				<?php if ( ! $is_new ) : ?>
					<?php echo self::feed_status_badge( $feed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo BHNA_UI::badge( $country, 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( 'fraternidades' === $feed['group'] ) : ?>
						<?php echo BHNA_UI::badge( 'Fraternidades' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<button type="submit" class="button button-small" name="test_feed" value="<?php echo esc_attr( $feed['id'] ); ?>">Guardar y probar</button>
				<?php endif; ?>
				<a href="#" class="bhna-feed-remove" data-bhna-remove-feed data-confirm="¿Eliminar esta fuente? Se aplicará al guardar.">Eliminar</a>
			</div>
			<div class="bhna-feed-grid">
				<div>
					<label>URL del feed (o de la web: se detecta el feed al probar)</label>
					<input type="url" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $feed['url'] ); ?>" placeholder="https://…">
				</div>
				<div>
					<label>Nombre del medio</label>
					<input type="text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( $feed['name'] ); ?>" placeholder="El Mundo">
				</div>
				<div>
					<label>País</label>
					<?php echo BHNA_UI::select( $name . '[country]', BHNA_Settings::COUNTRIES, $feed['country'], 'bhna_country_' . $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div>
					<label>Categoría de WordPress</label>
					<?php
					wp_dropdown_categories(
						array(
							'name'              => $name . '[category_id]',
							'id'                => 'bhna_cat_' . $i,
							'selected'          => (int) $feed['category_id'],
							'show_option_none'  => '— Ninguna —',
							'option_none_value' => 0,
							'hide_empty'        => 0,
							'hierarchical'      => 1,
						)
					);
					?>
				</div>
			</div>
			<div class="bhna-feed-grid is-keywords">
				<div>
					<label>Palabras de inclusión propias (opcional)</label>
					<textarea name="<?php echo esc_attr( $name ); ?>[include]" rows="2" placeholder="Vacío = usar las globales"><?php echo esc_textarea( $feed['include'] ); ?></textarea>
				</div>
				<div>
					<label>Palabras de exclusión propias (opcional)</label>
					<textarea name="<?php echo esc_attr( $name ); ?>[exclude]" rows="2"><?php echo esc_textarea( $feed['exclude'] ); ?></textarea>
				</div>
			</div>
			<div style="margin-top:10px;">
				<label class="bhna-help" style="margin-right:6px;">Grupo:</label>
				<?php echo BHNA_UI::select( $name . '[group]', BHNA_Settings::GROUPS, $feed['group'], 'bhna_group_' . $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
	}

	public static function handle_save_feeds() {
		self::verify( 'save_feeds' );
		$input = isset( $_POST['feeds'] ) ? (array) wp_unslash( $_POST['feeds'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$test  = isset( $_POST['test_feed'] ) ? sanitize_key( wp_unslash( $_POST['test_feed'] ) ) : '';
		$feeds = array();

		foreach ( $input as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
				continue;
			}
			$url  = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$id   = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';

			if ( '' === $id && '' === $url && '' === $name ) {
				continue;
			}

			$values = array(
				'name'        => $name ? $name : (string) wp_parse_url( $url, PHP_URL_HOST ),
				'url'         => $url,
				'country'     => isset( $row['country'] ) && array_key_exists( $row['country'], BHNA_Settings::COUNTRIES ) ? $row['country'] : 'other',
				'group'       => isset( $row['group'] ) && array_key_exists( $row['group'], BHNA_Settings::GROUPS ) ? $row['group'] : 'noticias',
				'category_id' => isset( $row['category_id'] ) ? max( 0, (int) $row['category_id'] ) : 0,
				'include'     => isset( $row['include'] ) ? sanitize_textarea_field( $row['include'] ) : '',
				'exclude'     => isset( $row['exclude'] ) ? sanitize_textarea_field( $row['exclude'] ) : '',
				'enabled'     => ! empty( $row['enabled'] ) && $url ? 1 : 0,
			);
			if ( '' !== $id ) {
				$values['id'] = $id;
			}
			$feeds[] = BHNA_Settings::new_feed( $values );
		}

		BHNA_Settings::save_feeds( $feeds );

		if ( $test ) {
			self::test_feed( $test );
		}
		self::redirect( 'bhna-feeds', 'Fuentes guardadas.' );
	}

	/**
	 * Reads a feed once (nothing is queued) and reports what it found. If the URL was a web page,
	 * SimplePie discovers the feed and the stored URL is updated to it.
	 */
	private static function test_feed( $feed_id ) {
		$feed = BHNA_Settings::feed( $feed_id );
		if ( ! $feed || '' === $feed['url'] ) {
			self::redirect( 'bhna-feeds', 'Esta fuente no tiene URL.', 'error' );
		}

		require_once ABSPATH . WPINC . '/feed.php';
		$rss = fetch_feed( $feed['url'] );
		if ( is_wp_error( $rss ) ) {
			self::redirect( 'bhna-feeds', sprintf( '"%s": no se pudo leer el feed. %s', BHNA_Fetcher::label( $feed ), $rss->get_error_message() ), 'error' );
		}

		$message = '';
		$found   = $rss->subscribe_url();
		if ( $found && untrailingslashit( $found ) !== untrailingslashit( $feed['url'] ) ) {
			$feeds = BHNA_Settings::feeds();
			foreach ( $feeds as &$f ) {
				if ( $f['id'] === $feed_id ) {
					$f['url'] = esc_url_raw( $found );
				}
			}
			unset( $f );
			BHNA_Settings::save_feeds( $feeds );
			$message .= "Feed detectado y guardado: {$found}\n";
		}

		$items  = $rss->get_items( 0, 3 );
		$titles = array();
		foreach ( $items as $item ) {
			$titles[] = '• ' . BHNA_Fetcher::clean( $item->get_title() );
		}
		$message .= sprintf( '"%s" funciona: %d noticias en el feed.', BHNA_Fetcher::label( $feed ), $rss->get_item_quantity() );
		if ( $titles ) {
			$message .= "\nÚltimas:\n" . implode( "\n", $titles );
		}
		self::redirect( 'bhna-feeds', $message );
	}

	/* ------------------------------------------------------------------ */
	/* Ajustes IA                                                          */
	/* ------------------------------------------------------------------ */

	public static function page_settings() {
		$s          = BHNA_Settings::get();
		$key_in_cfg = defined( 'BHNA_ANTHROPIC_API_KEY' ) && BHNA_ANTHROPIC_API_KEY;
		$models     = array();
		foreach ( BHNA_Settings::MODELS as $id => $model ) {
			$models[ $id ] = sprintf( '%s — $%s / $%s por millón de tokens', $model['label'], number_format_i18n( $model['in'], 2 ), number_format_i18n( $model['out'], 2 ) );
		}

		BHNA_UI::page_open( 'bhna-settings', 'Ajustes IA', 'Claude, filtro de noticias, entradas de WordPress y alertas a los editores.' );
		BHNA_UI::form_open( 'save_settings' );
		echo '<div class="bhna-card">';
		BHNA_UI::tabs(
			array(
				'claude'    => 'Claude',
				'filtro'    => 'Filtro y límites',
				'wordpress' => 'WordPress',
				'alertas'   => 'Alertas',
				'avanzado'  => 'Avanzado',
			)
		);

		BHNA_UI::panel_open( 'claude' );
		BHNA_UI::field(
			'Clave API de Anthropic',
			$key_in_cfg ? BHNA_UI::badge( 'Definida en wp-config.php', 'success' ) : BHNA_UI::secret( 'api_key', $s['api_key'], 'sk-ant-…' ),
			'Se obtiene en console.anthropic.com. También puede definirse en wp-config.php con <code>BHNA_ANTHROPIC_API_KEY</code>.',
			'bhna_api_key'
		);
		BHNA_UI::field( 'Modelo', BHNA_UI::select( 'model', $models, $s['model'] ), 'Opus redacta mejor; Haiku es el más económico y rápido.', 'bhna_model' );
		BHNA_UI::field(
			'Esfuerzo',
			BHNA_UI::select( 'effort', array( 'low' => 'Bajo — más rápido y económico', 'medium' => 'Medio — recomendado', 'high' => 'Alto — más cuidado, más coste' ), $s['effort'] ),
			'',
			'bhna_effort'
		);
		BHNA_UI::field(
			'Instrucciones de redacción',
			BHNA_UI::textarea( 'system_prompt', $s['system_prompt'], 14, 'large-text code' ),
			'Estilo y reglas. La respuesta siempre incluye titular, párrafos, extracto, meta descripción y etiquetas.',
			'bhna_system_prompt'
		);
		BHNA_UI::field( 'Probar conexión', '<button type="submit" class="button" name="bhna_after" value="test_claude">Guardar y probar Claude</button>', 'Reescribe una noticia de ejemplo (no crea ninguna entrada).' );
		BHNA_UI::panel_close();

		BHNA_UI::panel_open( 'filtro' );
		BHNA_UI::field( 'Palabras de inclusión', BHNA_UI::textarea( 'include_keywords', $s['include_keywords'], 8 ), 'Una por línea o separadas por comas. Solo se procesan las noticias que contengan alguna. Vacío = todas.', 'bhna_include_keywords' );
		BHNA_UI::field( 'Palabras de exclusión', BHNA_UI::textarea( 'exclude_keywords', $s['exclude_keywords'], 4 ), 'Las noticias con estas palabras nunca se procesan.', 'bhna_exclude_keywords' );
		BHNA_UI::field( 'Límite diario de borradores', BHNA_UI::number( 'daily_limit', $s['daily_limit'], 1, 500 ), 'Controla el gasto de la API y la carga de los editores.', 'bhna_daily_limit' );
		BHNA_UI::field( 'Noticias por ejecución', BHNA_UI::number( 'batch_size', $s['batch_size'], 1, 20 ), 'En hosting compartido conviene 3–5.', 'bhna_batch_size' );
		BHNA_UI::field( 'Frecuencia (minutos)', BHNA_UI::number( 'interval', $s['interval'], 5, 1440 ), '', 'bhna_interval' );
		BHNA_UI::field( 'Intentos por noticia', BHNA_UI::number( 'max_attempts', $s['max_attempts'], 1, 10 ), 'Si Claude falla temporalmente, la noticia se reintenta en las siguientes ejecuciones.', 'bhna_max_attempts' );
		BHNA_UI::panel_close();

		BHNA_UI::panel_open( 'wordpress' );
		BHNA_UI::field( 'Estado de las entradas', BHNA_UI::select( 'post_status', array( 'draft' => 'Borrador', 'pending' => 'Pendiente de revisión' ), $s['post_status'] ), 'El sistema nunca publica directamente.', 'bhna_post_status' );
		ob_start();
		wp_dropdown_users(
			array(
				'name'              => 'post_author',
				'id'                => 'bhna_post_author',
				'selected'          => (int) $s['post_author'],
				'show_option_none'  => '— Primer administrador —',
				'option_none_value' => 0,
				'capability'        => array( 'edit_posts' ),
			)
		);
		BHNA_UI::field( 'Autor', ob_get_clean(), '', 'bhna_post_author' );
		BHNA_UI::field(
			'Atribución',
			BHNA_UI::text( 'attribution_text', $s['attribution_text'] ) . '<p style="margin:8px 0 0;">' . BHNA_UI::toggle( 'attribution_link', $s['attribution_link'], 'Enlazar a la noticia original' ) . '</p>',
			'<code>{medio}</code> se sustituye por el nombre de la fuente.',
			'bhna_attribution_text'
		);
		BHNA_UI::field( 'Meta descripción SEO', BHNA_UI::toggle( 'seo_meta', $s['seo_meta'], 'Guardarla en Yoast / Rank Math' ) );
		BHNA_UI::field( 'Control de calidad', BHNA_UI::toggle( 'quality_check', $s['quality_check'], 'Avisar si faltan nombres o cifras del original' ) );
		BHNA_UI::panel_close();

		BHNA_UI::panel_open( 'alertas' );
		BHNA_UI::field(
			'Email',
			BHNA_UI::toggle( 'notify_email', $s['notify_email'], 'Avisar de cada borrador nuevo' ) . '<p style="margin:8px 0 0;">' . BHNA_UI::text( 'email_recipients', $s['email_recipients'], 'editor@ejemplo.com, otro@ejemplo.com' ) . '</p>',
			'Separa varios correos con comas.'
		);
		BHNA_UI::field( 'Telegram (grupo privado)', BHNA_UI::toggle( 'telegram_enabled', $s['telegram_enabled'], 'Avisar por Telegram' ) );
		BHNA_UI::field( 'Token del bot', BHNA_UI::secret( 'telegram_bot_token', $s['telegram_bot_token'], '123456:ABC… (de @BotFather)' ), 'El mismo bot publica en el canal público (Redes).', 'bhna_telegram_bot_token' );
		BHNA_UI::field( 'ID del grupo de editores', BHNA_UI::text( 'telegram_chat_id', $s['telegram_chat_id'], '-1001234567890' ), 'Añade el bot al grupo y escribe aquí su ID.', 'bhna_telegram_chat_id' );
		BHNA_UI::field( 'Probar', '<button type="submit" class="button" name="bhna_after" value="test_telegram">Guardar y enviar prueba</button>', 'Envía un mensaje de prueba al grupo privado.' );
		BHNA_UI::field( 'Errores', BHNA_UI::toggle( 'error_alerts', $s['error_alerts'], 'Avisar al administrador (máx. una vez por hora por error)' ) );
		BHNA_UI::panel_close();

		BHNA_UI::panel_open( 'avanzado' );
		BHNA_UI::field( 'Desinstalación', BHNA_UI::toggle( 'delete_on_uninstall', $s['delete_on_uninstall'], 'Borrar ajustes y registros al eliminar el plugin' ), 'Las entradas creadas nunca se borran.' );
		BHNA_UI::field(
			'Cron del servidor',
			defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? BHNA_UI::badge( 'Activo', 'success' ) : BHNA_UI::badge( 'Usando WP-Cron', 'warning' ),
			'Para funcionar 24/7, añade <code>define( \'DISABLE_WP_CRON\', true );</code> a wp-config.php y en Hostinger ➔ Cron Jobs una tarea cada 5 minutos:<br><code>wget -q -O - "' . esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ) . '" &gt;/dev/null 2&gt;&amp;1</code>'
		);
		BHNA_UI::panel_close();

		echo '</div>';
		BHNA_UI::savebar( 'Guardar ajustes' );
		echo '</form>';
		BHNA_UI::page_close();
	}

	public static function handle_save_settings() {
		self::verify( 'save_settings' );
		$p   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$old = BHNA_Settings::get();

		$values = array(
			'model'               => isset( $p['model'] ) && array_key_exists( $p['model'], BHNA_Settings::MODELS ) ? $p['model'] : $old['model'],
			'effort'              => isset( $p['effort'] ) && in_array( $p['effort'], BHNA_Settings::EFFORTS, true ) ? $p['effort'] : 'medium',
			'system_prompt'       => isset( $p['system_prompt'] ) && '' !== trim( $p['system_prompt'] ) ? sanitize_textarea_field( $p['system_prompt'] ) : BHNA_Settings::default_prompt(),
			'include_keywords'    => isset( $p['include_keywords'] ) ? sanitize_textarea_field( $p['include_keywords'] ) : '',
			'exclude_keywords'    => isset( $p['exclude_keywords'] ) ? sanitize_textarea_field( $p['exclude_keywords'] ) : '',
			'daily_limit'         => isset( $p['daily_limit'] ) ? max( 1, min( 500, absint( $p['daily_limit'] ) ) ) : 30,
			'batch_size'          => isset( $p['batch_size'] ) ? max( 1, min( 20, absint( $p['batch_size'] ) ) ) : 3,
			'interval'            => isset( $p['interval'] ) ? max( 5, min( 1440, absint( $p['interval'] ) ) ) : 30,
			'max_attempts'        => isset( $p['max_attempts'] ) ? max( 1, min( 10, absint( $p['max_attempts'] ) ) ) : 3,
			'post_status'         => isset( $p['post_status'] ) && in_array( $p['post_status'], BHNA_Publisher::ALLOWED_STATUSES, true ) ? $p['post_status'] : 'draft',
			'post_author'         => isset( $p['post_author'] ) ? max( 0, (int) $p['post_author'] ) : 0,
			'attribution_text'    => isset( $p['attribution_text'] ) && '' !== trim( $p['attribution_text'] ) ? sanitize_text_field( $p['attribution_text'] ) : 'Con información original de {medio}',
			'attribution_link'    => empty( $p['attribution_link'] ) ? 0 : 1,
			'seo_meta'            => empty( $p['seo_meta'] ) ? 0 : 1,
			'quality_check'       => empty( $p['quality_check'] ) ? 0 : 1,
			'notify_email'        => empty( $p['notify_email'] ) ? 0 : 1,
			'email_recipients'    => isset( $p['email_recipients'] ) ? implode( ', ', array_filter( array_map( 'sanitize_email', explode( ',', $p['email_recipients'] ) ) ) ) : '',
			'telegram_enabled'    => empty( $p['telegram_enabled'] ) ? 0 : 1,
			'telegram_chat_id'    => isset( $p['telegram_chat_id'] ) ? sanitize_text_field( $p['telegram_chat_id'] ) : '',
			'error_alerts'        => empty( $p['error_alerts'] ) ? 0 : 1,
			'delete_on_uninstall' => empty( $p['delete_on_uninstall'] ) ? 0 : 1,
		);
		foreach ( array( 'api_key', 'telegram_bot_token' ) as $secret ) {
			if ( ! empty( $p[ $secret ] ) ) {
				$values[ $secret ] = sanitize_text_field( $p[ $secret ] );
			}
		}

		BHNA_Settings::update( $values );
		if ( (int) $old['interval'] !== $values['interval'] ) {
			BHNA_Cron::reschedule();
		}

		$after = isset( $p['bhna_after'] ) ? $p['bhna_after'] : '';
		if ( 'test_claude' === $after ) {
			self::test_claude();
		} elseif ( 'test_telegram' === $after ) {
			$result = BHNA_Notifier::telegram( '✅ Prueba de Brotherhood News AI: las alertas de Telegram funcionan.' );
			if ( is_wp_error( $result ) ) {
				self::redirect( 'bhna-settings', 'Ajustes guardados. Telegram: ' . $result->get_error_message(), 'error', 'alertas' );
			}
			self::redirect( 'bhna-settings', 'Ajustes guardados. Mensaje de prueba enviado al grupo de Telegram.', 'success', 'alertas' );
		}

		self::redirect( 'bhna-settings', 'Ajustes guardados.' );
	}

	private static function test_claude() {
		$item   = array(
			'id'           => 0,
			'title'        => 'El Congreso aprueba una reforma de la ley de vivienda con 180 votos a favor',
			'summary'      => 'El Congreso de los Diputados aprobó este martes la reforma de la ley de vivienda, que limita las subidas del alquiler al 3% anual. La ministra Isabel Rodríguez defendió la norma en el pleno.',
			'published_at' => BHNA_Items::now(),
		);
		$feed   = BHNA_Settings::new_feed( array( 'id' => 'test', 'name' => 'Prueba', 'country' => 'es' ) );
		$result = BHNA_Claude::rewrite( $item, $feed );

		if ( is_wp_error( $result ) ) {
			self::redirect( 'bhna-settings', 'Ajustes guardados. Error al probar Claude: ' . $result->get_error_message(), 'error', 'claude' );
		}
		$message = sprintf(
			"Claude funciona (%s, %d + %d tokens).\nTitular: %s\n%s",
			$result['model'],
			$result['input_tokens'],
			$result['output_tokens'],
			$result['titulo'],
			isset( $result['cuerpo'][0] ) ? $result['cuerpo'][0] : ''
		);
		self::redirect( 'bhna-settings', $message, 'success', 'claude' );
	}

	/* ------------------------------------------------------------------ */
	/* Registro                                                            */
	/* ------------------------------------------------------------------ */

	public static function page_log() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$items    = BHNA_Items::recent( 100, $status );
		$counts   = BHNA_Items::count_by_status();
		$statuses = array(
			''                  => array( 'Todas', array_sum( $counts ) ),
			BHNA_Items::DONE    => array( 'Borradores', $counts[ BHNA_Items::DONE ] ),
			BHNA_Items::QUEUED  => array( 'En cola', $counts[ BHNA_Items::QUEUED ] ),
			BHNA_Items::SKIPPED => array( 'Descartadas', $counts[ BHNA_Items::SKIPPED ] ),
			BHNA_Items::FAILED  => array( 'Fallidas', $counts[ BHNA_Items::FAILED ] ),
		);

		BHNA_UI::page_open( 'bhna-log', 'Registro', 'Cada noticia leída de los feeds, qué pasó con ella y por qué.' );

		echo '<div class="bhna-pills">';
		foreach ( $statuses as $key => $info ) {
			printf(
				'<a href="%s" class="%s">%s<span class="count">%d</span></a>',
				esc_url( add_query_arg( array( 'page' => 'bhna-log', 'status' => $key ), admin_url( 'admin.php' ) ) ),
				$status === $key ? 'is-active' : '',
				esc_html( $info[0] ),
				(int) $info[1]
			);
		}
		echo '</div>';

		BHNA_UI::card_open( 'Noticias', 'dashicons-media-text', 'Las últimas 100.' );
		if ( ! $items ) {
			echo '<div class="bhna-empty"><span class="dashicons dashicons-format-aside"></span><p>Todavía no hay noticias. Pulsa "Ejecutar ahora" en el Panel.</p></div>';
		} else {
			echo '<div class="bhna-scroll"><table class="bhna-table"><thead><tr><th>Fecha</th><th>Noticia original</th><th>Estado</th><th>Detalle</th><th></th></tr></thead><tbody>';
			foreach ( $items as $item ) {
				echo '<tr>';
				printf( '<td class="bhna-muted">%s</td>', esc_html( wp_date( 'd/m H:i', strtotime( $item['created_at'] . ' UTC' ) ) ) );
				printf(
					'<td><a href="%s" target="_blank" rel="noopener">%s</a><div class="bhna-muted">%s</div></td>',
					esc_url( $item['source_url'] ),
					esc_html( $item['title'] ),
					esc_html( $item['source_name'] )
				);
				echo '<td>' . self::status_badge( $item['status'], (bool) $item['quality_flag'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				printf( '<td class="bhna-muted">%s</td>', esc_html( $item['reason'] ) );
				echo '<td style="text-align:right;white-space:nowrap;">';
				if ( $item['post_id'] ) {
					printf( '<a class="button button-small" href="%s">Editar borrador</a>', esc_url( get_edit_post_link( $item['post_id'] ) ) );
				} elseif ( in_array( $item['status'], array( BHNA_Items::FAILED, BHNA_Items::SKIPPED ), true ) ) {
					echo BHNA_UI::action_button( 'retry_item', BHNA_Items::FAILED === $item['status'] ? 'Reintentar' : 'Procesar igualmente', 'button button-small', '', array( 'item_id' => (int) $item['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		BHNA_UI::card_close();

		BHNA_UI::card_open( 'Eventos', 'dashicons-list-view', 'Lecturas de feeds, errores y publicaciones en redes.', BHNA_UI::action_button( 'clear_log', 'Vaciar', 'button button-small', '¿Borrar todos los eventos?' ) );
		$entries = BHNA_Logger::entries();
		if ( ! $entries ) {
			echo '<div class="bhna-empty"><p>Sin eventos.</p></div>';
		} else {
			$levels = array( 'info' => array( 'Info', 'info' ), 'warning' => array( 'Aviso', 'warning' ), 'error' => array( 'Error', 'danger' ) );
			echo '<div class="bhna-scroll"><table class="bhna-table"><tbody>';
			foreach ( $entries as $entry ) {
				$level = isset( $levels[ $entry['level'] ] ) ? $levels[ $entry['level'] ] : array( $entry['level'], '' );
				printf(
					'<tr><td class="bhna-muted" style="width:110px;">%s</td><td style="width:80px;">%s</td><td>%s</td></tr>',
					esc_html( wp_date( 'd/m H:i', $entry['time'] ) ),
					BHNA_UI::badge( $level[0], $level[1] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html( $entry['message'] )
				);
			}
			echo '</tbody></table></div>';
		}
		BHNA_UI::card_close();

		BHNA_UI::page_close();
	}

	public static function handle_retry_item() {
		self::verify( 'retry_item' );
		$id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		if ( $id ) {
			BHNA_Items::retry( $id );
		}
		self::redirect( 'bhna-log', 'La noticia vuelve a la cola y se procesará en la próxima ejecución.' );
	}

	public static function handle_clear_log() {
		self::verify( 'clear_log' );
		BHNA_Logger::clear();
		self::redirect( 'bhna-log', 'Eventos borrados.' );
	}

	/* ------------------------------------------------------------------ */
	/* Post edit screen                                                    */
	/* ------------------------------------------------------------------ */

	public static function add_meta_box( $post ) {
		if ( get_post_meta( $post->ID, '_bhna_item_id', true ) ) {
			add_meta_box( 'bhna_source', 'Noticias IA', array( __CLASS__, 'render_meta_box' ), 'post', 'side', 'high' );
		}
	}

	public static function render_meta_box( $post ) {
		$name    = get_post_meta( $post->ID, '_bhna_source_name', true );
		$url     = get_post_meta( $post->ID, '_bhna_source_url', true );
		$model   = get_post_meta( $post->ID, '_bhna_model', true );
		$missing = get_post_meta( $post->ID, '_bhna_quality_missing', true );

		echo '<p><strong>Fuente:</strong> ';
		if ( $url ) {
			printf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $name ) );
		} else {
			echo esc_html( $name );
		}
		echo '</p>';
		if ( $model ) {
			printf( '<p><strong>Modelo:</strong> %s</p>', esc_html( $model ) );
		}
		if ( $missing && is_array( $missing ) ) {
			printf(
				'<p style="color:#b32d2e;"><strong>⚠️ Revisar:</strong> estos datos del original no aparecen en el texto: %s</p>',
				esc_html( implode( ', ', $missing ) )
			);
		}
		echo '<p class="description">Comprueba los datos con la fuente antes de publicar.</p>';
	}
}

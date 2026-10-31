<?php
/**
 * Admin screens: Panel (stats), Fuentes (feeds), Ajustes (settings) and Registro (log).
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'add_meta_boxes_post', array( __CLASS__, 'add_meta_box' ) );

		foreach ( array( 'save_settings', 'save_feeds', 'run_now', 'test_claude', 'test_telegram', 'retry_item', 'clear_log' ) as $action ) {
			add_action( 'admin_post_bhna_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	public static function menu() {
		add_menu_page( 'Noticias IA', 'Noticias IA', self::CAP, 'bhna', array( __CLASS__, 'page_dashboard' ), 'dashicons-rss', 26 );
		add_submenu_page( 'bhna', 'Panel', 'Panel', self::CAP, 'bhna', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'bhna', 'Fuentes RSS', 'Fuentes RSS', self::CAP, 'bhna-feeds', array( __CLASS__, 'page_feeds' ) );
		add_submenu_page( 'bhna', 'Ajustes', 'Ajustes', self::CAP, 'bhna-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'bhna', 'Registro', 'Registro', self::CAP, 'bhna-log', array( __CLASS__, 'page_log' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Notices                                                             */
	/* ------------------------------------------------------------------ */

	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'bhna' ) ) {
			return;
		}

		if ( isset( $_GET['bhna_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$message = sanitize_text_field( wp_unslash( $_GET['bhna_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$type    = isset( $_GET['bhna_type'] ) && 'error' === $_GET['bhna_type'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
		}

		if ( '' === BHNA_Settings::api_key() ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html( 'Falta la clave API de Anthropic. El sistema no puede reescribir noticias hasta que la añadas.' ),
				esc_url( admin_url( 'admin.php?page=bhna-settings' ) ),
				esc_html( 'Ir a Ajustes' )
			);
		}
	}

	private static function redirect( $page, $message, $type = 'success' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => $page,
					'bhna_msg'  => rawurlencode( $message ),
					'bhna_type' => $type,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function verify( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html( 'No tienes permisos para hacer esto.' ) );
		}
		check_admin_referer( 'bhna_' . $action );
	}

	/* ------------------------------------------------------------------ */
	/* Panel                                                               */
	/* ------------------------------------------------------------------ */

	public static function page_dashboard() {
		$counts   = BHNA_Items::count_by_status();
		$today    = BHNA_Items::done_today();
		$limit    = (int) BHNA_Settings::get( 'daily_limit' );
		$last     = get_option( 'bhna_last_run' );
		$next     = wp_next_scheduled( BHNA_Cron::HOOK );
		$daily    = BHNA_Items::daily_stats( 14 );
		$by_feed  = BHNA_Items::feed_stats();
		$cost_sum = array_sum( wp_list_pluck( $daily, 'cost' ) );
		?>
		<div class="wrap">
			<h1>Noticias IA – Panel</h1>

			<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
				<?php
				self::card( 'Borradores hoy', $today . ' / ' . $limit );
				self::card( 'En cola', $counts[ BHNA_Items::QUEUED ] + $counts[ BHNA_Items::PROCESSING ] );
				self::card( 'Borradores creados (total)', $counts[ BHNA_Items::DONE ] );
				self::card( 'Descartadas por el filtro', $counts[ BHNA_Items::SKIPPED ] );
				self::card( 'Fallidas', $counts[ BHNA_Items::FAILED ] );
				self::card( 'Coste IA estimado (14 días)', '$' . number_format_i18n( $cost_sum, 2 ) );
				?>
			</div>

			<p>
				<strong>Última ejecución:</strong>
				<?php echo $last ? esc_html( wp_date( 'd/m/Y H:i', $last['time'] ) ) : '—'; ?>
				&nbsp;|&nbsp;
				<strong>Próxima ejecución:</strong>
				<?php echo $next ? esc_html( wp_date( 'd/m/Y H:i', $next ) ) : '—'; ?>
				<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
					&nbsp;|&nbsp; <span>Cron real del servidor activo ✔</span>
				<?php endif; ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bhna_run_now' ); ?>
				<input type="hidden" name="action" value="bhna_run_now">
				<?php submit_button( 'Ejecutar ahora', 'primary', 'submit', false ); ?>
				<span class="description">Lee los feeds y procesa la cola ahora mismo (respeta el límite diario).</span>
			</form>

			<h2>Últimos 14 días</h2>
			<table class="widefat striped" style="max-width:800px;">
				<thead><tr><th>Día</th><th>Leídas</th><th>Descartadas</th><th>Borradores</th><th>Fallidas</th><th>Coste IA</th></tr></thead>
				<tbody>
				<?php foreach ( array_reverse( $daily, true ) as $day => $row ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $day ) ) ); ?></td>
						<td><?php echo (int) $row['read']; ?></td>
						<td><?php echo (int) $row['skipped']; ?></td>
						<td><?php echo (int) $row['done']; ?></td>
						<td><?php echo (int) $row['failed']; ?></td>
						<td>$<?php echo esc_html( number_format_i18n( $row['cost'], 3 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Por fuente</h2>
			<table class="widefat striped" style="max-width:800px;">
				<thead><tr><th>Fuente</th><th>Leídas</th><th>Descartadas</th><th>Borradores</th><th>Fallidas</th></tr></thead>
				<tbody>
				<?php foreach ( BHNA_Settings::feeds() as $feed ) : ?>
					<?php $row = isset( $by_feed[ $feed['id'] ] ) ? $by_feed[ $feed['id'] ] : array( 'read' => 0, 'skipped' => 0, 'done' => 0, 'failed' => 0 ); ?>
					<tr>
						<td><?php echo esc_html( BHNA_Fetcher::label( $feed ) ); ?><?php echo $feed['enabled'] ? '' : ' <em>(inactiva)</em>'; ?></td>
						<td><?php echo (int) $row['read']; ?></td>
						<td><?php echo (int) $row['skipped']; ?></td>
						<td><?php echo (int) $row['done']; ?></td>
						<td><?php echo (int) $row['failed']; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">El coste es una estimación con las tarifas públicas de Anthropic. El importe real aparece en tu consola de Anthropic.</p>
		</div>
		<?php
	}

	private static function card( $label, $value ) {
		printf(
			'<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;min-width:160px;"><div style="color:#646970;font-size:12px;">%s</div><div style="font-size:22px;font-weight:600;margin-top:4px;">%s</div></div>',
			esc_html( $label ),
			esc_html( (string) $value )
		);
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

	/* ------------------------------------------------------------------ */
	/* Fuentes                                                             */
	/* ------------------------------------------------------------------ */

	public static function page_feeds() {
		$feeds   = BHNA_Settings::feeds();
		$feeds[] = BHNA_Settings::new_feed( array( 'id' => '' ) ); // Empty row to add a new feed.
		?>
		<div class="wrap">
			<h1>Noticias IA – Fuentes RSS</h1>
			<p>Solo se leen el título y el resumen de cada noticia. Las palabras clave de cada fuente se suman a las globales de Ajustes (una por línea o separadas por comas).</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bhna_save_feeds' ); ?>
				<input type="hidden" name="action" value="bhna_save_feeds">
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Activa</th><th>Medio</th><th>URL del feed</th><th>País</th><th>Grupo</th><th>Categoría WP</th><th>Incluir</th><th>Excluir</th><th>Borrar</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $feeds as $i => $feed ) : ?>
						<?php $name = 'feeds[' . $i . ']'; ?>
						<tr>
							<td>
								<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $feed['id'] ); ?>">
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $feed['enabled'] ); ?>>
							</td>
							<td><input type="text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( $feed['name'] ); ?>" placeholder="<?php echo $feed['id'] ? '' : 'Nueva fuente'; ?>" style="width:150px;"></td>
							<td><input type="url" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $feed['url'] ); ?>" style="width:280px;"></td>
							<td><?php self::select( $name . '[country]', BHNA_Settings::COUNTRIES, $feed['country'] ); ?></td>
							<td><?php self::select( $name . '[group]', BHNA_Settings::GROUPS, $feed['group'] ); ?></td>
							<td>
								<?php
								wp_dropdown_categories(
									array(
										'name'             => $name . '[category_id]',
										'selected'         => (int) $feed['category_id'],
										'show_option_none' => '— Ninguna —',
										'option_none_value' => 0,
										'hide_empty'       => 0,
										'hierarchical'     => 1,
									)
								);
								?>
							</td>
							<td><textarea name="<?php echo esc_attr( $name ); ?>[include]" rows="2" style="width:130px;"><?php echo esc_textarea( $feed['include'] ); ?></textarea></td>
							<td><textarea name="<?php echo esc_attr( $name ); ?>[exclude]" rows="2" style="width:130px;"><?php echo esc_textarea( $feed['exclude'] ); ?></textarea></td>
							<td><?php if ( $feed['id'] ) : ?><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete]" value="1"><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( 'Guardar fuentes' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save_feeds() {
		self::verify( 'save_feeds' );
		$input = isset( $_POST['feeds'] ) ? (array) wp_unslash( $_POST['feeds'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$feeds = array();

		foreach ( $input as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
				continue;
			}
			$url  = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$id   = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';

			// Skip the empty "new feed" row.
			if ( '' === $id && '' === $url && '' === $name ) {
				continue;
			}

			$country = isset( $row['country'] ) && array_key_exists( $row['country'], BHNA_Settings::COUNTRIES ) ? $row['country'] : 'other';
			$group   = isset( $row['group'] ) && array_key_exists( $row['group'], BHNA_Settings::GROUPS ) ? $row['group'] : 'noticias';

			$feeds[] = BHNA_Settings::new_feed(
				array_filter(
					array(
						'id'          => $id,
						'name'        => $name ? $name : wp_parse_url( $url, PHP_URL_HOST ),
						'url'         => $url,
						'country'     => $country,
						'group'       => $group,
						'category_id' => isset( $row['category_id'] ) ? absint( $row['category_id'] ) : 0,
						'include'     => isset( $row['include'] ) ? sanitize_textarea_field( $row['include'] ) : '',
						'exclude'     => isset( $row['exclude'] ) ? sanitize_textarea_field( $row['exclude'] ) : '',
						'enabled'     => ! empty( $row['enabled'] ) && $url ? 1 : 0,
					),
					function ( $value, $key ) {
						return 'id' !== $key || '' !== $value;
					},
					ARRAY_FILTER_USE_BOTH
				)
			);
		}

		BHNA_Settings::save_feeds( $feeds );
		self::redirect( 'bhna-feeds', 'Fuentes guardadas.' );
	}

	/* ------------------------------------------------------------------ */
	/* Ajustes                                                             */
	/* ------------------------------------------------------------------ */

	public static function page_settings() {
		$s          = BHNA_Settings::get();
		$key_in_cfg = defined( 'BHNA_ANTHROPIC_API_KEY' ) && BHNA_ANTHROPIC_API_KEY;
		$models     = array();
		foreach ( BHNA_Settings::MODELS as $id => $model ) {
			$models[ $id ] = $model['label'];
		}
		?>
		<div class="wrap">
			<h1>Noticias IA – Ajustes</h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bhna_save_settings' ); ?>
				<input type="hidden" name="action" value="bhna_save_settings">

				<h2>Claude (Anthropic)</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bhna_api_key">Clave API</label></th>
						<td>
							<?php if ( $key_in_cfg ) : ?>
								<p>Definida en <code>wp-config.php</code> (BHNA_ANTHROPIC_API_KEY).</p>
							<?php else : ?>
								<input type="password" id="bhna_api_key" name="api_key" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $s['api_key'] ? esc_attr( 'Guardada (••••' . substr( $s['api_key'], -4 ) . ')' ) : 'sk-ant-…'; ?>">
								<p class="description">Déjalo vacío para mantener la clave guardada.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Modelo</th>
						<td><?php self::select( 'model', $models, $s['model'] ); ?></td>
					</tr>
					<tr>
						<th scope="row">Esfuerzo</th>
						<td>
							<?php self::select( 'effort', array( 'low' => 'Bajo (más rápido y económico)', 'medium' => 'Medio (recomendado)', 'high' => 'Alto' ), $s['effort'] ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bhna_prompt">Instrucciones (prompt)</label></th>
						<td>
							<textarea id="bhna_prompt" name="system_prompt" rows="12" class="large-text code"><?php echo esc_textarea( $s['system_prompt'] ); ?></textarea>
							<p class="description">Estilo y reglas de redacción. La respuesta siempre se pide con titular, párrafos, extracto, meta descripción y etiquetas.</p>
						</td>
					</tr>
				</table>

				<h2>Filtro y límites</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bhna_include">Palabras clave de inclusión</label></th>
						<td>
							<textarea id="bhna_include" name="include_keywords" rows="5" class="large-text"><?php echo esc_textarea( $s['include_keywords'] ); ?></textarea>
							<p class="description">Una por línea o separadas por comas. Si está vacío, se procesan todas las noticias (salvo las excluidas).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bhna_exclude">Palabras clave de exclusión</label></th>
						<td><textarea id="bhna_exclude" name="exclude_keywords" rows="3" class="large-text"><?php echo esc_textarea( $s['exclude_keywords'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row">Límite diario de borradores</th>
						<td><input type="number" name="daily_limit" min="1" max="500" value="<?php echo esc_attr( $s['daily_limit'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row">Noticias por ejecución</th>
						<td><input type="number" name="batch_size" min="1" max="20" value="<?php echo esc_attr( $s['batch_size'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row">Frecuencia (minutos)</th>
						<td><input type="number" name="interval" min="5" max="1440" value="<?php echo esc_attr( $s['interval'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row">Intentos máximos por noticia</th>
						<td><input type="number" name="max_attempts" min="1" max="10" value="<?php echo esc_attr( $s['max_attempts'] ); ?>"></td>
					</tr>
				</table>

				<h2>WordPress</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Estado de las entradas</th>
						<td><?php self::select( 'post_status', array( 'draft' => 'Borrador', 'pending' => 'Pendiente de revisión' ), $s['post_status'] ); ?>
						<p class="description">El sistema nunca publica directamente.</p></td>
					</tr>
					<tr>
						<th scope="row">Autor</th>
						<td>
							<?php
							wp_dropdown_users(
								array(
									'name'             => 'post_author',
									'selected'         => (int) $s['post_author'],
									'show_option_none' => '— Primer administrador —',
									'option_none_value' => 0,
									'capability'       => array( 'edit_posts' ),
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bhna_attr">Texto de atribución</label></th>
						<td>
							<input type="text" id="bhna_attr" name="attribution_text" value="<?php echo esc_attr( $s['attribution_text'] ); ?>" class="regular-text">
							<p class="description"><code>{medio}</code> se sustituye por el nombre de la fuente.</p>
							<label><input type="checkbox" name="attribution_link" value="1" <?php checked( $s['attribution_link'] ); ?>> Enlazar a la noticia original</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Extras</th>
						<td>
							<label><input type="checkbox" name="seo_meta" value="1" <?php checked( $s['seo_meta'] ); ?>> Guardar la meta descripción en Yoast / Rank Math</label><br>
							<label><input type="checkbox" name="quality_check" value="1" <?php checked( $s['quality_check'] ); ?>> Control de calidad de nombres y cifras</label>
						</td>
					</tr>
				</table>

				<h2>Alertas</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Email</th>
						<td>
							<label><input type="checkbox" name="notify_email" value="1" <?php checked( $s['notify_email'] ); ?>> Avisar por email de cada borrador nuevo</label><br>
							<input type="text" name="email_recipients" value="<?php echo esc_attr( $s['email_recipients'] ); ?>" class="regular-text">
							<p class="description">Separa varios correos con comas.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Telegram (grupo privado de editores)</th>
						<td>
							<label><input type="checkbox" name="telegram_enabled" value="1" <?php checked( $s['telegram_enabled'] ); ?>> Avisar por Telegram</label><br>
							<input type="password" name="telegram_bot_token" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $s['telegram_bot_token'] ? esc_attr( 'Token guardado' ) : 'Token del bot (@BotFather)'; ?>"><br>
							<input type="text" name="telegram_chat_id" value="<?php echo esc_attr( $s['telegram_chat_id'] ); ?>" class="regular-text" placeholder="ID del grupo (ej. -1001234567890)">
						</td>
					</tr>
					<tr>
						<th scope="row">Errores</th>
						<td><label><input type="checkbox" name="error_alerts" value="1" <?php checked( $s['error_alerts'] ); ?>> Avisar al administrador de errores (como mucho una vez por hora por error)</label></td>
					</tr>
				</table>

				<h2>Datos</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Desinstalación</th>
						<td><label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>> Borrar ajustes y registros al eliminar el plugin</label></td>
					</tr>
				</table>

				<?php submit_button( 'Guardar ajustes' ); ?>
			</form>

			<hr>
			<h2>Pruebas</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
				<?php wp_nonce_field( 'bhna_test_claude' ); ?>
				<input type="hidden" name="action" value="bhna_test_claude">
				<?php submit_button( 'Probar Claude', 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<?php wp_nonce_field( 'bhna_test_telegram' ); ?>
				<input type="hidden" name="action" value="bhna_test_telegram">
				<?php submit_button( 'Probar Telegram', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
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
			'batch_size'          => isset( $p['batch_size'] ) ? max( 1, min( 20, absint( $p['batch_size'] ) ) ) : 5,
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

		// Secrets are only replaced when a new value is typed.
		if ( ! empty( $p['api_key'] ) ) {
			$values['api_key'] = sanitize_text_field( $p['api_key'] );
		}
		if ( ! empty( $p['telegram_bot_token'] ) ) {
			$values['telegram_bot_token'] = sanitize_text_field( $p['telegram_bot_token'] );
		}

		BHNA_Settings::update( $values );

		if ( (int) $old['interval'] !== $values['interval'] ) {
			BHNA_Cron::reschedule();
		}

		self::redirect( 'bhna-settings', 'Ajustes guardados.' );
	}

	public static function handle_test_claude() {
		self::verify( 'test_claude' );
		$item = array(
			'id'           => 0,
			'title'        => 'El Congreso aprueba una reforma de la ley de vivienda con 180 votos a favor',
			'summary'      => 'El Congreso de los Diputados aprobó este martes la reforma de la ley de vivienda, que limita las subidas del alquiler al 3% anual. La ministra Isabel Rodríguez defendió la norma en el pleno.',
			'published_at' => BHNA_Items::now(),
		);
		$feed   = BHNA_Settings::new_feed( array( 'id' => 'test', 'name' => 'Prueba', 'country' => 'es' ) );
		$result = BHNA_Claude::rewrite( $item, $feed );

		if ( is_wp_error( $result ) ) {
			self::redirect( 'bhna-settings', 'Error: ' . $result->get_error_message(), 'error' );
		}
		self::redirect( 'bhna-settings', sprintf( 'Claude responde correctamente (%s). Titular de prueba: "%s"', $result['model'], $result['titulo'] ) );
	}

	public static function handle_test_telegram() {
		self::verify( 'test_telegram' );
		$result = BHNA_Notifier::telegram( '✅ Prueba de Brotherhood News AI: las alertas de Telegram funcionan.' );
		if ( is_wp_error( $result ) ) {
			self::redirect( 'bhna-settings', 'Telegram: ' . $result->get_error_message(), 'error' );
		}
		self::redirect( 'bhna-settings', 'Mensaje de prueba enviado a Telegram.' );
	}

	/* ------------------------------------------------------------------ */
	/* Registro                                                            */
	/* ------------------------------------------------------------------ */

	public static function page_log() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$items    = BHNA_Items::recent( 100, $status );
		$statuses = array(
			''                       => 'Todas',
			BHNA_Items::DONE         => 'Borradores creados',
			BHNA_Items::QUEUED       => 'En cola',
			BHNA_Items::SKIPPED      => 'Descartadas',
			BHNA_Items::FAILED       => 'Fallidas',
		);
		?>
		<div class="wrap">
			<h1>Noticias IA – Registro</h1>

			<ul class="subsubsub">
				<?php
				$links = array();
				foreach ( $statuses as $key => $label ) {
					$url     = add_query_arg( array( 'page' => 'bhna-log', 'status' => $key ), admin_url( 'admin.php' ) );
					$links[] = sprintf( '<li><a href="%s"%s>%s</a></li>', esc_url( $url ), $status === $key ? ' class="current"' : '', esc_html( $label ) );
				}
				echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</ul>

			<table class="widefat striped">
				<thead><tr><th>Fecha</th><th>Fuente</th><th>Noticia original</th><th>Estado</th><th>Motivo / detalle</th><th>Borrador</th></tr></thead>
				<tbody>
				<?php if ( ! $items ) : ?>
					<tr><td colspan="6">Todavía no hay noticias.</td></tr>
				<?php endif; ?>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $item['created_at'] . ' UTC' ) ) ); ?></td>
						<td><?php echo esc_html( $item['source_name'] ); ?></td>
						<td><a href="<?php echo esc_url( $item['source_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['title'] ); ?></a></td>
						<td><?php echo esc_html( self::status_label( $item['status'] ) ); ?><?php echo $item['quality_flag'] ? ' ⚠️' : ''; ?></td>
						<td><?php echo esc_html( $item['reason'] ); ?></td>
						<td>
							<?php if ( $item['post_id'] ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>">Editar</a>
							<?php elseif ( in_array( $item['status'], array( BHNA_Items::FAILED, BHNA_Items::SKIPPED ), true ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'bhna_retry_item' ); ?>
									<input type="hidden" name="action" value="bhna_retry_item">
									<input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
									<button type="submit" class="button-link"><?php echo BHNA_Items::FAILED === $item['status'] ? 'Reintentar' : 'Procesar igualmente'; ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:32px;">Eventos</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bhna_clear_log' ); ?>
				<input type="hidden" name="action" value="bhna_clear_log">
				<?php submit_button( 'Vaciar eventos', 'secondary small', 'submit', false ); ?>
			</form>
			<table class="widefat striped" style="margin-top:8px;">
				<thead><tr><th style="width:140px;">Fecha</th><th style="width:80px;">Tipo</th><th>Mensaje</th></tr></thead>
				<tbody>
				<?php foreach ( BHNA_Logger::entries() as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i', $entry['time'] ) ); ?></td>
						<td><?php echo esc_html( $entry['level'] ); ?></td>
						<td><?php echo esc_html( $entry['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
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

	public static function status_label( $status ) {
		$labels = array(
			BHNA_Items::SKIPPED    => 'Descartada',
			BHNA_Items::QUEUED     => 'En cola',
			BHNA_Items::PROCESSING => 'Procesando',
			BHNA_Items::DONE       => 'Borrador creado',
			BHNA_Items::FAILED     => 'Fallida',
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
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

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	private static function select( $name, array $options, $selected ) {
		printf( '<select name="%s">', esc_attr( $name ) );
		foreach ( $options as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}
}

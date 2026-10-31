<?php
/**
 * Admin for the Amazon and social-network modules: settings pages, the product editor,
 * and the "Amazon y redes sociales" box on each post.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Extras_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'add_meta_boxes_' . BHNA_Amazon::CPT, array( __CLASS__, 'add_product_box' ) );
		add_action( 'save_post_' . BHNA_Amazon::CPT, array( __CLASS__, 'save_product' ), 5, 2 );
		add_action( 'add_meta_boxes_post', array( __CLASS__, 'add_post_box' ) );
		add_action( 'save_post_post', array( __CLASS__, 'save_post_box' ), 10, 2 );

		add_filter( 'manage_' . BHNA_Amazon::CPT . '_posts_columns', array( __CLASS__, 'product_columns' ) );
		add_action( 'manage_' . BHNA_Amazon::CPT . '_posts_custom_column', array( __CLASS__, 'product_column' ), 10, 2 );

		foreach ( array( 'save_amazon', 'save_social', 'test_x', 'test_tg_channel', 'create_deals_page' ) as $action ) {
			add_action( 'admin_post_bhna_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	public static function menu() {
		add_submenu_page( 'bhna', 'Amazon', 'Ajustes de Amazon', self::CAP, 'bhna-amazon', array( __CLASS__, 'page_amazon' ) );
		add_submenu_page( 'bhna', 'Redes sociales', 'Redes sociales', self::CAP, 'bhna-social', array( __CLASS__, 'page_social' ) );
	}

	private static function verify( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html( 'No tienes permisos para hacer esto.' ) );
		}
		check_admin_referer( 'bhna_' . $action );
	}

	private static function redirect( $page, $message, $type = 'success' ) {
		wp_safe_redirect( add_query_arg( array( 'page' => $page, 'bhna_msg' => rawurlencode( $message ), 'bhna_type' => $type ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function form_open( $action ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'bhna_' . $action );
		echo '<input type="hidden" name="action" value="bhna_' . esc_attr( $action ) . '">';
	}

	private static function secret_field( $name, $saved, $placeholder ) {
		printf(
			'<input type="password" name="%s" value="" class="regular-text" autocomplete="off" placeholder="%s">',
			esc_attr( $name ),
			esc_attr( $saved ? 'Guardado (••••' . substr( $saved, -4 ) . ')' : $placeholder )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Amazon settings                                                     */
	/* ------------------------------------------------------------------ */

	public static function page_amazon() {
		$s = BHNA_Settings::get();
		?>
		<div class="wrap">
			<h1>Noticias IA – Amazon</h1>
			<p>Los productos se añaden en <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . BHNA_Amazon::CPT ) ); ?>">Productos Amazon</a>. Sin la API de Amazon no se muestran precios ni imágenes (Amazon solo los permite a través de su API).</p>
			<?php self::form_open( 'save_amazon' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Activar</th>
						<td><label><input type="checkbox" name="amazon_enabled" value="1" <?php checked( $s['amazon_enabled'] ); ?>> Mostrar productos de Amazon en los artículos</label></td>
					</tr>
					<tr>
						<th scope="row">ID de afiliado Amazon.com</th>
						<td><input type="text" name="amazon_tag_com" value="<?php echo esc_attr( $s['amazon_tag_com'] ); ?>" class="regular-text" placeholder="tuid-20"></td>
					</tr>
					<tr>
						<th scope="row">ID de afiliado Amazon.es</th>
						<td>
							<input type="text" name="amazon_tag_es" value="<?php echo esc_attr( $s['amazon_tag_es'] ); ?>" class="regular-text" placeholder="tuid-21">
							<p class="description">Mientras esté vacío, las noticias de España mostrarán productos de Amazon.com con tu ID de Amazon.com, para no perder comisiones.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Tienda por defecto</th>
						<td>
							<select name="amazon_default_store">
								<?php foreach ( BHNA_Amazon::STORES as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['amazon_default_store'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Se usa en artículos sin país. Noticias de España ➔ Amazon.es; EE. UU. y Cuba ➔ Amazon.com.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Productos por artículo</th>
						<td><input type="number" name="amazon_max_products" min="1" max="6" value="<?php echo esc_attr( $s['amazon_max_products'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row">Posición</th>
						<td>
							<select name="amazon_position">
								<option value="end" <?php selected( $s['amazon_position'], 'end' ); ?>>Al final del artículo</option>
								<option value="middle" <?php selected( $s['amazon_position'], 'middle' ); ?>>Después del segundo párrafo</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Título del bloque</th>
						<td><input type="text" name="amazon_box_title" value="<?php echo esc_attr( $s['amazon_box_title'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row">Texto del botón</th>
						<td><input type="text" name="amazon_button_text" value="<?php echo esc_attr( $s['amazon_button_text'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row">Aviso de afiliación</th>
						<td>
							<textarea name="amazon_disclosure" rows="2" class="large-text"><?php echo esc_textarea( $s['amazon_disclosure'] ); ?></textarea>
							<p class="description">Amazon exige mostrar este aviso. Los enlaces llevan rel="sponsored", como recomienda Google.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Guardar ajustes de Amazon' ); ?>
			</form>

			<hr>
			<h2>Sección de Ofertas</h2>
			<p>Usa el shortcode <code>[bhna_ofertas]</code> en cualquier página (opciones: <code>limite="12"</code>, <code>tienda="es"</code> o <code>tienda="com"</code>, <code>titulo="…"</code>), o el widget <strong>"Ofertas de Amazon"</strong> en Apariencia ➔ Widgets.</p>
			<?php self::form_open( 'create_deals_page' ); ?>
				<?php submit_button( 'Crear página "Ofertas" (borrador)', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save_amazon() {
		self::verify( 'save_amazon' );
		$p = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		BHNA_Settings::update(
			array(
				'amazon_enabled'       => empty( $p['amazon_enabled'] ) ? 0 : 1,
				'amazon_tag_com'       => isset( $p['amazon_tag_com'] ) ? sanitize_text_field( $p['amazon_tag_com'] ) : '',
				'amazon_tag_es'        => isset( $p['amazon_tag_es'] ) ? sanitize_text_field( $p['amazon_tag_es'] ) : '',
				'amazon_default_store' => isset( $p['amazon_default_store'] ) && 'es' === $p['amazon_default_store'] ? 'es' : 'com',
				'amazon_max_products'  => isset( $p['amazon_max_products'] ) ? max( 1, min( 6, absint( $p['amazon_max_products'] ) ) ) : 3,
				'amazon_position'      => isset( $p['amazon_position'] ) && 'middle' === $p['amazon_position'] ? 'middle' : 'end',
				'amazon_box_title'     => isset( $p['amazon_box_title'] ) ? sanitize_text_field( $p['amazon_box_title'] ) : '',
				'amazon_button_text'   => isset( $p['amazon_button_text'] ) && '' !== trim( $p['amazon_button_text'] ) ? sanitize_text_field( $p['amazon_button_text'] ) : 'Ver en Amazon',
				'amazon_disclosure'    => isset( $p['amazon_disclosure'] ) && '' !== trim( $p['amazon_disclosure'] ) ? sanitize_textarea_field( $p['amazon_disclosure'] ) : BHNA_Settings::defaults()['amazon_disclosure'],
			)
		);
		BHNA_Amazon::flush_cache();
		self::redirect( 'bhna-amazon', 'Ajustes de Amazon guardados.' );
	}

	public static function handle_create_deals_page() {
		self::verify( 'create_deals_page' );
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'Ofertas',
				'post_content' => "<!-- wp:shortcode -->\n[bhna_ofertas limite=\"24\"]\n<!-- /wp:shortcode -->",
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			self::redirect( 'bhna-amazon', $page_id->get_error_message(), 'error' );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $page_id . '&action=edit' ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Product editor                                                      */
	/* ------------------------------------------------------------------ */

	public static function add_product_box() {
		add_meta_box( 'bhna_product', 'Datos del producto', array( __CLASS__, 'render_product_box' ), BHNA_Amazon::CPT, 'normal', 'high' );
	}

	public static function render_product_box( $post ) {
		$asin     = get_post_meta( $post->ID, '_bhna_asin', true );
		$url      = get_post_meta( $post->ID, '_bhna_url', true );
		$store    = get_post_meta( $post->ID, '_bhna_store', true );
		$cats     = (array) get_post_meta( $post->ID, '_bhna_cats', true );
		$selected = array_map( 'intval', array_filter( $cats ) );
		wp_nonce_field( 'bhna_product', 'bhna_product_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="bhna_asin_or_url">ASIN o enlace de Amazon</label></th>
				<td>
					<input type="text" id="bhna_asin_or_url" name="bhna_asin_or_url" value="<?php echo esc_attr( $asin ? $asin : $url ); ?>" class="large-text" placeholder="B0XXXXXXXX o https://www.amazon.com/dp/B0XXXXXXXX">
					<p class="description">El ID de afiliado se añade automáticamente al enlace.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Tienda</th>
				<td>
					<select name="bhna_store">
						<?php foreach ( BHNA_Amazon::STORES as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $store ? $store : 'com', $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bhna_description">Descripción breve</label></th>
				<td><textarea id="bhna_description" name="bhna_description" rows="2" maxlength="200" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, '_bhna_description', true ) ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row">Categorías relacionadas</th>
				<td>
					<div style="max-height:160px;overflow:auto;border:1px solid #dcdcde;padding:8px;">
						<?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) : ?>
							<label style="display:block;"><input type="checkbox" name="bhna_cats[]" value="<?php echo (int) $category->term_id; ?>" <?php checked( in_array( (int) $category->term_id, $selected, true ) ); ?>> <?php echo esc_html( $category->name ); ?></label>
						<?php endforeach; ?>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bhna_keywords">Palabras clave</label></th>
				<td>
					<textarea id="bhna_keywords" name="bhna_keywords" rows="3" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, '_bhna_keywords', true ) ); ?></textarea>
					<p class="description">El producto aparece en los artículos que tengan estas palabras o las categorías marcadas. Una por línea o separadas por comas.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Opciones</th>
				<td>
					<label><input type="checkbox" name="bhna_featured" value="1" <?php checked( get_post_meta( $post->ID, '_bhna_featured', true ) ); ?>> Destacado (tiene prioridad)</label>
				</td>
			</tr>
			<tr>
				<th scope="row">Oferta</th>
				<td>
					<label><input type="checkbox" name="bhna_deal" value="1" <?php checked( get_post_meta( $post->ID, '_bhna_deal', true ) ); ?>> Mostrar en la sección de Ofertas</label><br>
					<input type="text" name="bhna_deal_label" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bhna_deal_label', true ) ); ?>" placeholder="Ej. -30% u Oferta del día" style="margin-top:6px;">
					<label>Hasta: <input type="date" name="bhna_deal_until" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bhna_deal_until', true ) ); ?>"></label>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_product( $post_id, $post ) {
		if ( ! isset( $_POST['bhna_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bhna_product_nonce'] ), 'bhna_product' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$p     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$input = isset( $p['bhna_asin_or_url'] ) ? trim( sanitize_text_field( $p['bhna_asin_or_url'] ) ) : '';
		$asin  = BHNA_Amazon::extract_asin( $input );
		$store = isset( $p['bhna_store'] ) && 'es' === $p['bhna_store'] ? 'es' : 'com';

		// Detect the store from the link when one is pasted.
		if ( preg_match( '#amazon\.es#i', $input ) ) {
			$store = 'es';
		} elseif ( preg_match( '#amazon\.com(?![.\w])#i', $input ) ) {
			$store = 'com';
		}

		update_post_meta( $post_id, '_bhna_asin', $asin );
		update_post_meta( $post_id, '_bhna_url', $asin ? '' : esc_url_raw( $input ) );
		update_post_meta( $post_id, '_bhna_store', $store );
		update_post_meta( $post_id, '_bhna_description', isset( $p['bhna_description'] ) ? sanitize_textarea_field( $p['bhna_description'] ) : '' );
		update_post_meta( $post_id, '_bhna_cats', isset( $p['bhna_cats'] ) ? array_map( 'absint', (array) $p['bhna_cats'] ) : array() );
		update_post_meta( $post_id, '_bhna_keywords', isset( $p['bhna_keywords'] ) ? sanitize_textarea_field( $p['bhna_keywords'] ) : '' );
		update_post_meta( $post_id, '_bhna_featured', empty( $p['bhna_featured'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_bhna_deal', empty( $p['bhna_deal'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_bhna_deal_label', isset( $p['bhna_deal_label'] ) ? sanitize_text_field( $p['bhna_deal_label'] ) : '' );
		$until = isset( $p['bhna_deal_until'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $p['bhna_deal_until'] ) ? $p['bhna_deal_until'] : '';
		update_post_meta( $post_id, '_bhna_deal_until', $until );

		BHNA_Amazon::flush_cache();
	}

	public static function product_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );
		$columns['bhna_asin']  = 'ASIN / enlace';
		$columns['bhna_store'] = 'Tienda';
		$columns['bhna_deal']  = 'Oferta';
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public static function product_column( $column, $post_id ) {
		if ( 'bhna_asin' === $column ) {
			$asin = get_post_meta( $post_id, '_bhna_asin', true );
			echo esc_html( $asin ? $asin : get_post_meta( $post_id, '_bhna_url', true ) );
		} elseif ( 'bhna_store' === $column ) {
			echo 'es' === get_post_meta( $post_id, '_bhna_store', true ) ? 'Amazon.es' : 'Amazon.com';
		} elseif ( 'bhna_deal' === $column && get_post_meta( $post_id, '_bhna_deal', true ) ) {
			$label = get_post_meta( $post_id, '_bhna_deal_label', true );
			$until = get_post_meta( $post_id, '_bhna_deal_until', true );
			echo esc_html( trim( ( $label ? $label : 'Sí' ) . ( $until ? ' (hasta ' . $until . ')' : '' ) ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Box on each article                                                 */
	/* ------------------------------------------------------------------ */

	public static function add_post_box() {
		add_meta_box( 'bhna_extras', 'Amazon y redes sociales', array( __CLASS__, 'render_post_box' ), 'post', 'side', 'default' );
	}

	public static function render_post_box( $post ) {
		$store    = get_post_meta( $post->ID, '_bhna_amazon_store', true );
		$manual   = array_map( 'intval', (array) get_post_meta( $post->ID, '_bhna_products', true ) );
		$products = BHNA_Amazon::products();
		wp_nonce_field( 'bhna_post_extras', 'bhna_post_extras_nonce' );
		?>
		<p>
			<label for="bhna_amazon_store"><strong>Tienda de Amazon</strong></label><br>
			<select id="bhna_amazon_store" name="bhna_amazon_store" style="width:100%;">
				<option value="" <?php selected( $store, '' ); ?>>Automática (según el país)</option>
				<?php foreach ( BHNA_Amazon::STORES as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $store, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><strong>Productos elegidos a mano</strong><br><span class="description">Opcional. Se completan con los automáticos.</span></p>
		<div style="max-height:150px;overflow:auto;border:1px solid #dcdcde;padding:6px;">
			<?php if ( ! $products ) : ?>
				<em>Todavía no hay productos.</em>
			<?php endif; ?>
			<?php foreach ( $products as $product ) : ?>
				<label style="display:block;"><input type="checkbox" name="bhna_products[]" value="<?php echo (int) $product['id']; ?>" <?php checked( in_array( (int) $product['id'], $manual, true ) ); ?>> <?php echo esc_html( $product['title'] ); ?> <small>(<?php echo esc_html( $product['store'] ); ?>)</small></label>
			<?php endforeach; ?>
		</div>
		<p>
			<label><input type="checkbox" name="bhna_no_products" value="1" <?php checked( get_post_meta( $post->ID, '_bhna_no_products', true ) ); ?>> No mostrar productos en este artículo</label><br>
			<label><input type="checkbox" name="bhna_no_share" value="1" <?php checked( get_post_meta( $post->ID, '_bhna_no_share', true ) ); ?>> No compartir en redes sociales</label>
		</p>
		<?php
		$shared = array();
		foreach ( array( 'x', 'telegram' ) as $network ) {
			$time = get_post_meta( $post->ID, '_bhna_shared_' . $network, true );
			if ( $time ) {
				$shared[] = BHNA_Social::network_label( $network ) . ' (' . wp_date( 'd/m H:i', (int) $time ) . ')';
			}
		}
		if ( $shared ) {
			echo '<p class="description">Compartido en: ' . esc_html( implode( ', ', $shared ) ) . '</p>';
		}
	}

	public static function save_post_box( $post_id, $post ) {
		if ( ! isset( $_POST['bhna_post_extras_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bhna_post_extras_nonce'] ), 'bhna_post_extras' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$p     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$store = isset( $p['bhna_amazon_store'] ) && in_array( $p['bhna_amazon_store'], array( 'com', 'es' ), true ) ? $p['bhna_amazon_store'] : '';

		update_post_meta( $post_id, '_bhna_amazon_store', $store );
		update_post_meta( $post_id, '_bhna_products', isset( $p['bhna_products'] ) ? array_map( 'absint', (array) $p['bhna_products'] ) : array() );
		update_post_meta( $post_id, '_bhna_no_products', empty( $p['bhna_no_products'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_bhna_no_share', empty( $p['bhna_no_share'] ) ? 0 : 1 );
	}

	/* ------------------------------------------------------------------ */
	/* Social settings                                                     */
	/* ------------------------------------------------------------------ */

	public static function page_social() {
		$s = BHNA_Settings::get();
		?>
		<div class="wrap">
			<h1>Noticias IA – Redes sociales</h1>
			<p>Cuando un editor <strong>publica</strong> un artículo, se comparte automáticamente en las redes activas (nunca los borradores). Cada artículo puede excluirse desde su caja "Amazon y redes sociales".</p>
			<?php self::form_open( 'save_social' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Qué artículos compartir</th>
						<td>
							<label><input type="radio" name="social_share_all" value="1" <?php checked( $s['social_share_all'], 1 ); ?>> Todos los artículos publicados</label><br>
							<label><input type="radio" name="social_share_all" value="0" <?php checked( $s['social_share_all'], 0 ); ?>> Solo los creados por Noticias IA</label>
						</td>
					</tr>
				</table>

				<h2>X (Twitter)</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Activar</th>
						<td><label><input type="checkbox" name="x_enabled" value="1" <?php checked( $s['x_enabled'] ); ?>> Publicar en X</label></td>
					</tr>
					<tr>
						<th scope="row">API Key / API Secret</th>
						<td>
							<?php self::secret_field( 'x_api_key', $s['x_api_key'], 'API Key (Consumer Key)' ); ?><br>
							<?php self::secret_field( 'x_api_secret', $s['x_api_secret'], 'API Key Secret' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Access Token / Secret</th>
						<td>
							<?php self::secret_field( 'x_access_token', $s['x_access_token'], 'Access Token' ); ?><br>
							<?php self::secret_field( 'x_access_secret', $s['x_access_secret'], 'Access Token Secret' ); ?>
							<p class="description">Del portal de desarrolladores de X, con permisos de "Read and write".</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Plantilla</th>
						<td>
							<textarea name="x_template" rows="3" class="large-text"><?php echo esc_textarea( $s['x_template'] ); ?></textarea>
							<p class="description">Variables: <code>{titulo}</code>, <code>{extracto}</code>, <code>{enlace}</code>. Se recorta automáticamente a 280 caracteres. X muestra la imagen del artículo en la tarjeta del enlace.</p>
						</td>
					</tr>
				</table>

				<h2>Canal público de Telegram</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Activar</th>
						<td>
							<label><input type="checkbox" name="tg_channel_enabled" value="1" <?php checked( $s['tg_channel_enabled'] ); ?>> Publicar en el canal de Telegram</label>
							<p class="description">Usa el mismo bot que las alertas (Ajustes). Añádelo como administrador del canal. Las alertas de editores siguen yendo al grupo privado.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">ID del canal</th>
						<td><input type="text" name="tg_channel_id" value="<?php echo esc_attr( $s['tg_channel_id'] ); ?>" class="regular-text" placeholder="@micanal o -100…"></td>
					</tr>
					<tr>
						<th scope="row">Plantilla</th>
						<td>
							<textarea name="tg_channel_template" rows="4" class="large-text"><?php echo esc_textarea( $s['tg_channel_template'] ); ?></textarea>
							<p class="description">Variables: <code>{titulo}</code>, <code>{extracto}</code>, <code>{enlace}</code>. Admite &lt;b&gt; e &lt;i&gt;.</p>
						</td>
					</tr>
				</table>

				<h2>Próximamente</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Facebook</th>
						<td><input type="text" class="regular-text" disabled placeholder="Preparado para conectar (página de Facebook)"></td>
					</tr>
					<tr>
						<th scope="row">LinkedIn</th>
						<td><input type="text" class="regular-text" disabled placeholder="Preparado para conectar (página de empresa)"></td>
					</tr>
				</table>

				<?php submit_button( 'Guardar redes sociales' ); ?>
			</form>

			<hr>
			<h2>Comprobaciones</h2>
			<p class="description">Verifican las credenciales sin publicar nada.</p>
			<div style="display:flex;gap:8px;">
				<?php self::form_open( 'test_x' ); ?><?php submit_button( 'Verificar X', 'secondary', 'submit', false ); ?></form>
				<?php self::form_open( 'test_tg_channel' ); ?><?php submit_button( 'Verificar canal de Telegram', 'secondary', 'submit', false ); ?></form>
			</div>
		</div>
		<?php
	}

	public static function handle_save_social() {
		self::verify( 'save_social' );
		$p = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$values = array(
			'social_share_all'    => isset( $p['social_share_all'] ) && '0' === (string) $p['social_share_all'] ? 0 : 1,
			'x_enabled'           => empty( $p['x_enabled'] ) ? 0 : 1,
			'x_template'          => isset( $p['x_template'] ) && '' !== trim( $p['x_template'] ) ? sanitize_textarea_field( $p['x_template'] ) : BHNA_Settings::defaults()['x_template'],
			'tg_channel_enabled'  => empty( $p['tg_channel_enabled'] ) ? 0 : 1,
			'tg_channel_id'       => isset( $p['tg_channel_id'] ) ? sanitize_text_field( $p['tg_channel_id'] ) : '',
			'tg_channel_template' => isset( $p['tg_channel_template'] ) && '' !== trim( $p['tg_channel_template'] ) ? wp_kses( $p['tg_channel_template'], array( 'b' => array(), 'i' => array(), 'u' => array(), 'a' => array( 'href' => array() ) ) ) : BHNA_Settings::defaults()['tg_channel_template'],
		);
		foreach ( array( 'x_api_key', 'x_api_secret', 'x_access_token', 'x_access_secret' ) as $key ) {
			if ( ! empty( $p[ $key ] ) ) {
				$values[ $key ] = sanitize_text_field( $p[ $key ] );
			}
		}

		BHNA_Settings::update( $values );
		self::redirect( 'bhna-social', 'Redes sociales guardadas.' );
	}

	public static function handle_test_x() {
		self::verify( 'test_x' );
		$result = BHNA_Social::check_x();
		if ( is_wp_error( $result ) ) {
			self::redirect( 'bhna-social', 'X: ' . $result->get_error_message(), 'error' );
		}
		self::redirect( 'bhna-social', 'X conectado correctamente: ' . $result );
	}

	public static function handle_test_tg_channel() {
		self::verify( 'test_tg_channel' );
		$result = BHNA_Social::check_telegram_channel();
		if ( is_wp_error( $result ) ) {
			self::redirect( 'bhna-social', 'Telegram: ' . $result->get_error_message(), 'error' );
		}
		self::redirect( 'bhna-social', 'El bot tiene acceso al canal: ' . $result );
	}
}

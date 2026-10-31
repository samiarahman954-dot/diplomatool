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
		add_action( 'all_admin_notices', array( __CLASS__, 'products_header' ) );

		add_filter( 'manage_' . BHNA_Amazon::CPT . '_posts_columns', array( __CLASS__, 'product_columns' ) );
		add_action( 'manage_' . BHNA_Amazon::CPT . '_posts_custom_column', array( __CLASS__, 'product_column' ), 10, 2 );

		foreach ( array( 'save_amazon', 'save_social', 'create_deals_page' ) as $action ) {
			add_action( 'admin_post_bhna_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	public static function menu() {
		add_submenu_page( 'bhna', bhna__( 'Amazon' ), bhna__( 'Ajustes de Amazon' ), self::CAP, 'bhna-amazon', array( __CLASS__, 'page_amazon' ) );
		add_submenu_page( 'bhna', bhna__( 'Redes sociales' ), bhna__( 'Redes sociales' ), self::CAP, 'bhna-social', array( __CLASS__, 'page_social' ) );
	}

	/** The plugin navigation on top of the "Productos Amazon" list, so every screen looks the same. */
	public static function products_header() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . BHNA_Amazon::CPT !== $screen->id ) {
			return;
		}
		echo '<div class="bhna-wrap" style="min-height:0;padding-bottom:0;margin-bottom:-20px;">';
		echo '<div class="bhna-header" style="margin-bottom:0;"><div class="bhna-brand"><div class="bhna-logo">BH</div><div><h1>Brotherhood News AI</h1><small>' . esc_html( bhna__( 'Productos de Amazon' ) ) . '</small></div></div><nav class="bhna-nav">';
		foreach ( BHNA_UI::nav_items() as $slug => $item ) {
			printf(
				'<a href="%s" class="%s"><span class="dashicons %s"></span>%s</a>',
				esc_url( $item[2] ),
				'bhna-products' === $slug ? 'is-active' : '',
				esc_attr( $item[1] ),
				esc_html( bhna__( $item[0] ) )
			);
		}
		echo '</nav>' . BHNA_I18N::switcher() . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/* ------------------------------------------------------------------ */
	/* Amazon settings                                                     */
	/* ------------------------------------------------------------------ */

	public static function page_amazon() {
		$s        = BHNA_Settings::get();
		$products = wp_count_posts( BHNA_Amazon::CPT );
		$total    = $products ? (int) $products->publish : 0;
		$deals    = count( BHNA_Amazon::deals( '', 500 ) );

		BHNA_UI::page_open(
			'bhna-amazon',
			'Amazon',
			'Productos de afiliado en los artículos y sección de ofertas. Sin la API de Amazon no se muestran precios ni imágenes (Amazon solo los permite mediante su API).',
			'<a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=' . BHNA_Amazon::CPT ) ) . '">' . esc_html( bhna__( 'Añadir producto' ) ) . '</a>'
		);

		echo '<div class="bhna-kpis">';
		BHNA_UI::kpi( 'Módulo', $s['amazon_enabled'] ? 'Activo' : 'Inactivo', 'dashicons-amazon', $s['amazon_enabled'] ? 'success' : 'warning' );
		BHNA_UI::kpi( 'Productos', $total, 'dashicons-cart' );
		BHNA_UI::kpi( 'Ofertas activas', $deals, 'dashicons-tag' );
		BHNA_UI::kpi( 'Tienda España', '' !== $s['amazon_tag_es'] ? 'Amazon.es' : 'Amazon.com (sin ID .es)', 'dashicons-location', '' !== $s['amazon_tag_es'] ? 'success' : 'warning' );
		echo '</div>';

		BHNA_UI::form_open( 'save_amazon' );

		BHNA_UI::card_open( 'Módulo e IDs de afiliado', 'dashicons-admin-network' );
		BHNA_UI::field( 'Activar', BHNA_UI::toggle( 'amazon_enabled', $s['amazon_enabled'], 'Mostrar productos de Amazon en los artículos' ) );
		BHNA_UI::field( 'ID de Amazon.com', BHNA_UI::text( 'amazon_tag_com', $s['amazon_tag_com'], 'tuid-20' ), 'El identificador de afiliado que termina en -20.', 'bhna_amazon_tag_com' );
		BHNA_UI::field( 'ID de Amazon.es', BHNA_UI::text( 'amazon_tag_es', $s['amazon_tag_es'], 'tuid-21' ), 'Mientras esté vacío, las noticias de España muestran productos de Amazon.com con tu ID de .com, para no perder comisiones.', 'bhna_amazon_tag_es' );
		BHNA_UI::field( 'Tienda por defecto', BHNA_UI::select( 'amazon_default_store', BHNA_Amazon::STORES, $s['amazon_default_store'] ), 'Para artículos sin país. España ➔ Amazon.es; EE. UU. y Cuba ➔ Amazon.com.', 'bhna_amazon_default_store' );
		BHNA_UI::card_close();

		BHNA_UI::card_open( 'Bloque de productos', 'dashicons-screenoptions', 'Cómo se ve el bloque dentro de los artículos.' );
		BHNA_UI::field( 'Productos por artículo', BHNA_UI::number( 'amazon_max_products', $s['amazon_max_products'], 1, 6 ), '', 'bhna_amazon_max_products' );
		BHNA_UI::field( 'Posición', BHNA_UI::select( 'amazon_position', array( 'end' => 'Al final del artículo', 'middle' => 'Después del segundo párrafo' ), $s['amazon_position'] ), '', 'bhna_amazon_position' );
		BHNA_UI::field( 'Título del bloque', BHNA_UI::text( 'amazon_box_title', $s['amazon_box_title'] ), '', 'bhna_amazon_box_title' );
		BHNA_UI::field( 'Texto del botón', BHNA_UI::text( 'amazon_button_text', $s['amazon_button_text'] ), '', 'bhna_amazon_button_text' );
		BHNA_UI::field( 'Aviso de afiliación', BHNA_UI::textarea( 'amazon_disclosure', $s['amazon_disclosure'], 2 ), 'Amazon exige mostrarlo. Los enlaces llevan rel="sponsored", como recomienda Google.', 'bhna_amazon_disclosure' );
		BHNA_UI::card_close();

		BHNA_UI::savebar( 'Guardar ajustes de Amazon' );
		echo '</form>';

		BHNA_UI::card_open(
			'Sección de Ofertas',
			'dashicons-tag',
			'Marca un producto como "Oferta" para que aparezca aquí. Usa el shortcode <code>[bhna_ofertas]</code> (opciones: <code>limite="12"</code>, <code>tienda="es"</code> o <code>tienda="com"</code>, <code>titulo="…"</code>) o el widget "Ofertas de Amazon" en Apariencia ➔ Widgets.',
			BHNA_UI::action_button( 'create_deals_page', 'Crear página "Ofertas"', 'button' )
		);
		BHNA_UI::card_close();

		BHNA_UI::page_close();
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
		BHNA_Admin::redirect( 'bhna-amazon', 'Ajustes de Amazon guardados.' );
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
			BHNA_Admin::redirect( 'bhna-amazon', $page_id->get_error_message(), 'error' );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $page_id . '&action=edit' ) );
		exit;
	}

	private static function verify( $action ) {
		BHNA_Admin::verify( $action );
	}

	/* ------------------------------------------------------------------ */
	/* Product editor                                                      */
	/* ------------------------------------------------------------------ */

	public static function add_product_box() {
		add_meta_box( 'bhna_product', bhna__( 'Datos del producto' ), array( __CLASS__, 'render_product_box' ), BHNA_Amazon::CPT, 'normal', 'high' );
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
				<th scope="row"><label for="bhna_asin_or_url"><?php echo esc_html( bhna__( 'ASIN o enlace de Amazon' ) ); ?></label></th>
				<td>
					<input type="text" id="bhna_asin_or_url" name="bhna_asin_or_url" value="<?php echo esc_attr( $asin ? $asin : $url ); ?>" class="large-text" placeholder="<?php echo esc_attr( bhna__( 'B0XXXXXXXX o https://www.amazon.com/dp/B0XXXXXXXX' ) ); ?>">
					<p class="description"><?php echo esc_html( bhna__( 'El ID de afiliado se añade automáticamente al enlace.' ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html( bhna__( 'Tienda' ) ); ?></th>
				<td>
					<select name="bhna_store">
						<?php foreach ( BHNA_Amazon::STORES as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $store ? $store : 'com', $value ); ?>><?php echo esc_html( bhna__( $label ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bhna_description"><?php echo esc_html( bhna__( 'Descripción breve' ) ); ?></label></th>
				<td><textarea id="bhna_description" name="bhna_description" rows="2" maxlength="200" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, '_bhna_description', true ) ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html( bhna__( 'Categorías relacionadas' ) ); ?></th>
				<td>
					<div style="max-height:160px;overflow:auto;border:1px solid #dcdcde;padding:8px;">
						<?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) : ?>
							<label style="display:block;"><input type="checkbox" name="bhna_cats[]" value="<?php echo (int) $category->term_id; ?>" <?php checked( in_array( (int) $category->term_id, $selected, true ) ); ?>> <?php echo esc_html( $category->name ); ?></label>
						<?php endforeach; ?>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bhna_keywords"><?php echo esc_html( bhna__( 'Palabras clave' ) ); ?></label></th>
				<td>
					<textarea id="bhna_keywords" name="bhna_keywords" rows="3" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, '_bhna_keywords', true ) ); ?></textarea>
					<p class="description"><?php echo esc_html( bhna__( 'El producto aparece en los artículos que tengan estas palabras o las categorías marcadas. Una por línea o separadas por comas.' ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html( bhna__( 'Opciones' ) ); ?></th>
				<td>
					<label><input type="checkbox" name="bhna_featured" value="1" <?php checked( get_post_meta( $post->ID, '_bhna_featured', true ) ); ?>> <?php echo esc_html( bhna__( 'Destacado (tiene prioridad)' ) ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html( bhna__( 'Oferta' ) ); ?></th>
				<td>
					<label><input type="checkbox" name="bhna_deal" value="1" <?php checked( get_post_meta( $post->ID, '_bhna_deal', true ) ); ?>> <?php echo esc_html( bhna__( 'Mostrar en la sección de Ofertas' ) ); ?></label><br>
					<input type="text" name="bhna_deal_label" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bhna_deal_label', true ) ); ?>" placeholder="<?php echo esc_attr( bhna__( 'Ej. -30% u Oferta del día' ) ); ?>" style="margin-top:6px;">
					<label><?php echo esc_html( bhna__( 'Hasta:' ) ); ?> <input type="date" name="bhna_deal_until" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bhna_deal_until', true ) ); ?>"></label>
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
		$columns['bhna_asin']  = bhna__( 'ASIN / enlace' );
		$columns['bhna_store'] = bhna__( 'Tienda' );
		$columns['bhna_deal']  = bhna__( 'Oferta' );
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
			echo esc_html( trim( ( $label ? $label : bhna__( 'Sí' ) ) . ( $until ? ' (' . sprintf( bhna__( 'hasta %s' ), $until ) . ')' : '' ) ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Box on each article                                                 */
	/* ------------------------------------------------------------------ */

	public static function add_post_box() {
		add_meta_box( 'bhna_extras', bhna__( 'Amazon y redes sociales' ), array( __CLASS__, 'render_post_box' ), 'post', 'side', 'default' );
	}

	public static function render_post_box( $post ) {
		$store    = get_post_meta( $post->ID, '_bhna_amazon_store', true );
		$manual   = array_map( 'intval', (array) get_post_meta( $post->ID, '_bhna_products', true ) );
		$products = BHNA_Amazon::products();
		wp_nonce_field( 'bhna_post_extras', 'bhna_post_extras_nonce' );
		?>
		<p>
			<label for="bhna_amazon_store"><strong><?php echo esc_html( bhna__( 'Tienda de Amazon' ) ); ?></strong></label><br>
			<select id="bhna_amazon_store" name="bhna_amazon_store" style="width:100%;">
				<option value="" <?php selected( $store, '' ); ?>><?php echo esc_html( bhna__( 'Automática (según el país)' ) ); ?></option>
				<?php foreach ( BHNA_Amazon::STORES as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $store, $value ); ?>><?php echo esc_html( bhna__( $label ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><strong><?php echo esc_html( bhna__( 'Productos elegidos a mano' ) ); ?></strong><br><span class="description"><?php echo esc_html( bhna__( 'Opcional. Se completan con los automáticos.' ) ); ?></span></p>
		<div style="max-height:150px;overflow:auto;border:1px solid #dcdcde;padding:6px;">
			<?php if ( ! $products ) : ?>
				<em><?php echo esc_html( bhna__( 'Todavía no hay productos.' ) ); ?></em>
			<?php endif; ?>
			<?php foreach ( $products as $product ) : ?>
				<label style="display:block;"><input type="checkbox" name="bhna_products[]" value="<?php echo (int) $product['id']; ?>" <?php checked( in_array( (int) $product['id'], $manual, true ) ); ?>> <?php echo esc_html( $product['title'] ); ?> <small>(<?php echo esc_html( $product['store'] ); ?>)</small></label>
			<?php endforeach; ?>
		</div>
		<p>
			<label><input type="checkbox" name="bhna_no_products" value="1" <?php checked( get_post_meta( $post->ID, '_bhna_no_products', true ) ); ?>> <?php echo esc_html( bhna__( 'No mostrar productos en este artículo' ) ); ?></label><br>
			<label><input type="checkbox" name="bhna_no_share" value="1" <?php checked( get_post_meta( $post->ID, '_bhna_no_share', true ) ); ?>> <?php echo esc_html( bhna__( 'No compartir en redes sociales' ) ); ?></label>
		</p>
		<?php
		$shared = array();
		foreach ( array( 'x', 'telegram' ) as $network ) {
			$time = get_post_meta( $post->ID, '_bhna_shared_' . $network, true );
			if ( $time ) {
				$shared[] = bhna__( BHNA_Social::network_label( $network ) ) . ' (' . wp_date( 'd/m H:i', (int) $time ) . ')';
			}
		}
		if ( $shared ) {
			echo '<p class="description">' . esc_html( bhna__( 'Compartido en:' ) . ' ' . implode( ', ', $shared ) ) . '</p>';
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

		BHNA_UI::page_open(
			'bhna-social',
			'Redes sociales',
			'Cuando un editor <strong>publica</strong> un artículo, se comparte automáticamente en las redes activas (nunca los borradores). Cada artículo puede excluirse desde su caja "Amazon y redes sociales".'
		);

		echo '<div class="bhna-kpis">';
		BHNA_UI::kpi( 'X (Twitter)', $s['x_enabled'] ? 'Activo' : 'Inactivo', 'dashicons-twitter', $s['x_enabled'] ? 'success' : '' );
		BHNA_UI::kpi( 'Canal de Telegram', $s['tg_channel_enabled'] ? 'Activo' : 'Inactivo', 'dashicons-megaphone', $s['tg_channel_enabled'] ? 'success' : '' );
		BHNA_UI::kpi( 'Facebook / LinkedIn', 'Próximamente', 'dashicons-share' );
		echo '</div>';

		BHNA_UI::form_open( 'save_social' );

		BHNA_UI::card_open( 'General', 'dashicons-admin-settings' );
		BHNA_UI::field(
			'Qué artículos compartir',
			BHNA_UI::select( 'social_share_all', array( '1' => 'Todos los artículos publicados', '0' => 'Solo los creados por Noticias IA' ), (string) (int) $s['social_share_all'] ),
			'',
			'bhna_social_share_all'
		);
		BHNA_UI::card_close();

		BHNA_UI::card_open( 'X (Twitter)', 'dashicons-twitter', 'Crea una app en el portal de desarrolladores de X con permisos <strong>Read and write</strong> y copia aquí sus cuatro claves. El plan gratuito de X tiene un límite mensual de publicaciones.' );
		BHNA_UI::field( 'Activar', BHNA_UI::toggle( 'x_enabled', $s['x_enabled'], 'Publicar en X' ) );
		BHNA_UI::field( 'API Key', BHNA_UI::secret( 'x_api_key', $s['x_api_key'], 'API Key (Consumer Key)' ), '', 'bhna_x_api_key' );
		BHNA_UI::field( 'API Key Secret', BHNA_UI::secret( 'x_api_secret', $s['x_api_secret'], 'API Key Secret' ), '', 'bhna_x_api_secret' );
		BHNA_UI::field( 'Access Token', BHNA_UI::secret( 'x_access_token', $s['x_access_token'], 'Access Token' ), '', 'bhna_x_access_token' );
		BHNA_UI::field( 'Access Token Secret', BHNA_UI::secret( 'x_access_secret', $s['x_access_secret'], 'Access Token Secret' ), '', 'bhna_x_access_secret' );
		BHNA_UI::field( 'Plantilla', BHNA_UI::textarea( 'x_template', $s['x_template'], 3 ), 'Variables: <code>{titulo}</code>, <code>{extracto}</code>, <code>{enlace}</code>. Se recorta a 280 caracteres. X muestra la imagen del artículo en la tarjeta del enlace.', 'bhna_x_template' );
		BHNA_UI::field( 'Comprobar', '<button type="submit" class="button" name="bhna_after" value="test_x">' . esc_html( bhna__( 'Guardar y verificar X' ) ) . '</button>', 'Comprueba las claves sin publicar nada.' );
		BHNA_UI::card_close();

		BHNA_UI::card_open( 'Canal público de Telegram', 'dashicons-megaphone', 'Usa el mismo bot que las alertas (Ajustes IA ➔ Alertas). Añádelo como <strong>administrador del canal</strong>. Las alertas de editores siguen yendo al grupo privado.' );
		BHNA_UI::field( 'Activar', BHNA_UI::toggle( 'tg_channel_enabled', $s['tg_channel_enabled'], 'Publicar en el canal' ) );
		BHNA_UI::field( 'ID del canal', BHNA_UI::text( 'tg_channel_id', $s['tg_channel_id'], '@micanal o -100…' ), '', 'bhna_tg_channel_id' );
		BHNA_UI::field( 'Plantilla', BHNA_UI::textarea( 'tg_channel_template', $s['tg_channel_template'], 4 ), 'Variables: <code>{titulo}</code>, <code>{extracto}</code>, <code>{enlace}</code>. Admite &lt;b&gt; e &lt;i&gt;.', 'bhna_tg_channel_template' );
		BHNA_UI::field( 'Comprobar', '<button type="submit" class="button" name="bhna_after" value="test_tg_channel">' . esc_html( bhna__( 'Guardar y verificar canal' ) ) . '</button>', 'Comprueba que el bot tiene acceso al canal, sin publicar nada.' );
		BHNA_UI::card_close();

		BHNA_UI::card_open( 'Próximamente', 'dashicons-share', 'Preparado para conectar más adelante.' );
		BHNA_UI::field( 'Facebook', '<input type="text" class="regular-text" disabled placeholder="' . esc_attr( bhna__( 'Página de Facebook' ) ) . '">' . ' ' . BHNA_UI::badge( 'Preparado' ) );
		BHNA_UI::field( 'LinkedIn', '<input type="text" class="regular-text" disabled placeholder="' . esc_attr( bhna__( 'Página de empresa' ) ) . '">' . ' ' . BHNA_UI::badge( 'Preparado' ) );
		BHNA_UI::card_close();

		BHNA_UI::savebar( 'Guardar redes sociales' );
		echo '</form>';
		BHNA_UI::page_close();
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

		$after = isset( $p['bhna_after'] ) ? $p['bhna_after'] : '';
		if ( 'test_x' === $after ) {
			$result = BHNA_Social::check_x();
			if ( is_wp_error( $result ) ) {
				BHNA_Admin::redirect( 'bhna-social', 'Guardado. X: ' . $result->get_error_message(), 'error' );
			}
			BHNA_Admin::redirect( 'bhna-social', 'Guardado. X conectado correctamente: ' . $result );
		}
		if ( 'test_tg_channel' === $after ) {
			$result = BHNA_Social::check_telegram_channel();
			if ( is_wp_error( $result ) ) {
				BHNA_Admin::redirect( 'bhna-social', 'Guardado. Telegram: ' . $result->get_error_message(), 'error' );
			}
			BHNA_Admin::redirect( 'bhna-social', 'Guardado. El bot tiene acceso al canal: ' . $result );
		}

		BHNA_Admin::redirect( 'bhna-social', 'Redes sociales guardadas.' );
	}
}

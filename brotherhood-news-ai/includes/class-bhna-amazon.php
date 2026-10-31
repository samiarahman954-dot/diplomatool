<?php
/**
 * Amazon affiliate products: product library, automatic matching to articles, the product box,
 * the deals section (shortcode + widget) and Amazon.es / Amazon.com segmentation.
 *
 * Works without the Amazon API (semi-automatic): editors add products by ASIN or link. Amazon only
 * allows prices and official images through its API, so boxes show name, description and a button.
 *
 * Nothing here calls Amazon while a page loads: products are read from the database and cached.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_Amazon {

	const CPT          = 'bhna_product';
	const VERSION_OPT  = 'bhna_products_version';
	const CACHE_TTL    = 12 * HOUR_IN_SECONDS;
	const STORES       = array(
		'com' => 'Amazon.com (EE. UU.)',
		'es'  => 'Amazon.es (España)',
	);

	private static $styles_printed = false;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
		add_shortcode( 'bhna_ofertas', array( __CLASS__, 'shortcode_deals' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );

		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'flush_cache' ) );
		add_action( 'trashed_post', array( __CLASS__, 'maybe_flush_cache' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'maybe_flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'maybe_flush_cache' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => 'Productos Amazon',
					'singular_name' => 'Producto Amazon',
					'add_new'       => 'Añadir producto',
					'add_new_item'  => 'Añadir producto de Amazon',
					'edit_item'     => 'Editar producto',
					'all_items'     => 'Productos Amazon',
					'search_items'  => 'Buscar productos',
					'not_found'     => 'No hay productos.',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'bhna',
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	public static function register_widget() {
		register_widget( 'BHNA_Deals_Widget' );
	}

	/* ------------------------------------------------------------------ */
	/* Links                                                               */
	/* ------------------------------------------------------------------ */

	/** Extracts the 10-character ASIN from a raw ASIN or an Amazon product URL. */
	public static function extract_asin( $input ) {
		$input = trim( (string) $input );
		if ( preg_match( '/^[A-Z0-9]{10}$/i', $input ) ) {
			return strtoupper( $input );
		}
		if ( preg_match( '#/(?:dp|gp/product|gp/aw/d|exec/obidos/ASIN|o/ASIN)/([A-Z0-9]{10})#i', $input, $m ) ) {
			return strtoupper( $m[1] );
		}
		return '';
	}

	public static function tag( $store ) {
		return trim( (string) BHNA_Settings::get( 'es' === $store ? 'amazon_tag_es' : 'amazon_tag_com' ) );
	}

	/** Affiliate link for a product in its own store, with that store's tracking ID. */
	public static function link( array $product ) {
		$store = 'es' === $product['store'] ? 'es' : 'com';
		$tag   = self::tag( $store );

		if ( $product['asin'] ) {
			$url = 'https://www.amazon.' . $store . '/dp/' . $product['asin'] . '/';
			return $tag ? add_query_arg( 'tag', rawurlencode( $tag ), $url ) : $url;
		}

		// Links without an ASIN (e.g. a deals page): add the tag only to full amazon.com/.es URLs.
		// Short amzn.to links already carry the tag they were created with.
		$url  = esc_url_raw( $product['url'] );
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( $tag && preg_match( '/(^|\.)amazon\.(com|es)$/', $host ) ) {
			$url = add_query_arg( 'tag', rawurlencode( $tag ), remove_query_arg( 'tag', $url ) );
		}
		return $url;
	}

	/**
	 * Store for an article: the editor's choice, else from the news country (Spain → .es, others → .com),
	 * else the default. Without an Amazon.es tracking ID, .com is used so no commission is lost.
	 */
	public static function store_for_post( $post_id ) {
		$choice = get_post_meta( $post_id, '_bhna_amazon_store', true );
		if ( in_array( $choice, array( 'com', 'es' ), true ) ) {
			$store = $choice;
		} else {
			$country = get_post_meta( $post_id, '_bhna_country', true );
			if ( 'es' === $country ) {
				$store = 'es';
			} elseif ( in_array( $country, array( 'us', 'cu' ), true ) ) {
				$store = 'com';
			} else {
				$store = 'es' === BHNA_Settings::get( 'amazon_default_store' ) ? 'es' : 'com';
			}
		}
		if ( 'es' === $store && '' === self::tag( 'es' ) ) {
			$store = 'com';
		}
		return $store;
	}

	/* ------------------------------------------------------------------ */
	/* Product data (cached)                                               */
	/* ------------------------------------------------------------------ */

	public static function version() {
		return (int) get_option( self::VERSION_OPT, 1 );
	}

	public static function flush_cache() {
		update_option( self::VERSION_OPT, self::version() + 1, false );
	}

	public static function maybe_flush_cache( $post_id ) {
		if ( self::CPT === get_post_type( $post_id ) ) {
			self::flush_cache();
		}
	}

	/** All active products as plain arrays. One cached query; invalidated whenever a product changes. */
	public static function products() {
		$key      = 'bhna_products_' . self::version();
		$products = get_transient( $key );
		if ( is_array( $products ) ) {
			return $products;
		}

		$products = array();
		$posts    = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		foreach ( $posts as $post ) {
			$products[ $post->ID ] = self::product_from_post( $post );
		}

		set_transient( $key, $products, self::CACHE_TTL );
		return $products;
	}

	public static function product_from_post( WP_Post $post ) {
		$cats = get_post_meta( $post->ID, '_bhna_cats', true );
		return array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => (string) get_post_meta( $post->ID, '_bhna_description', true ),
			'asin'        => (string) get_post_meta( $post->ID, '_bhna_asin', true ),
			'url'         => (string) get_post_meta( $post->ID, '_bhna_url', true ),
			'store'       => 'es' === get_post_meta( $post->ID, '_bhna_store', true ) ? 'es' : 'com',
			'cats'        => is_array( $cats ) ? array_map( 'intval', $cats ) : array(),
			'keywords'    => BHNA_Settings::keyword_list( get_post_meta( $post->ID, '_bhna_keywords', true ) ),
			'featured'    => (bool) get_post_meta( $post->ID, '_bhna_featured', true ),
			'deal'        => (bool) get_post_meta( $post->ID, '_bhna_deal', true ),
			'deal_label'  => (string) get_post_meta( $post->ID, '_bhna_deal_label', true ),
			'deal_until'  => (string) get_post_meta( $post->ID, '_bhna_deal_until', true ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Matching                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Products for an article: the editor's manual picks first, then automatic matches by category
	 * (3 points each) and keyword found in the title or text (1 point each).
	 *
	 * @return array[] Product arrays.
	 */
	public static function products_for_post( WP_Post $post ) {
		$store = self::store_for_post( $post->ID );
		$max   = max( 1, (int) BHNA_Settings::get( 'amazon_max_products' ) );
		$key   = 'bhna_match_' . $post->ID . '_' . self::version() . '_' . $store . '_' . $max . '_' . md5( $post->post_modified_gmt );

		$ids = get_transient( $key );
		if ( ! is_array( $ids ) ) {
			$ids = self::match( $post, $store, $max );
			set_transient( $key, $ids, self::CACHE_TTL );
		}

		$all = self::products();
		$out = array();
		foreach ( $ids as $id ) {
			if ( isset( $all[ $id ] ) ) {
				$out[] = $all[ $id ];
			}
		}
		return $out;
	}

	public static function match( WP_Post $post, $store, $max ) {
		$all    = self::products();
		$manual = get_post_meta( $post->ID, '_bhna_products', true );
		$ids    = array();

		foreach ( is_array( $manual ) ? $manual : array() as $id ) {
			if ( isset( $all[ $id ] ) && count( $ids ) < $max ) {
				$ids[] = (int) $id;
			}
		}
		if ( count( $ids ) >= $max ) {
			return $ids;
		}

		$post_cats = wp_get_post_categories( $post->ID );
		$text      = BHNA_Filter::normalize( $post->post_title . ' ' . wp_strip_all_tags( $post->post_content ) );
		$scores    = array();

		foreach ( $all as $id => $product ) {
			if ( $product['store'] !== $store || in_array( $id, $ids, true ) ) {
				continue;
			}
			$score = 3 * count( array_intersect( $product['cats'], $post_cats ) );
			foreach ( $product['keywords'] as $keyword ) {
				if ( BHNA_Filter::contains( $text, $keyword ) ) {
					$score++;
				}
			}
			if ( $score > 0 ) {
				$scores[ $id ] = $score + ( $product['featured'] ? 0.5 : 0 );
			}
		}

		arsort( $scores );
		foreach ( array_keys( $scores ) as $id ) {
			if ( count( $ids ) >= $max ) {
				break;
			}
			$ids[] = (int) $id;
		}
		return $ids;
	}

	/* ------------------------------------------------------------------ */
	/* Front end                                                           */
	/* ------------------------------------------------------------------ */

	public static function filter_content( $content ) {
		// Skip excerpts and <head> (SEO plugins build descriptions from the content there).
		if ( ! BHNA_Settings::get( 'amazon_enabled' ) || ! is_singular( 'post' ) || doing_filter( 'get_the_excerpt' ) || doing_action( 'wp_head' ) ) {
			return $content;
		}
		// Block themes don't always run the loop, so compare with the queried post instead of in_the_loop().
		$post = get_post();
		if ( ! $post || (int) $post->ID !== (int) get_queried_object_id() || get_post_meta( $post->ID, '_bhna_no_products', true ) ) {
			return $content;
		}

		$products = self::products_for_post( $post );
		if ( ! $products ) {
			return $content;
		}

		$box = self::render_box( $products );

		if ( 'middle' === BHNA_Settings::get( 'amazon_position' ) ) {
			$offset = self::after_paragraph( $content, 2 );
			// Only when more paragraphs follow; short articles get the box at the end.
			if ( $offset && false !== strpos( $content, '</p>', $offset ) ) {
				return substr( $content, 0, $offset ) . $box . substr( $content, $offset );
			}
		}
		return $content . $box;
	}

	/** Position right after the $n-th closing </p>, or 0 if there are fewer paragraphs. */
	public static function after_paragraph( $content, $n ) {
		$offset = 0;
		for ( $i = 0; $i < $n; $i++ ) {
			$pos = strpos( $content, '</p>', $offset );
			if ( false === $pos ) {
				return 0;
			}
			$offset = $pos + 4;
		}
		return $offset;
	}

	public static function render_box( array $products ) {
		$html  = self::styles();
		$html .= '<aside class="bhna-amazon-box">';
		$html .= '<p class="bhna-amazon-heading">' . esc_html( BHNA_Settings::get( 'amazon_box_title' ) ) . '</p><ul>';
		foreach ( $products as $product ) {
			$html .= self::render_product( $product );
		}
		$html .= '</ul><p class="bhna-amazon-disclosure">' . esc_html( BHNA_Settings::get( 'amazon_disclosure' ) ) . '</p></aside>';
		return $html;
	}

	public static function render_product( array $product, $show_deal = true ) {
		$link  = esc_url( self::link( $product ) );
		$attrs = 'href="' . $link . '" target="_blank" rel="sponsored nofollow noopener"';
		$html  = '<li class="bhna-amazon-item">';
		$html .= '<a class="bhna-amazon-name" ' . $attrs . '>' . esc_html( $product['title'] ) . '</a>';
		if ( $show_deal && $product['deal'] && $product['deal_label'] ) {
			$html .= ' <span class="bhna-amazon-deal">' . esc_html( $product['deal_label'] ) . '</span>';
		}
		if ( $product['description'] ) {
			$html .= '<span class="bhna-amazon-desc">' . esc_html( $product['description'] ) . '</span>';
		}
		$html .= '<a class="bhna-amazon-btn" ' . $attrs . '>' . esc_html( BHNA_Settings::get( 'amazon_button_text' ) ) . '</a>';
		return $html . '</li>';
	}

	/** Small inline stylesheet, printed once per page and only when a box or deals list is shown. */
	public static function styles() {
		if ( self::$styles_printed ) {
			return '';
		}
		self::$styles_printed = true;
		return '<style>'
			. '.bhna-amazon-box,.bhna-deals{border:1px solid #e2e2e2;border-radius:8px;padding:16px;margin:24px 0;background:#fafafa}'
			. '.bhna-amazon-heading{font-weight:700;margin:0 0 12px}'
			. '.bhna-amazon-box ul,.bhna-deals ul{list-style:none;margin:0;padding:0;display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}'
			. '.bhna-amazon-item{background:#fff;border:1px solid #eee;border-radius:6px;padding:12px;display:flex;flex-direction:column;gap:6px}'
			. '.bhna-amazon-name{font-weight:600;text-decoration:none}'
			. '.bhna-amazon-desc{font-size:.9em;color:#555}'
			. '.bhna-amazon-deal{display:inline-block;background:#c7511f;color:#fff;font-size:.8em;font-weight:700;padding:2px 6px;border-radius:4px}'
			. '.bhna-amazon-btn{margin-top:auto;align-self:flex-start;background:#ffd814;color:#111;border-radius:20px;padding:6px 14px;font-size:.9em;text-decoration:none}'
			. '.bhna-amazon-disclosure{font-size:.75em;color:#777;margin:12px 0 0}'
			. '</style>';
	}

	/* ------------------------------------------------------------------ */
	/* Deals                                                               */
	/* ------------------------------------------------------------------ */

	/** Active deals: marked as deal and not expired. */
	public static function deals( $store = '', $limit = 12 ) {
		$today = wp_date( 'Y-m-d' );
		$deals = array();
		foreach ( self::products() as $product ) {
			if ( ! $product['deal'] ) {
				continue;
			}
			if ( $product['deal_until'] && $product['deal_until'] < $today ) {
				continue;
			}
			if ( $store && $product['store'] !== $store ) {
				continue;
			}
			$deals[] = $product;
		}
		usort(
			$deals,
			function ( $a, $b ) {
				return (int) $b['featured'] - (int) $a['featured'];
			}
		);
		return array_slice( $deals, 0, max( 1, (int) $limit ) );
	}

	/**
	 * [bhna_ofertas limite="12" tienda="es|com"] — the deals section.
	 */
	public static function shortcode_deals( $atts ) {
		$atts  = shortcode_atts( array( 'limite' => 12, 'tienda' => '', 'titulo' => '' ), $atts, 'bhna_ofertas' );
		$store = in_array( $atts['tienda'], array( 'com', 'es' ), true ) ? $atts['tienda'] : '';
		return self::render_deals( self::deals( $store, $atts['limite'] ), $atts['titulo'] );
	}

	public static function render_deals( array $deals, $title = '' ) {
		if ( ! $deals ) {
			return '<p class="bhna-deals-empty">No hay ofertas disponibles en este momento.</p>';
		}
		$html = self::styles() . '<div class="bhna-deals">';
		if ( $title ) {
			$html .= '<p class="bhna-amazon-heading">' . esc_html( $title ) . '</p>';
		}
		$html .= '<ul>';
		foreach ( $deals as $product ) {
			$html .= self::render_product( $product );
		}
		$html .= '</ul><p class="bhna-amazon-disclosure">' . esc_html( BHNA_Settings::get( 'amazon_disclosure' ) ) . '</p></div>';
		return $html;
	}
}

/**
 * "Ofertas de Amazon" widget for the home page or sidebar.
 */
class BHNA_Deals_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct( 'bhna_deals', 'Ofertas de Amazon', array( 'description' => 'Muestra las ofertas activas de Amazon.' ) );
	}

	public function widget( $args, $instance ) {
		$deals = BHNA_Amazon::deals( isset( $instance['store'] ) ? $instance['store'] : '', isset( $instance['number'] ) ? $instance['number'] : 4 );
		if ( ! $deals ) {
			return;
		}
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo BHNA_Amazon::render_deals( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) {
		$title  = isset( $instance['title'] ) ? $instance['title'] : 'Ofertas de Amazon';
		$number = isset( $instance['number'] ) ? (int) $instance['number'] : 4;
		$store  = isset( $instance['store'] ) ? $instance['store'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Título:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>">Número de ofertas:</label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" min="1" max="20" value="<?php echo esc_attr( $number ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'store' ) ); ?>">Tienda:</label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'store' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'store' ) ); ?>">
				<option value="" <?php selected( $store, '' ); ?>>Todas</option>
				<?php foreach ( BHNA_Amazon::STORES as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $store, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title'  => sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' ),
			'number' => max( 1, min( 20, absint( isset( $new_instance['number'] ) ? $new_instance['number'] : 4 ) ) ),
			'store'  => isset( $new_instance['store'] ) && array_key_exists( $new_instance['store'], BHNA_Amazon::STORES ) ? $new_instance['store'] : '',
		);
	}
}

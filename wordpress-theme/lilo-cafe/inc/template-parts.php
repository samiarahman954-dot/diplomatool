<?php
/**
 * Header and footer markup, shared by the theme templates and the Elementor Header/Footer widgets.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current request URL without query string or fragment, for active-link checks.
 */
function lilo_current_url() {
	global $wp;
	$path = isset( $wp->request ) ? $wp->request : '';
	return untrailingslashit( home_url( $path ) );
}

/**
 * Whether a nav link points at the page being viewed. Anchor links (#about) are never "current".
 */
function lilo_is_current_link( $url ) {
	$url = lilo_expand_url( $url );
	if ( '' === $url || false !== strpos( $url, '#' ) ) {
		return false;
	}
	$url = untrailingslashit( strtok( $url, '?' ) );
	return $url === lilo_current_url();
}

/**
 * Header markup.
 *
 * @param array $a {
 *     @type array|string $logo        Media setting/URL. Empty uses the site logo.
 *     @type string       $logo_link   Logo link.
 *     @type string       $source      'menu' (WordPress menu) or 'links'.
 *     @type int|string   $menu        Menu ID/slug or theme location when source is 'menu'.
 *     @type array        $links       [ [ 'text', 'link' => url control ] ] when source is 'links'.
 *     @type string       $cta_text    Button text.
 *     @type array|string $cta_link    Button link.
 *     @type bool         $hamburger   Collapse to a toggle on phones.
 * }
 */
function lilo_render_header( $a = array() ) {
	$a = wp_parse_args(
		$a,
		array(
			'logo'      => '',
			'logo_link' => home_url( '/' ),
			'source'    => 'menu',
			'menu'      => 'primary',
			'links'     => array(),
			'cta_text'  => lilo_opt( 'lilo_header_cta_text' ),
			'cta_link'  => lilo_opt( 'lilo_header_cta_url' ),
			'hamburger' => true,
		)
	);

	$nav_id = 'lilo-nav-' . wp_unique_id();
	ob_start();
	?>
	<div class="lilo-header<?php echo $a['hamburger'] ? ' lilo-header--collapsible' : ''; ?>">
		<div class="lilo-header__inner">
			<a class="lilo-header__logo" href="<?php echo esc_url( lilo_expand_url( $a['logo_link'] ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<?php echo lilo_logo_html( $a['logo'], 'lilo-header__logo-img' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
			<?php if ( $a['hamburger'] ) : ?>
				<button class="lilo-header__toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $nav_id ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'lilo-cafe' ); ?></span>
					<span class="lilo-header__bars" aria-hidden="true"></span>
				</button>
			<?php endif; ?>
			<nav class="lilo-nav" id="<?php echo esc_attr( $nav_id ); ?>" aria-label="<?php esc_attr_e( 'Primary', 'lilo-cafe' ); ?>">
				<?php
				if ( 'links' === $a['source'] ) {
					echo '<ul class="lilo-nav__list">';
					foreach ( (array) $a['links'] as $item ) {
						if ( empty( $item['text'] ) ) {
							continue;
						}
						$link    = isset( $item['link'] ) ? $item['link'] : array();
						$url     = is_array( $link ) ? ( isset( $link['url'] ) ? $link['url'] : '' ) : $link;
						$current = lilo_is_current_link( $url );
						printf(
							'<li class="menu-item%s"><a%s%s>%s</a></li>',
							$current ? ' current-menu-item' : '',
							lilo_link_attrs( $link ), // phpcs:ignore WordPress.Security.EscapeOutput
							$current ? ' aria-current="page"' : '',
							esc_html( $item['text'] )
						);
					}
					echo '</ul>';
				} else {
					$menu_args = array(
						'container'   => false,
						'menu_class'  => 'lilo-nav__list',
						'depth'       => 1,
						'fallback_cb' => 'lilo_menu_fallback',
					);
					if ( is_numeric( $a['menu'] ) && (int) $a['menu'] > 0 ) {
						$menu_args['menu'] = (int) $a['menu'];
					} elseif ( has_nav_menu( (string) $a['menu'] ) ) {
						$menu_args['theme_location'] = (string) $a['menu'];
					} else {
						$menu_args['menu'] = (string) $a['menu'];
					}
					wp_nav_menu( $menu_args );
				}
				if ( ! empty( $a['cta_text'] ) ) {
					printf( '<a class="lilo-btn lilo-btn--nav"%s>%s</a>', lilo_link_attrs( $a['cta_link'] ), esc_html( $a['cta_text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</nav>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Menu fallback when no menu is assigned: Home + pages.
 */
function lilo_menu_fallback() {
	echo '<ul class="lilo-nav__list">';
	printf( '<li class="menu-item%s"><a href="%s">%s</a></li>', is_front_page() ? ' current-menu-item' : '', esc_url( home_url( '/' ) ), esc_html__( 'Home', 'lilo-cafe' ) );
	$pages = get_pages(
		array(
			'sort_column' => 'menu_order,post_title',
			'number'      => 5,
			'exclude'     => (int) get_option( 'page_on_front' ),
		)
	);
	foreach ( $pages as $page ) {
		printf( '<li class="menu-item%s"><a href="%s">%s</a></li>', is_page( $page->ID ) ? ' current-menu-item' : '', esc_url( get_permalink( $page ) ), esc_html( get_the_title( $page ) ) );
	}
	echo '</ul>';
}

/**
 * Logo markup: given image, else the Customizer logo, else the bundled logo.
 */
function lilo_logo_html( $logo = '', $class = '' ) {
	$alt = get_bloginfo( 'name' );
	if ( ( is_array( $logo ) && ( ! empty( $logo['id'] ) || ! empty( $logo['url'] ) ) ) || ( is_string( $logo ) && '' !== $logo ) ) {
		return lilo_img( $logo, $alt, 'medium_large', array( 'class' => $class, 'loading' => 'eager' ) );
	}
	$custom = get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		return lilo_img( array( 'id' => $custom ), $alt, 'medium_large', array( 'class' => $class, 'loading' => 'eager' ) );
	}
	return lilo_img( lilo_asset( 'lilo-logo.png' ), $alt, 'full', array( 'class' => $class, 'loading' => 'eager' ) );
}

/**
 * Default footer columns from the Customizer.
 */
function lilo_default_footer_columns() {
	$social = array();
	foreach ( array( 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook' ) as $key => $label ) {
		$url = lilo_opt( "lilo_social_{$key}" );
		if ( $url ) {
			$social[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
		}
	}
	return array(
		array(
			'title' => lilo_opt( 'lilo_footer_col1_title' ),
			'text'  => lilo_opt( 'lilo_footer_col1_text' ),
		),
		array(
			'title' => lilo_opt( 'lilo_footer_col2_title' ),
			'text'  => lilo_opt( 'lilo_footer_col2_text' ),
		),
		array(
			'title' => lilo_opt( 'lilo_footer_col3_title' ),
			'text'  => implode( "\n", $social ),
			'links' => true,
		),
	);
}

/**
 * Footer markup.
 *
 * @param array $a {
 *     @type array|string $logo      Media setting/URL. Empty uses footer/site logo.
 *     @type array        $columns   [ [ 'title', 'text', 'links' => bool ] ].
 *     @type string       $copyright Copyright line; {year} is replaced.
 * }
 */
function lilo_render_footer( $a = array() ) {
	$a = wp_parse_args(
		$a,
		array(
			'logo'      => '',
			'columns'   => lilo_default_footer_columns(),
			'copyright' => lilo_opt( 'lilo_copyright' ),
		)
	);
	if ( empty( $a['logo'] ) && lilo_opt( 'lilo_footer_logo' ) ) {
		$a['logo'] = array( 'id' => (int) lilo_opt( 'lilo_footer_logo' ) );
	}
	ob_start();
	?>
	<div class="lilo-footer">
		<div class="lilo-footer__grid">
			<a class="lilo-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo lilo_logo_html( $a['logo'], 'lilo-footer__logo-img' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<?php foreach ( (array) $a['columns'] as $col ) : ?>
				<div class="lilo-footer__col<?php echo ! empty( $col['links'] ) ? ' lilo-footer__col--links' : ''; ?>">
					<?php if ( ! empty( $col['title'] ) ) : ?>
						<span class="lilo-label"><?php echo esc_html( $col['title'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $col['links'] ) ) : ?>
						<?php echo lilo_kses( $col['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php else : ?>
						<span class="lilo-footer__text"><?php echo lilo_kses( $col['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $a['copyright'] ) ) : ?>
			<div class="lilo-footer__bottom"><?php echo lilo_kses( str_replace( '{year}', gmdate( 'Y' ), $a['copyright'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

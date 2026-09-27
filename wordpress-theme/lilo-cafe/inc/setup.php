<?php
/**
 * Theme supports, menus and assets.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $content_width ) ) {
	$content_width = 1176;
}

add_action( 'after_setup_theme', 'lilo_setup' );
function lilo_setup() {
	load_theme_textdomain( 'lilo-cafe', LILO_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 440,
			'width'       => 520,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'lilo-cafe' ),
			'footer'  => __( 'Footer social links', 'lilo-cafe' ),
		)
	);
}

/**
 * Google fonts used by the design.
 */
function lilo_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Allura&family=Gilda+Display&family=Jost:wght@400;500;600&display=swap';
}

add_action( 'wp_enqueue_scripts', 'lilo_enqueue_assets' );
function lilo_enqueue_assets() {
	wp_enqueue_style( 'lilo-fonts', lilo_fonts_url(), array(), null );
	wp_enqueue_style( 'lilo-main', LILO_URI . '/assets/css/main.css', array( 'lilo-fonts' ), LILO_VERSION );
	wp_add_inline_style( 'lilo-main', lilo_customizer_css() );

	wp_enqueue_script( 'lilo-main', LILO_URI . '/assets/js/main.js', array(), LILO_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

add_filter( 'wp_resource_hints', 'lilo_resource_hints', 10, 2 );
function lilo_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && wp_style_is( 'lilo-fonts', 'queue' ) ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
			'crossorigin',
		);
	}
	return $urls;
}

/**
 * Brand colors from the Customizer, as CSS variables.
 */
function lilo_customizer_css() {
	$vars = array(
		'--lilo-olive'      => lilo_opt( 'lilo_color_primary' ),
		'--lilo-olive-dark' => lilo_opt( 'lilo_color_primary_dark' ),
		'--lilo-coral'      => lilo_opt( 'lilo_color_accent' ),
		'--lilo-bg'         => lilo_opt( 'lilo_color_bg' ),
		'--lilo-text'       => lilo_opt( 'lilo_color_text' ),
	);
	$css = ':root{';
	foreach ( $vars as $name => $value ) {
		$value = sanitize_hex_color( $value );
		if ( $value ) {
			$css .= $name . ':' . $value . ';';
		}
	}
	return $css . '}';
}

add_filter( 'document_title_separator', 'lilo_title_separator' );
function lilo_title_separator() {
	return '·';
}

add_filter( 'body_class', 'lilo_body_classes' );
function lilo_body_classes( $classes ) {
	if ( lilo_opt( 'lilo_sticky_header' ) ) {
		$classes[] = 'lilo-has-sticky-header';
	}
	if ( is_singular() && lilo_is_elementor_page( get_the_ID() ) ) {
		$classes[] = 'lilo-elementor-page';
	}
	return $classes;
}

/**
 * Whether a post is built with Elementor.
 */
function lilo_is_elementor_page( $post_id ) {
	return (bool) get_post_meta( $post_id, '_elementor_edit_mode', true );
}

/**
 * Editor styles for the block editor, so posts look like the front end.
 */
add_action( 'after_setup_theme', 'lilo_editor_styles' );
function lilo_editor_styles() {
	add_theme_support( 'editor-styles' );
	add_editor_style( array( lilo_fonts_url(), 'assets/css/editor.css' ) );
}

/**
 * Tell users the theme works best with Elementor.
 */
add_action( 'admin_notices', 'lilo_elementor_notice' );
function lilo_elementor_notice() {
	if ( did_action( 'elementor/loaded' ) || ! current_user_can( 'install_plugins' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'appearance_page_lilo-demo-import' === $screen->id ) {
		return;
	}
	printf(
		'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
		esc_html__( 'Lilo Cafe is built for Elementor. The one-click demo import installs it for you and builds the whole site.', 'lilo-cafe' ),
		esc_url( admin_url( 'themes.php?page=lilo-demo-import' ) ),
		esc_html__( 'Open demo import', 'lilo-cafe' )
	);
}

/**
 * After switching to the theme, point the user at the importer.
 */
add_action( 'after_switch_theme', 'lilo_after_switch_theme' );
function lilo_after_switch_theme() {
	set_transient( 'lilo_show_welcome', 1, 60 );
}

add_action( 'admin_notices', 'lilo_welcome_notice' );
function lilo_welcome_notice() {
	if ( ! get_transient( 'lilo_show_welcome' ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	delete_transient( 'lilo_show_welcome' );
	printf(
		'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s <a class="button button-primary" href="%s">%s</a></p></div>',
		esc_html__( 'Welcome to Lilo Cafe!', 'lilo-cafe' ),
		esc_html__( 'Build the complete demo site with one click:', 'lilo-cafe' ),
		esc_url( admin_url( 'themes.php?page=lilo-demo-import' ) ),
		esc_html__( 'Import demo', 'lilo-cafe' )
	);
}

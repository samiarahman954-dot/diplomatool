<?php
/**
 * Elementor-editable header and footer ("Lilo Templates").
 *
 * A private-ish post type that Elementor can edit. The template picked in
 * Customizer > Lilo Cafe Theme > Header/Footer replaces the theme header/footer.
 * Elementor Pro Theme Builder locations are also supported and take priority.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'lilo_register_template_cpt' );
function lilo_register_template_cpt() {
	register_post_type(
		'lilo_template',
		array(
			'labels'              => array(
				'name'          => __( 'Header & Footer', 'lilo-cafe' ),
				'singular_name' => __( 'Header/Footer template', 'lilo-cafe' ),
				'add_new_item'  => __( 'Add header or footer template', 'lilo-cafe' ),
				'edit_item'     => __( 'Edit template', 'lilo-cafe' ),
				'menu_name'     => __( 'Header & Footer', 'lilo-cafe' ),
			),
			'public'              => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'show_in_nav_menus'   => false,
			'show_in_menu'        => 'themes.php',
			'show_in_rest'        => true,
			'has_archive'         => false,
			'rewrite'             => array( 'slug' => 'lilo-template' ),
			'supports'            => array( 'title', 'editor', 'elementor' ),
			'capability_type'     => 'page',
		)
	);
	add_post_type_support( 'lilo_template', 'elementor' );
}

/**
 * Make sure Elementor lists our post type as editable.
 */
add_filter( 'option_elementor_cpt_support', 'lilo_elementor_cpt_support' );
add_filter( 'default_option_elementor_cpt_support', 'lilo_elementor_cpt_support' );
function lilo_elementor_cpt_support( $value ) {
	if ( ! is_array( $value ) ) {
		$value = array( 'page', 'post' );
	}
	if ( ! in_array( 'lilo_template', $value, true ) ) {
		$value[] = 'lilo_template';
	}
	return $value;
}

/**
 * Show templates on a blank canvas when viewed directly (this is also the Elementor editor preview).
 */
add_filter( 'template_include', 'lilo_template_canvas', 99 );
function lilo_template_canvas( $template ) {
	if ( is_singular( 'lilo_template' ) ) {
		$canvas = LILO_DIR . '/template-parts/canvas.php';
		if ( file_exists( $canvas ) ) {
			return $canvas;
		}
	}
	return $template;
}

/**
 * Keep templates out of search engines.
 */
add_action( 'wp_head', 'lilo_template_noindex', 1 );
function lilo_template_noindex() {
	if ( is_singular( 'lilo_template' ) ) {
		echo '<meta name="robots" content="noindex, nofollow">' . "\n";
	}
}

/**
 * ID of the Elementor template chosen for 'header' or 'footer', or 0.
 */
function lilo_hf_template_id( $type ) {
	if ( ! did_action( 'elementor/loaded' ) || is_singular( 'lilo_template' ) ) {
		return 0;
	}
	$id = (int) lilo_opt( 'lilo_' . $type . '_template' );
	if ( ! $id || 'publish' !== get_post_status( $id ) || 'lilo_template' !== get_post_type( $id ) ) {
		return 0;
	}
	return (int) apply_filters( 'lilo_hf_template_id', $id, $type );
}

/**
 * Load the chosen templates' Elementor CSS in <head>.
 */
add_action( 'wp_enqueue_scripts', 'lilo_hf_enqueue', 20 );
function lilo_hf_enqueue() {
	if ( ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
		return;
	}
	foreach ( array( 'header', 'footer' ) as $type ) {
		$id = lilo_hf_template_id( $type );
		if ( $id ) {
			$css = new \Elementor\Core\Files\CSS\Post( $id );
			$css->enqueue();
		}
	}
}

/**
 * Prints the header or footer: Elementor Pro location > Lilo template > theme default.
 */
function lilo_do_location( $type ) {
	if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $type ) ) {
		return;
	}

	$id = lilo_hf_template_id( $type );
	if ( $id ) {
		echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}

	echo 'header' === $type ? lilo_render_header() : lilo_render_footer(); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Elementor Pro: register header/footer locations so Theme Builder templates work too.
 */
add_action( 'elementor/theme/register_locations', 'lilo_register_elementor_locations' );
function lilo_register_elementor_locations( $manager ) {
	$manager->register_location( 'header' );
	$manager->register_location( 'footer' );
}

/**
 * Admin list column showing where a template is used.
 */
add_filter( 'manage_lilo_template_posts_columns', 'lilo_template_columns' );
function lilo_template_columns( $columns ) {
	$columns['lilo_used_as'] = __( 'Used as', 'lilo-cafe' );
	return $columns;
}

add_action( 'manage_lilo_template_posts_custom_column', 'lilo_template_column_value', 10, 2 );
function lilo_template_column_value( $column, $post_id ) {
	if ( 'lilo_used_as' !== $column ) {
		return;
	}
	$used = array();
	if ( (int) lilo_opt( 'lilo_header_template' ) === (int) $post_id ) {
		$used[] = __( 'Site header', 'lilo-cafe' );
	}
	if ( (int) lilo_opt( 'lilo_footer_template' ) === (int) $post_id ) {
		$used[] = __( 'Site footer', 'lilo-cafe' );
	}
	echo $used ? esc_html( implode( ', ', $used ) ) : '—';
	echo ' <a href="' . esc_url( admin_url( 'customize.php?autofocus[panel]=lilo_panel' ) ) . '">' . esc_html__( 'Change', 'lilo-cafe' ) . '</a>';
}

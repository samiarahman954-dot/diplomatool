<?php
/**
 * Customizer options: brand colors, header, footer and social links.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

add_action( 'customize_register', 'lilo_customize_register' );
function lilo_customize_register( $wp_customize ) {
	$defaults = lilo_option_defaults();

	$wp_customize->add_panel(
		'lilo_panel',
		array(
			'title'    => __( 'Lilo Cafe Theme', 'lilo-cafe' ),
			'priority' => 30,
		)
	);

	// Colors.
	$wp_customize->add_section( 'lilo_colors', array( 'title' => __( 'Brand colors', 'lilo-cafe' ), 'panel' => 'lilo_panel' ) );
	$colors = array(
		'lilo_color_primary'      => __( 'Primary (olive)', 'lilo-cafe' ),
		'lilo_color_primary_dark' => __( 'Primary hover', 'lilo-cafe' ),
		'lilo_color_accent'       => __( 'Accent (coral)', 'lilo-cafe' ),
		'lilo_color_bg'           => __( 'Page background', 'lilo-cafe' ),
		'lilo_color_text'         => __( 'Body text', 'lilo-cafe' ),
	);
	foreach ( $colors as $key => $label ) {
		$wp_customize->add_setting( $key, array( 'default' => $defaults[ $key ], 'sanitize_callback' => 'sanitize_hex_color' ) );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $key, array( 'label' => $label, 'section' => 'lilo_colors' ) ) );
	}

	// Header.
	$wp_customize->add_section(
		'lilo_header',
		array(
			'title'       => __( 'Header', 'lilo-cafe' ),
			'panel'       => 'lilo_panel',
			'description' => __( 'Pick an Elementor header template to design the header in Elementor. Without one, the theme header below is used (logo from Site Identity, links from the Primary menu).', 'lilo-cafe' ),
		)
	);
	$wp_customize->add_setting( 'lilo_header_template', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( 'lilo_header_template', array( 'label' => __( 'Elementor header template', 'lilo-cafe' ), 'section' => 'lilo_header', 'type' => 'select', 'choices' => lilo_template_choices() ) );
	$wp_customize->add_setting( 'lilo_sticky_header', array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean' ) );
	$wp_customize->add_control( 'lilo_sticky_header', array( 'label' => __( 'Sticky header', 'lilo-cafe' ), 'section' => 'lilo_header', 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'lilo_header_cta_text', array( 'default' => $defaults['lilo_header_cta_text'], 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'lilo_header_cta_text', array( 'label' => __( 'Button text', 'lilo-cafe' ), 'section' => 'lilo_header' ) );
	$wp_customize->add_setting( 'lilo_header_cta_url', array( 'default' => $defaults['lilo_header_cta_url'], 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'lilo_header_cta_url', array( 'label' => __( 'Button link', 'lilo-cafe' ), 'section' => 'lilo_header', 'type' => 'url' ) );

	// Footer.
	$wp_customize->add_section(
		'lilo_footer',
		array(
			'title'       => __( 'Footer', 'lilo-cafe' ),
			'panel'       => 'lilo_panel',
			'description' => __( 'Pick an Elementor footer template to design the footer in Elementor, or fill in the fields below.', 'lilo-cafe' ),
		)
	);
	$wp_customize->add_setting( 'lilo_footer_template', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( 'lilo_footer_template', array( 'label' => __( 'Elementor footer template', 'lilo-cafe' ), 'section' => 'lilo_footer', 'type' => 'select', 'choices' => lilo_template_choices() ) );
	$wp_customize->add_setting( 'lilo_footer_logo', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'lilo_footer_logo', array( 'label' => __( 'Footer logo (defaults to site logo)', 'lilo-cafe' ), 'section' => 'lilo_footer', 'mime_type' => 'image' ) ) );
	foreach ( array( 1, 2, 3 ) as $n ) {
		$wp_customize->add_setting( "lilo_footer_col{$n}_title", array( 'default' => $defaults[ "lilo_footer_col{$n}_title" ], 'sanitize_callback' => 'sanitize_text_field' ) );
		/* translators: %d: column number */
		$wp_customize->add_control( "lilo_footer_col{$n}_title", array( 'label' => sprintf( __( 'Column %d title', 'lilo-cafe' ), $n ), 'section' => 'lilo_footer' ) );
		if ( 3 !== $n ) {
			$wp_customize->add_setting( "lilo_footer_col{$n}_text", array( 'default' => $defaults[ "lilo_footer_col{$n}_text" ], 'sanitize_callback' => 'lilo_kses' ) );
			/* translators: %d: column number */
			$wp_customize->add_control( "lilo_footer_col{$n}_text", array( 'label' => sprintf( __( 'Column %d text', 'lilo-cafe' ), $n ), 'section' => 'lilo_footer', 'type' => 'textarea' ) );
		}
	}
	$wp_customize->add_setting( 'lilo_copyright', array( 'default' => $defaults['lilo_copyright'], 'sanitize_callback' => 'lilo_kses' ) );
	$wp_customize->add_control( 'lilo_copyright', array( 'label' => __( 'Copyright ({year} = current year)', 'lilo-cafe' ), 'section' => 'lilo_footer', 'type' => 'textarea' ) );

	// Social.
	$wp_customize->add_section( 'lilo_social', array( 'title' => __( 'Social links', 'lilo-cafe' ), 'panel' => 'lilo_panel' ) );
	foreach ( array( 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook' ) as $key => $label ) {
		$wp_customize->add_setting( "lilo_social_{$key}", array( 'default' => $defaults[ "lilo_social_{$key}" ], 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( "lilo_social_{$key}", array( 'label' => $label, 'section' => 'lilo_social', 'type' => 'url' ) );
	}
}

/**
 * Header/footer template choices for the Customizer.
 */
function lilo_template_choices() {
	$choices = array( 0 => __( '— Theme default —', 'lilo-cafe' ) );
	$posts   = get_posts(
		array(
			'post_type'      => 'lilo_template',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
		)
	);
	foreach ( $posts as $post ) {
		$choices[ $post->ID ] = $post->post_title;
	}
	return $choices;
}

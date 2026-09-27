<?php
/**
 * Site header.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#lilo-content"><?php esc_html_e( 'Skip to content', 'lilo-cafe' ); ?></a>
<header class="lilo-site-header" id="lilo-site-header">
	<?php lilo_do_location( 'header' ); ?>
</header>
<div class="lilo-site-content" id="lilo-content">

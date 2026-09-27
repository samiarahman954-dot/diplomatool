<?php
/**
 * Template Name: Lilo Full Width (no title)
 * Template Post Type: page, post
 *
 * Header, footer and the content edge to edge. Good for Elementor pages.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) {
	the_post();
	the_content();
}
get_footer();

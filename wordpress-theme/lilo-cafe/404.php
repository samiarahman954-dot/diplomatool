<?php
/**
 * Not found.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="lilo-main lilo-wrap lilo-404">
	<img src="<?php echo esc_url( lilo_asset( 'hibiscus.png' ) ); ?>" alt="" width="52" height="52">
	<div class="lilo-script"><?php esc_html_e( 'oops', 'lilo-cafe' ); ?></div>
	<h1 class="lilo-h1"><?php esc_html_e( 'This page wandered off', 'lilo-cafe' ); ?></h1>
	<p class="lilo-lead"><?php esc_html_e( 'The page you are looking for is not here. Head back home or take a look at the menu.', 'lilo-cafe' ); ?></p>
	<a class="lilo-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back home', 'lilo-cafe' ); ?></a>
</main>
<?php
get_footer();

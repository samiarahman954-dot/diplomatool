<?php
/**
 * Blog, archives and search results.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="lilo-main lilo-wrap lilo-archive">
	<header class="lilo-page-title">
		<?php if ( is_search() ) : ?>
			<div class="lilo-script"><?php esc_html_e( 'you searched for', 'lilo-cafe' ); ?></div>
			<h1 class="lilo-h1"><?php echo esc_html( get_search_query() ); ?></h1>
		<?php elseif ( is_archive() ) : ?>
			<div class="lilo-script"><?php esc_html_e( 'browse', 'lilo-cafe' ); ?></div>
			<h1 class="lilo-h1"><?php echo wp_kses_post( get_the_archive_title() ); ?></h1>
			<?php the_archive_description( '<div class="lilo-lead">', '</div>' ); ?>
		<?php else : ?>
			<div class="lilo-script"><?php esc_html_e( 'from the cafe', 'lilo-cafe' ); ?></div>
			<h1 class="lilo-h1"><?php echo is_home() && get_option( 'page_for_posts' ) ? esc_html( get_the_title( get_option( 'page_for_posts' ) ) ) : esc_html__( 'Journal', 'lilo-cafe' ); ?></h1>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="lilo-post-grid">
			<?php
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content' );
			}
			?>
		</div>
		<?php
		the_posts_pagination(
			array(
				'class'     => 'lilo-pagination',
				'prev_text' => '←',
				'next_text' => '→',
			)
		);
		?>
	<?php else : ?>
		<p class="lilo-lead"><?php esc_html_e( 'Nothing here yet. Try a search:', 'lilo-cafe' ); ?></p>
		<?php get_search_form(); ?>
	<?php endif; ?>
</main>
<?php
get_footer();

<?php
/**
 * Single posts.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	if ( lilo_is_elementor_page( get_the_ID() ) ) :
		the_content();
	else :
		?>
		<main class="lilo-main lilo-wrap lilo-wrap--narrow">
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'lilo-entry' ); ?>>
				<header class="lilo-page-title">
					<span class="lilo-label"><?php echo esc_html( get_the_date() ); ?> · <?php the_category( ', ' ); ?></span>
					<h1 class="lilo-h1"><?php the_title(); ?></h1>
				</header>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="lilo-entry__media"><?php the_post_thumbnail( 'large' ); ?></div>
				<?php endif; ?>
				<div class="lilo-entry__content">
					<?php
					the_content();
					wp_link_pages();
					?>
				</div>
				<footer class="lilo-entry__footer"><?php the_tags( '<span class="lilo-label">' . esc_html__( 'Tags', 'lilo-cafe' ) . '</span> ', ', ' ); ?></footer>
			</article>
			<?php
			the_post_navigation(
				array(
					'prev_text' => '← %title',
					'next_text' => '%title →',
				)
			);
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</main>
		<?php
	endif;
endwhile;

get_footer();

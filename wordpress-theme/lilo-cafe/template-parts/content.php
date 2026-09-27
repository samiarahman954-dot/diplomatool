<?php
/**
 * Post card in lists.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'lilo-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="lilo-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'medium_large' ); ?></a>
	<?php endif; ?>
	<div class="lilo-card__body">
		<span class="lilo-label"><?php echo esc_html( get_the_date() ); ?></span>
		<h2 class="lilo-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="lilo-card__excerpt"><?php the_excerpt(); ?></div>
		<a class="lilo-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more →', 'lilo-cafe' ); ?></a>
	</div>
</article>

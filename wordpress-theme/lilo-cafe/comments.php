<?php
/**
 * Comments.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="lilo-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="lilo-h2">
			<?php
			/* translators: %s: number of comments */
			printf( esc_html( _n( '%s comment', '%s comments', get_comments_number(), 'lilo-cafe' ) ), esc_html( number_format_i18n( get_comments_number() ) ) );
			?>
		</h2>
		<ol class="lilo-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>
		<?php the_comments_pagination(); ?>
	<?php endif; ?>
	<?php comment_form(); ?>
</section>

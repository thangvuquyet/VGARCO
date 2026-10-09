<?php

defined( 'ABSPATH' ) || exit;

get_header();

$heading = is_singular() ? 'h1' : 'h2';
?>
<div class="wrap">
	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'entry' ); ?>>
				<<?php echo $heading; ?>>
					<?php if ( is_singular() ) : ?>
						<?php the_title(); ?>
					<?php else : ?>
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					<?php endif; ?>
				</<?php echo $heading; ?>>
				<div class="entry-content"><?php is_singular() ? the_content() : the_excerpt(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Chưa có nội dung.', 'vgarco' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();

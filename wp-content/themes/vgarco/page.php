<?php

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	vgarco_breadcrumb(
		array(
			array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
			array( get_the_title() ),
		)
	);
	?>
	<article <?php post_class( 'page-basic wrap' ); ?>>
		<header class="page-basic-head">
			<h1><?php the_title(); ?></h1>
		</header>
		<div class="entry-content"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();

<?php

defined( 'ABSPATH' ) || exit;

$project = $args['project'] ?? array();
if ( ! $project ) {
	return;
}

$meta_right = implode( ' / ', array_filter( array( $project['years'], $project['types'][0]['name'] ?? '' ) ) );
$image_id   = (int) ( $project['card_image'] ?: $project['hero_image'] );
?>
<article class="project-card">
	<a href="<?php echo esc_url( $project['url'] ); ?>">
		<div class="project-photo">
			<?php
			echo vgarco_image(
				$image_id,
				array(
					'size'     => 'vg-1200',
					'sizes'    => ! empty( $args['arrow'] )
						? '(min-width: 1001px) 50vw, 100vw'
						: '(min-width: 1001px) 30vw, (min-width: 701px) 45vw, 100vw',
					'alt'      => $project['kind_label'] . ' — ' . $project['title'],
					'priority' => ! empty( $args['priority'] ),
				)
			);
			?>
			<?php if ( $image_id > 0 ) : ?>
				<span class="image-label"><?php echo esc_html( $project['kind_label'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $args['arrow'] ) ) : ?>
				<span class="circle-arrow" aria-hidden="true"><?php echo vgarco_icon( 'arrow-up-right' ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $project['location'] || '' !== $meta_right ) : ?>
			<div class="project-meta">
				<?php if ( '' !== $project['location'] ) : ?>
					<span><?php echo esc_html( $project['location'] ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $meta_right ) : ?>
					<span><?php echo esc_html( $meta_right ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<h3><?php echo esc_html( $project['title'] ); ?></h3>
	</a>
</article>

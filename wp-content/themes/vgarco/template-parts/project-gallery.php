<?php

defined( 'ABSPATH' ) || exit;

$gallery = $args['gallery'] ?? array();
$title   = $args['title'] ?? '';
if ( ! $gallery ) {
	return;
}

$total = count( $gallery );
$many  = $total > 1;

$label = static fn( array $item ): string => '' !== $item['caption'] ? $item['caption'] : $item['kind_label'];
?>
<section class="project-detail-gallery" aria-labelledby="gallery-title" data-gallery data-total="<?php echo esc_attr( (string) $total ); ?>"
	data-status="<?php /* translators: 1: số thứ tự ảnh, 2: tổng số ảnh, 3: chú thích. */ echo esc_attr( __( 'Ảnh %1$d / %2$d: %3$s', 'vgarco' ) ); ?>">
	<div class="wrap gallery-head">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Hình ảnh dự án', 'vgarco' ); ?></p>
			<h2 id="gallery-title"><?php echo vgarco_heading( __( "Từng góc nhìn.\n<em>Một câu chuyện.</em>", 'vgarco' ) ); ?></h2>
		</div>
		<?php if ( $many ) : ?>
			<p class="gallery-head-meta" aria-hidden="true"><span data-gallery-current>01</span><small>/ <?php echo esc_html( vgarco_pad( $total ) ); ?></small></p>
		<?php endif; ?>
	</div>

	<div class="project-gallery-stage" data-gallery-stage tabindex="0" role="group" aria-roledescription="<?php esc_attr_e( 'trình chiếu', 'vgarco' ); ?>" aria-label="<?php esc_attr_e( 'Trình chiếu hình ảnh dự án', 'vgarco' ); ?>">
		<?php foreach ( $gallery as $i => $item ) : ?>
			<figure class="gallery-slide<?php echo 0 === $i ? ' is-active' : ''; ?>" data-gallery-slide<?php echo 0 === $i ? '' : ' hidden'; ?>
				data-full="<?php echo esc_url( vgarco_image_url( $item['image'] ) ); ?>" data-label="<?php echo esc_attr( $label( $item ) ); ?>">
				<?php
				echo vgarco_image(
					$item['image'],
					array(
						'size'  => 'vg-1600',
						'sizes' => '100vw',
						'alt'   => $label( $item ) . ' — ' . $title,
					)
				);
				?>
				<figcaption>
					<?php if ( $many ) : ?>
						<span class="gallery-caption-index" aria-hidden="true"><?php echo esc_html( vgarco_pad( $i + 1 ) ); ?></span>
					<?php endif; ?>
					<span><?php echo esc_html( $label( $item ) ); ?></span>
				</figcaption>
			</figure>
		<?php endforeach; ?>

		<button type="button" class="gallery-expand" data-gallery-open aria-label="<?php esc_attr_e( 'Xem ảnh lớn', 'vgarco' ); ?>" hidden>
			<?php echo vgarco_icon( 'expand' ); ?>
		</button>

		<?php if ( $many ) : ?>
			<?php ?>
			<button type="button" class="gallery-toggle" data-gallery-toggle aria-pressed="false" hidden
				aria-label="<?php esc_attr_e( 'Tạm dừng chuyển động', 'vgarco' ); ?>"
				data-label-pause="<?php esc_attr_e( 'Tạm dừng chuyển động', 'vgarco' ); ?>"
				data-label-play="<?php esc_attr_e( 'Tiếp tục chuyển động', 'vgarco' ); ?>">
				<?php echo vgarco_icon( 'pause', 'icon-pause' ); ?>
				<?php echo vgarco_icon( 'play', 'icon-play' ); ?>
			</button>

			<div class="project-gallery-controls" hidden data-gallery-controls>
				<button type="button" class="gallery-nav" data-gallery-prev aria-label="<?php esc_attr_e( 'Ảnh trước', 'vgarco' ); ?>">
					<?php echo vgarco_icon( 'arrow-left' ); ?>
				</button>
				<div class="project-gallery-progress">
					<?php foreach ( $gallery as $i => $item ) : ?>
						<button type="button" data-gallery-dot="<?php echo esc_attr( (string) $i ); ?>"<?php echo 0 === $i ? ' class="is-active" aria-pressed="true"' : ' aria-pressed="false"'; ?>
							aria-label="<?php /* translators: %d: số thứ tự ảnh. */ echo esc_attr( sprintf( __( 'Xem ảnh %d', 'vgarco' ), $i + 1 ) ); ?>"><span></span></button>
					<?php endforeach; ?>
				</div>
				<button type="button" class="gallery-nav" data-gallery-next aria-label="<?php esc_attr_e( 'Ảnh tiếp theo', 'vgarco' ); ?>">
					<?php echo vgarco_icon( 'arrow-right' ); ?>
				</button>
			</div>
		<?php endif; ?>

		<div class="gallery-watermark" aria-hidden="true">VGARCO</div>
		<p class="sr-only" aria-live="polite" data-gallery-status></p>
	</div>

	<?php if ( $many ) : ?>
		<ul class="project-gallery-thumbs wrap">
			<?php foreach ( $gallery as $i => $item ) : ?>
				<li>
					<a href="<?php echo esc_url( vgarco_image_url( $item['image'] ) ); ?>" data-gallery-thumb="<?php echo esc_attr( (string) $i ); ?>"<?php echo 0 === $i ? ' class="is-active" aria-current="true"' : ''; ?>>
						<span class="thumb-index" aria-hidden="true"><?php echo esc_html( vgarco_pad( $i + 1 ) ); ?></span>
						<?php
						echo vgarco_image(
							$item['image'],
							array(
								'size'  => 'vg-480',
								'sizes' => '90px',
								'alt'   => '',
							)
						);
						?>
						<span class="thumb-label"><?php echo esc_html( $label( $item ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<dialog class="gallery-lightbox on-dark" data-gallery-lightbox aria-label="<?php esc_attr_e( 'Xem ảnh lớn', 'vgarco' ); ?>">
		<form method="dialog">
			<button class="lightbox-close" aria-label="<?php esc_attr_e( 'Đóng', 'vgarco' ); ?>"><?php echo vgarco_icon( 'close' ); ?></button>
		</form>
		<figure>
			<img alt="" data-lightbox-image>
			<figcaption data-lightbox-caption></figcaption>
		</figure>
		<?php if ( $many ) : ?>
			<button type="button" class="gallery-nav lightbox-prev" data-lightbox-prev aria-label="<?php esc_attr_e( 'Ảnh trước', 'vgarco' ); ?>"><?php echo vgarco_icon( 'arrow-left' ); ?></button>
			<button type="button" class="gallery-nav lightbox-next" data-lightbox-next aria-label="<?php esc_attr_e( 'Ảnh tiếp theo', 'vgarco' ); ?>"><?php echo vgarco_icon( 'arrow-right' ); ?></button>
		<?php endif; ?>
	</dialog>
</section>

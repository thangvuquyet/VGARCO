<?php
/**
 * Thẻ dự án (dùng ở trang chủ, danh mục dự án, dự án liên quan).
 * Trường thiếu tự ẩn: dự án chưa có địa điểm hoặc năm thì thẻ chỉ hiện loại công trình (UC-04 3a).
 *
 * @var array $args['project'] Dự án (xem vgarco_project()).
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

$project = $args['project'] ?? array();
if ( ! $project ) {
	return;
}

$meta_right = implode( ' / ', array_filter( array( $project['years'], $project['types'][0]['name'] ?? '' ) ) );
?>
<article class="project-card">
	<a href="<?php echo esc_url( $project['url'] ); ?>">
		<div class="project-photo">
			<?php
			echo vgarco_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup ảnh của WordPress.
				$project['card_image'] ?: $project['hero_image'],
				array(
					'size'  => 'vg-1200',
					'sizes' => '(min-width: 1001px) 30vw, (min-width: 701px) 45vw, 100vw',
					'alt'   => $project['kind_label'] . ' — ' . $project['title'],
				)
			);
			?>
			<span class="image-label"><?php echo esc_html( $project['kind_label'] ); ?></span>
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

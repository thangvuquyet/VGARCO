<?php
/**
 * Template Name: Lĩnh vực hoạt động
 */

defined( 'ABSPATH' ) || exit;

get_header();

$id       = (int) get_queried_object_id();
$services = vgarco_services();

$first_ref   = null;
$first_index = 0;
foreach ( $services as $index => $service ) {
	if ( $service['reference'] ) {
		$first_ref   = $service['reference'];
		$first_index = $index;
		break;
	}
}

$ref_data = static function ( ?array $ref ): array {
	if ( ! $ref ) {
		return array( 'image' => 0, 'alt' => '', 'title' => '' );
	}
	return array(
		'image' => (int) ( $ref['card_image'] ?: $ref['hero_image'] ),
		'alt'   => $ref['kind_label'] . ' — ' . $ref['title'],
		'title' => $ref['title'],
	);
};

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Lĩnh vực hoạt động', 'vgarco' ) ),
	)
);
?>

<section class="services on-dark" aria-labelledby="services-title">
	<div class="wrap">
		<div class="services-head">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Chuyên môn', 'vgarco' ); ?></p>
				<h1 id="services-title"><?php echo vgarco_field_heading( 'hero_tieu_de', __( "Tư duy tổng thể.\n<em>Chăm chút từng chi tiết.</em>", 'vgarco' ), $id ); ?></h1>
			</div>
			<p class="section-description"><?php echo esc_html( vgarco_field_text( 'hero_mo_ta', __( 'Kết nối kiến trúc và kỹ thuật, từ khảo sát ban đầu đến thiết kế và quản lý triển khai.', 'vgarco' ), $id ) ); ?></p>
		</div>

		<?php if ( $services ) : ?>
			<div class="services-body" data-services>
				<?php $first = $ref_data( $first_ref ); ?>
				<?php if ( $first['image'] > 0 ) : ?>
					<figure class="service-image">
						<?php
						echo vgarco_image(
							$first['image'],
							array(
								'size'  => 'vg-1200',
								'sizes' => '(min-width: 1000px) 38vw, 100vw',
								'alt'   => $first['alt'],
								'attr'  => array( 'data-service-image' => '' ),
							)
						);
						?>
						<figcaption>
							<span><span data-service-title><?php echo esc_html( $first['title'] ); ?></span><small><?php esc_html_e( 'Hình ảnh dự án tham khảo', 'vgarco' ); ?></small></span>
							<span aria-hidden="true" data-service-number><?php echo esc_html( vgarco_pad( $first_index + 1 ) ); ?></span>
						</figcaption>
					</figure>
				<?php endif; ?>

				<div class="service-list">
					<?php foreach ( $services as $i => $service ) : ?>
						<?php $ref = $ref_data( $service['reference'] ); ?>
						<details class="service-item" id="<?php echo esc_attr( $service['anchor'] ); ?>"<?php echo 0 === $i ? ' open' : ''; ?>
							data-number="<?php echo esc_attr( vgarco_pad( $i + 1 ) ); ?>"
							<?php if ( $ref['image'] > 0 ) : ?>
								data-image="<?php echo esc_url( vgarco_image_url( $ref['image'], 'vg-1200' ) ); ?>"
								data-alt="<?php echo esc_attr( $ref['alt'] ); ?>"
								data-ref="<?php echo esc_attr( $ref['title'] ); ?>"
							<?php endif; ?>>
							<summary>
								<span class="num" aria-hidden="true"><?php echo esc_html( vgarco_pad( $i + 1 ) ); ?></span>
								<?php echo esc_html( $service['title'] ); ?>
								<span class="plus" aria-hidden="true">+</span>
							</summary>
							<div class="service-body"><?php echo $service['description']; ?></div>
						</details>
					<?php endforeach; ?>
				</div>
			</div>
		<?php else : ?>
			<p class="services-empty"><?php esc_html_e( 'Nội dung đang được cập nhật.', 'vgarco' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();

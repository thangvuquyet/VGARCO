<?php

defined( 'ABSPATH' ) || exit;

get_header();

$job = vgarco_job( (int) get_queried_object_id() );

$sections = array(
	array( __( 'Công việc chính', 'vgarco' ), $job['responsibilities'] ),
	array( __( 'Yêu cầu', 'vgarco' ), $job['requirements'] ),
	array( __( 'Quyền lợi', 'vgarco' ), $job['benefits'] ),
);

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Tuyển dụng', 'vgarco' ), vgarco_page_url( 'careers' ) ),
		array( $job['title'] ),
	)
);
?>

<article class="job-detail">
	<section class="wrap job-detail-hero">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Cơ hội nghề nghiệp', 'vgarco' ); ?></p>
			<h1><?php echo esc_html( $job['title'] ); ?></h1>
			<ul class="job-detail-meta">
				<?php foreach ( array( $job['location']['name'], $job['department']['name'], $job['type_label'] ) as $meta ) : ?>
					<?php if ( '' !== $meta ) : ?>
						<li><?php echo esc_html( $meta ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php if ( $job['open'] ) : ?>
			<a class="job-apply-primary" href="<?php echo esc_url( $job['apply_url'] ); ?>">
				<span><?php esc_html_e( 'Ứng tuyển ngay', 'vgarco' ); ?></span>
				<?php echo vgarco_icon( 'arrow-up-right' ); ?>
			</a>
		<?php else : ?>
			<p class="job-closed" role="status"><?php esc_html_e( 'Đã ngừng nhận hồ sơ', 'vgarco' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="wrap job-detail-layout">
		<div class="job-detail-content">
			<?php if ( '' !== $job['description'] ) : ?>
				<p class="job-detail-lead"><?php echo esc_html( $job['description'] ); ?></p>
			<?php elseif ( '' !== $job['summary'] ) : ?>
				<p class="job-detail-lead"><?php echo esc_html( $job['summary'] ); ?></p>
			<?php endif; ?>

			<?php foreach ( $sections as $section ) : ?>
				<?php if ( $section[1] ) : ?>
					<section>
						<h2><?php echo esc_html( $section[0] ); ?></h2>
						<ul>
							<?php foreach ( $section[1] as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<aside class="job-detail-aside" aria-label="<?php esc_attr_e( 'Tóm tắt vị trí', 'vgarco' ); ?>">
			<span><?php esc_html_e( 'Vị trí', 'vgarco' ); ?></span>
			<strong><?php echo esc_html( $job['title'] ); ?></strong>
			<dl>
				<?php if ( '' !== $job['location']['name'] ) : ?>
					<div><dt><?php esc_html_e( 'Địa điểm', 'vgarco' ); ?></dt><dd><?php echo esc_html( $job['location']['name'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( '' !== $job['department']['name'] ) : ?>
					<div><dt><?php esc_html_e( 'Phòng ban', 'vgarco' ); ?></dt><dd><?php echo esc_html( $job['department']['name'] ); ?></dd></div>
				<?php endif; ?>
				<div><dt><?php esc_html_e( 'Loại hình', 'vgarco' ); ?></dt><dd><?php echo esc_html( $job['type_label'] ); ?></dd></div>
				<?php if ( '' !== $job['deadline'] ) : ?>
					<div><dt><?php esc_html_e( 'Hạn nộp', 'vgarco' ); ?></dt><dd><time datetime="<?php echo esc_attr( substr( $job['deadline'], 0, 4 ) . '-' . substr( $job['deadline'], 4, 2 ) . '-' . substr( $job['deadline'], 6, 2 ) ); ?>"><?php echo esc_html( substr( $job['deadline'], 6, 2 ) . '/' . substr( $job['deadline'], 4, 2 ) . '/' . substr( $job['deadline'], 0, 4 ) ); ?></time></dd></div>
				<?php endif; ?>
			</dl>
		</aside>
	</section>
</article>

<?php
get_footer();

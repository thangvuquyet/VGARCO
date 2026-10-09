<?php

defined( 'ABSPATH' ) || exit;

get_header();

$detail  = vgarco_project_detail( (int) get_queried_object_id() );
$project = $detail['project'];
$type    = $project['types'][0] ?? null;
$next    = $detail['next'];
$content = trim( $detail['content'] );
$all_url = get_post_type_archive_link( 'du_an' ) ?: home_url( '/du-an/' );
$cover   = $project['hero_image'] ?: $project['card_image'];

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Dự án', 'vgarco' ), $all_url ),
		array( $project['title'] ),
	),
	'breadcrumb--detail'
);
?>

<article class="project-detail">
	<section class="project-detail-hero">
		<div class="project-detail-head wrap">
			<div>
				<?php if ( $type ) : ?>
					<a class="eyebrow" href="<?php echo esc_url( $type['url'] ); ?>"><?php echo esc_html( $type['name'] ); ?></a>
				<?php endif; ?>
				<h1><?php echo esc_html( $project['title'] ); ?></h1>
			</div>
			<?php if ( $detail['total'] > 1 ) : ?>
				<p class="project-detail-index">
					<span class="sr-only">
						<?php
						/* translators: 1: số thứ tự dự án, 2: tổng số dự án. */
						echo esc_html( sprintf( __( 'Dự án thứ %1$d trong %2$d dự án', 'vgarco' ), $detail['index'], $detail['total'] ) );
						?>
					</span>
					<span aria-hidden="true"><?php echo esc_html( vgarco_pad( $detail['index'] ) ); ?></span>
					<small aria-hidden="true">/ <?php echo esc_html( vgarco_pad( $detail['total'] ) ); ?></small>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( $cover > 0 ) : ?>
			<figure class="project-detail-cover">
				<?php
				echo vgarco_image(
					$cover,
					array(
						'size'     => 'vg-1600',
						'sizes'    => '100vw',
						'alt'      => $project['kind_label'] . ' — ' . $project['title'],
						'priority' => true,
					)
				);
				?>
				<figcaption>
					<span><?php echo esc_html( $project['kind_label'] ); ?></span>
					<?php if ( '' !== $project['location'] || $type ) : ?>
						<span><?php echo esc_html( '' !== $project['location'] ? $project['location'] : $type['name'] ); ?></span>
					<?php endif; ?>
				</figcaption>
			</figure>
		<?php endif; ?>
	</section>

	<?php if ( $detail['facts'] || '' !== $content || '' !== $project['scope'] ) : ?>
		<section class="project-detail-story wrap">
			<?php if ( $detail['facts'] ) : ?>
				<aside aria-labelledby="project-info-title">
					<p class="eyebrow" id="project-info-title"><?php esc_html_e( 'Thông tin dự án', 'vgarco' ); ?></p>
					<dl>
						<?php foreach ( $detail['facts'] as $fact ) : ?>
							<div>
								<dt><?php echo esc_html( $fact[0] ); ?></dt>
								<dd><?php echo esc_html( $fact[1] ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</aside>
			<?php endif; ?>

			<div class="project-detail-copy">
				<p class="project-kicker"><?php esc_html_e( 'Một công trình trong hồ sơ VGARCO', 'vgarco' ); ?></p>
				<h2><?php echo vgarco_heading( __( "Từ bối cảnh,\n<em>đến một giải pháp riêng.</em>", 'vgarco' ) ); ?></h2>
				<?php if ( '' !== $content ) : ?>
					<div class="entry-content"><?php echo $content; ?></div>
				<?php endif; ?>
				<?php if ( '' !== $project['scope'] ) : ?>
					<div class="project-scope">
						<span><?php esc_html_e( 'Phạm vi tham gia', 'vgarco' ); ?></span>
						<strong><?php echo esc_html( $project['scope'] ); ?></strong>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $detail['gallery'] ) : ?>
		<?php get_template_part( 'template-parts/project-gallery', null, array( 'gallery' => $detail['gallery'], 'title' => $project['title'] ) ); ?>
	<?php endif; ?>

	<?php if ( $next ) : ?>
		<section class="project-detail-next on-dark" aria-labelledby="project-next-title">
			<div class="wrap">
				<p class="eyebrow" id="project-next-title"><?php esc_html_e( 'Dự án tiếp theo', 'vgarco' ); ?></p>
				<a class="project-next-link" href="<?php echo esc_url( $next['url'] ); ?>">
					<span><?php echo esc_html( $next['title'] ); ?></span>
					<span class="project-next-arrow" aria-hidden="true"><?php echo vgarco_icon( 'arrow-up-right' ); ?></span>
				</a>
				<div class="project-next-meta">
					<span><?php echo esc_html( $next['types'][0]['name'] ?? '' ); ?></span>
					<span><?php echo esc_html( '' !== $next['location'] ? $next['location'] : 'VGARCO' ); ?></span>
				</div>
			</div>
		</section>
	<?php endif; ?>
</article>

<?php
get_footer();

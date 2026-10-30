<?php

defined( 'ABSPATH' ) || exit;

$term      = $args['term'] ?? null;
$catalogue = vgarco_catalogue( $term );
$story     = $term ? null : vgarco_story();
$all_url   = get_post_type_archive_link( 'du_an' ) ?: home_url( '/du-an/' );

$trail = array( array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ) );
if ( $term ) {
	$trail[] = array( __( 'Dự án', 'vgarco' ), $all_url );
	$trail[] = array( $catalogue['title'] );
} else {
	$trail[] = array( __( 'Dự án', 'vgarco' ) );
}

vgarco_breadcrumb( $trail );
?>

<section class="project-catalogue-head" aria-labelledby="catalogue-title">
	<div class="project-head-meta">
		<p class="eyebrow"><?php esc_html_e( 'Danh mục dự án', 'vgarco' ); ?></p>
		<?php if ( $catalogue['count'] > 0 ) : ?>
			<span>
				<?php
				echo esc_html( sprintf( _n( '%s dự án', '%s dự án', $catalogue['count'], 'vgarco' ), vgarco_pad( $catalogue['count'] ) ) );
				?>
			</span>
		<?php endif; ?>
	</div>
	<h1 id="catalogue-title"><?php echo esc_html( $catalogue['title'] ); ?></h1>
	<p><?php echo esc_html( $catalogue['note'] ); ?></p>
</section>

<section class="section wrap projects-section catalogue" id="du-an">
	<?php if ( $catalogue['projects'] ) : ?>
		<h2 class="sr-only"><?php esc_html_e( 'Danh mục dự án', 'vgarco' ); ?></h2>
		<div class="catalogue-grid<?php echo $term ? ' is-filtered' : ''; ?>">
			<?php foreach ( $catalogue['projects'] as $index => $project ) : ?>
				<?php get_template_part( 'template-parts/cards/project', null, array( 'project' => $project, 'arrow' => true, 'priority' => 0 === $index ) ); ?>
			<?php endforeach; ?>
		</div>
		<?php ?>
		<p class="sr-only" role="status">
			<?php
			echo esc_html(
				1 === $catalogue['count']
					? __( 'Đang hiển thị 1 dự án.', 'vgarco' )
					: sprintf( __( 'Đang hiển thị %d dự án.', 'vgarco' ), $catalogue['count'] )
			);
			?>
		</p>
	<?php else : ?>
		<div class="catalogue-empty">
			<p>
				<?php
				echo esc_html(
					$term
						? __( 'Chưa có dự án nào thuộc nhóm này.', 'vgarco' )
						: __( 'Danh mục dự án đang được cập nhật.', 'vgarco' )
				);
				?>
			</p>
			<?php if ( $term ) : ?>
				<a class="text-link" href="<?php echo esc_url( $all_url ); ?>"><?php esc_html_e( 'Xem tất cả dự án', 'vgarco' ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>

<?php if ( $story ) : ?>
	<?php
	$project   = $story['project'];
	$location  = $project['location'];
	$has_built = $story['built'] > 0;
	$eyebrow   = implode( ' / ', array_filter( array( __( 'Câu chuyện một công trình', 'vgarco' ), $project['years'] ) ) );
	?>
	<section class="project-story on-dark" id="cau-chuyen" aria-labelledby="story-title" data-story>
		<div class="story-text">
			<p class="eyebrow light"><?php echo esc_html( $eyebrow ); ?></p>
			<h2 id="story-title"><?php echo vgarco_heading( $story['title'] ); ?></h2>
			<?php if ( '' !== $story['text'] ) : ?>
				<p><?php echo esc_html( $story['text'] ); ?></p>
			<?php endif; ?>
			<a class="story-link" href="<?php echo esc_url( $project['url'] ); ?>">
				<?php esc_html_e( 'Khám phá câu chuyện', 'vgarco' ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?>
			</a>

			<?php if ( $has_built ) : ?>
				<div class="story-switch" role="group" aria-label="<?php esc_attr_e( 'Xem phối cảnh thiết kế hoặc công trình thực tế', 'vgarco' ); ?>">
					<button type="button" class="is-active" aria-pressed="true" data-story-show="design" data-caption="<?php echo esc_attr( vgarco_kind_label( 'thiet_ke' ) ); ?>">01 — <?php esc_html_e( 'Phối cảnh', 'vgarco' ); ?></button>
					<button type="button" aria-pressed="false" data-story-show="built" data-caption="<?php echo esc_attr( vgarco_kind_label( 'cong_trinh' ) ); ?>">02 — <?php esc_html_e( 'Công trình', 'vgarco' ); ?></button>
				</div>
			<?php endif; ?>
		</div>

		<figure class="story-media">
			<?php
			$views = array( 'design' => $story['design'] );
			if ( $has_built ) {
				$views['built'] = $story['built'];
			}
			foreach ( $views as $view => $image_id ) {
				echo vgarco_image(
					$image_id,
					array(
						'size'  => 'vg-1600',
						'sizes' => '(min-width: 1001px) 55vw, 100vw',
						'alt'   => vgarco_kind_label( 'design' === $view ? 'thiet_ke' : 'cong_trinh' ) . ' — ' . $project['title'],
						'class' => 'story-image' . ( 'design' === $view ? ' is-active' : '' ),
						'attr'  => 'design' === $view ? array() : array( 'aria-hidden' => 'true' ),
					)
				);
			}
			?>
			<figcaption>
				<span data-story-caption><?php echo esc_html( vgarco_kind_label( 'thiet_ke' ) ); ?></span>
				<?php if ( '' !== $location ) : ?>
					<span><?php echo esc_html( $location ); ?></span>
				<?php endif; ?>
			</figcaption>
		</figure>
	</section>
<?php endif; ?>

<?php

defined( 'ABSPATH' ) || exit;

global $wp_query;

$term       = $args['term'] ?? null;
$ids        = array_map( 'intval', wp_list_pluck( (array) ( $wp_query->posts ?? array() ), 'ID' ) );
$items      = array_map( 'vgarco_news_item', $ids );
$categories = vgarco_news_categories( $term ? (int) $term->term_id : 0 );
$all_url    = get_post_type_archive_link( 'tin_tuc' ) ?: home_url( '/tin-tuc/' );
$paged      = max( 1, (int) get_query_var( 'paged' ) );
$pages      = (int) ( $wp_query->max_num_pages ?? 1 );

$trail = array( array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ) );
if ( $term ) {
	$trail[] = array( __( 'Tin tức', 'vgarco' ), $all_url );
	$trail[] = array( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) );
} else {
	$trail[] = array( __( 'Tin tức', 'vgarco' ) );
}
vgarco_breadcrumb( $trail );
?>

<section class="wrap news-section">
	<div class="news-page-head">
		<h1 class="eyebrow"><?php esc_html_e( 'Tin tức & hoạt động', 'vgarco' ); ?></h1>
		<nav class="news-filters" aria-label="<?php esc_attr_e( 'Chọn chuyên mục tin tức', 'vgarco' ); ?>">
			<ul>
				<li><a href="<?php echo esc_url( $all_url ); ?>"<?php echo $term ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'Tất cả', 'vgarco' ); ?></a></li>
				<?php foreach ( $categories as $category ) : ?>
					<li><a href="<?php echo esc_url( $category['url'] ); ?>"<?php echo $category['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $category['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	</div>

	<?php if ( $items ) : ?>
		<div class="news-list">
			<?php foreach ( $items as $index => $item ) : ?>
				<article class="news-row">
					<a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['image_id'] > 0 ? '' : ' class="no-thumb"'; ?>>
						<?php if ( $item['image_id'] > 0 ) : ?>
							<figure>
								<?php
								echo vgarco_image(
									$item['image_id'],
									array(
										'size'  => 'vg-480',
										'sizes'    => '(min-width: 801px) 150px, (min-width: 601px) 96px, 100vw',
										'alt'      => '',
										'priority' => 0 === $index,
									)
								);
								?>
							</figure>
						<?php endif; ?>
						<div class="news-row-copy">
							<?php if ( '' !== $item['category'] ) : ?>
								<span class="eyebrow"><?php echo esc_html( $item['category'] ); ?></span>
							<?php endif; ?>
							<h2><?php echo esc_html( $item['title'] ); ?></h2>
							<?php if ( '' !== $item['excerpt'] ) : ?>
								<p><?php echo esc_html( $item['excerpt'] ); ?></p>
							<?php endif; ?>
						</div>
						<div class="news-row-meta">
							<time datetime="<?php echo esc_attr( $item['iso'] ); ?>"><?php echo esc_html( $item['date'] ); ?></time>
							<?php if ( '' !== $item['topic'] ) : ?>
								<small><?php echo esc_html( $item['topic'] ); ?></small>
							<?php endif; ?>
							<span class="row-arrow" aria-hidden="true"><?php echo vgarco_icon( 'arrow-up-right' ); ?></span>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		</div>

		<?php
		echo vgarco_pagination(
			$paged,
			$pages,
			static fn( int $page ): string => (string) get_pagenum_link( $page )
		);
		?>
	<?php else : ?>
		<p class="news-empty"><?php esc_html_e( 'Chưa có bài viết nào trong mục này.', 'vgarco' ); ?></p>
	<?php endif; ?>
</section>

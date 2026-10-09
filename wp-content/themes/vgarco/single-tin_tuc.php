<?php

defined( 'ABSPATH' ) || exit;

get_header();

$article = vgarco_news_article( (int) get_queried_object_id() );
$item    = $article['item'];
$content = trim( $article['content'] );
$url     = $item['url'];

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Tin tức', 'vgarco' ), get_post_type_archive_link( 'tin_tuc' ) ?: home_url( '/tin-tuc/' ) ),
		array( $item['title'] ),
	)
);
?>

<article class="news-article">
	<header class="wrap news-article-head">
		<div>
			<?php if ( '' !== $item['category'] ) : ?>
				<p class="eyebrow"><?php echo esc_html( $item['category'] ); ?></p>
			<?php endif; ?>
			<h1><?php echo esc_html( $item['title'] ); ?></h1>
		</div>
		<p class="news-article-meta">
			<time datetime="<?php echo esc_attr( $item['iso'] ); ?>"><?php echo esc_html( $item['date'] ); ?></time>
			<?php if ( '' !== $item['topic'] ) : ?>
				<span><?php echo esc_html( $item['topic'] ); ?></span>
			<?php endif; ?>
		</p>
	</header>

	<?php if ( $item['image_id'] > 0 ) : ?>
		<figure class="news-article-cover">
			<?php
			echo vgarco_image(
				$item['image_id'],
				array(
					'size'     => 'vg-1600',
					'sizes'    => '100vw',
					'alt'      => '' !== $article['caption'] ? $article['caption'] : $item['title'],
					'priority' => true,
				)
			);
			?>
			<?php if ( '' !== $article['caption'] ) : ?>
				<figcaption><?php echo esc_html( $article['caption'] ); ?></figcaption>
			<?php endif; ?>
		</figure>
	<?php endif; ?>

	<div class="wrap news-article-layout">
		<aside class="article-share" aria-label="<?php esc_attr_e( 'Chia sẻ bài viết', 'vgarco' ); ?>">
			<span><?php esc_html_e( 'Chia sẻ', 'vgarco' ); ?></span>
			<div>
				<button type="button" data-copy-link="<?php echo esc_url( $url ); ?>" hidden
					data-label="<?php esc_attr_e( 'Sao chép liên kết', 'vgarco' ); ?>"
					data-done="<?php esc_attr_e( 'Đã sao chép liên kết', 'vgarco' ); ?>"
					aria-label="<?php esc_attr_e( 'Sao chép liên kết', 'vgarco' ); ?>">
					<?php echo vgarco_icon( 'arrow-up-right' ); ?>
				</button>
				<a class="share-facebook" href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ); ?>" target="_blank" rel="noopener noreferrer"
					aria-label="<?php esc_attr_e( 'Chia sẻ lên Facebook (mở thẻ mới)', 'vgarco' ); ?>">f</a>
			</div>
			<p class="sr-only" role="status" aria-live="polite" data-copy-status></p>
		</aside>

		<div class="news-article-body entry-content">
			<?php if ( '' !== $article['lead'] ) : ?>
				<p class="article-lead"><?php echo esc_html( $article['lead'] ); ?></p>
			<?php endif; ?>
			<?php echo $content; ?>
		</div>
	</div>

	<?php if ( $article['prev'] || $article['next'] ) : ?>
		<nav class="news-article-nav" aria-label="<?php esc_attr_e( 'Bài viết khác', 'vgarco' ); ?>">
			<?php if ( $article['prev'] ) : ?>
				<a class="nav-prev" href="<?php echo esc_url( $article['prev']['url'] ); ?>" rel="prev">
					<small><?php esc_html_e( 'Bài trước', 'vgarco' ); ?></small>
					<strong><?php echo esc_html( $article['prev']['title'] ); ?></strong>
					<span aria-hidden="true"><?php echo vgarco_icon( 'arrow-left' ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $article['next'] ) : ?>
				<a class="nav-next" href="<?php echo esc_url( $article['next']['url'] ); ?>" rel="next">
					<small><?php esc_html_e( 'Bài tiếp theo', 'vgarco' ); ?></small>
					<strong><?php echo esc_html( $article['next']['title'] ); ?></strong>
					<span aria-hidden="true"><?php echo vgarco_icon( 'arrow-right' ); ?></span>
				</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</article>

<?php
get_footer();

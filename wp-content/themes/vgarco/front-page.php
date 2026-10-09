<?php

defined( 'ABSPATH' ) || exit;

get_header();

$id        = (int) get_queried_object_id();
$t         = static fn( string $name, string $default ): string => vgarco_field_text( $name, $default, $id );
$slides    = vgarco_hero_slides();
$showcase  = vgarco_showcase_projects( 3 );
$services  = vgarco_home_services( 3 );
$news      = vgarco_latest_news( 3 );
$facts     = (array) vgarco_option( 'chi_so_trang_chu', array() );
$partners  = array_values( array_filter( (array) vgarco_option( 'doi_tac', array() ), static fn( $p ) => ! empty( $p['ten'] ) ) );
$projects  = get_post_type_archive_link( 'du_an' ) ?: home_url( '/du-an/' );
$news_url  = get_post_type_archive_link( 'tin_tuc' ) ?: home_url( '/tin-tuc/' );
$band = vgarco_split_lines( vgarco_field_text( 'dai_chu', '', $id ) );
if ( count( $band ) < 2 ) {
	$band = array(
		__( 'Tư duy kiến trúc', 'vgarco' ),
		__( 'Năng lực thực thi', 'vgarco' ),
		__( 'Dấu ấn VGARCO', 'vgarco' ),
	);
}
$methods = array_slice(
	array_map(
		static fn( array $row ): array => array( $row['tieu_de'], $row['mo_ta'] ),
		vgarco_field_rows(
			'nguyen_tac',
			array( 'tieu_de', 'mo_ta' ),
			array(
				array( 'tieu_de' => __( 'Hiểu đúng bối cảnh', 'vgarco' ), 'mo_ta' => __( 'Mọi phương án bắt đầu từ nhu cầu sử dụng, điều kiện địa điểm và nguồn lực thực tế.', 'vgarco' ) ),
				array( 'tieu_de' => __( 'Phối hợp xuyên suốt', 'vgarco' ), 'mo_ta' => __( 'Kiến trúc, kết cấu và kỹ thuật được kết nối trong cùng một quy trình làm việc.', 'vgarco' ) ),
				array( 'tieu_de' => __( 'Theo sát thực tế', 'vgarco' ), 'mo_ta' => __( 'Giải pháp được kiểm soát từ ý tưởng đến hồ sơ và giai đoạn triển khai.', 'vgarco' ) ),
			),
			$id
		)
	),
	0,
	3
);
?>

<section class="hero" aria-label="<?php esc_attr_e( 'Dự án nổi bật', 'vgarco' ); ?>">
	<div class="hero-content">
		<p class="eyebrow"><?php echo esc_html( $t( 'hero_nhan', __( 'Kiến trúc bắt đầu từ một tầm nhìn', 'vgarco' ) ) ); ?></p>
		<h1><?php echo vgarco_field_heading( 'hero_tieu_de', __( "Mỗi công trình,\nmột <em>dấu ấn.</em>", 'vgarco' ), $id ); ?></h1>
		<p class="hero-description"><?php echo vgarco_field_lines( 'hero_mo_ta', __( "Chúng tôi kết nối ý tưởng kiến trúc với\nnăng lực kỹ thuật để tạo nên những\nkhông gian có giá trị dài lâu.", 'vgarco' ), $id ); ?></p>
		<a class="hero-explore" href="<?php echo esc_url( $projects ); ?>">
			<span class="round-arrow" aria-hidden="true"><?php echo vgarco_icon( 'arrow-up-right' ); ?></span>
			<span><?php echo esc_html( $t( 'hero_nut', __( 'Khám phá công trình', 'vgarco' ) ) ); ?><small><?php echo esc_html( $t( 'hero_nut_nhan', __( 'Dấu ấn VGARCO', 'vgarco' ) ) ); ?></small></span>
		</a>
		<p class="hero-footnote"><?php echo esc_html( $t( 'hero_chan_1', __( 'Hà Nội, Việt Nam', 'vgarco' ) ) ); ?> <span><?php echo esc_html( $t( 'hero_chan_2', __( 'Hoạt động từ 2013', 'vgarco' ) ) ); ?></span></p>
	</div>

	<a class="hero-process-rail" href="<?php echo esc_url( $showcase ? '#du-an' : $projects ); ?>" aria-label="<?php esc_attr_e( 'Từ bản vẽ đến công trình', 'vgarco' ); ?>">
		<span class="process-line" aria-hidden="true"></span>
		<strong><?php echo esc_html( $t( 'hero_rail_1', __( 'Từ bản vẽ', 'vgarco' ) ) ); ?></strong>
		<span class="process-arrow" aria-hidden="true">&rarr;</span>
		<strong><?php echo esc_html( $t( 'hero_rail_2', __( 'Đến công trình', 'vgarco' ) ) ); ?></strong>
	</a>

	<?php if ( $slides ) : ?>
		<div class="hero-frame" data-hero>
			<div class="showcase-media">
				<div class="showcase-images">
					<?php foreach ( $slides as $i => $slide ) : ?>
						<?php
						echo vgarco_image(
							$slide['image_id'],
							array(
								'size'     => 'vg-1600',
								'sizes'    => '(min-width: 1001px) 55vw, 100vw',
								'alt'      => $slide['alt'],
								'class'    => 'showcase-image' . ( 0 === $i ? ' is-active' : '' ),
								'priority' => 0 === $i,
							)
						);
						?>
					<?php endforeach; ?>
				</div>
				<div class="showcase-grid" aria-hidden="true"></div>
				<div class="showcase-watermark" aria-hidden="true">VG</div>

				<?php if ( count( $slides ) > 1 ) : ?>
					<?php ?>
					<div class="showcase-dots on-dark" data-hero-dots role="group" aria-label="<?php esc_attr_e( 'Chọn ảnh trình chiếu', 'vgarco' ); ?>" hidden>
						<?php foreach ( $slides as $i => $slide ) : ?>
							<button type="button" class="showcase-dot" data-hero-dot<?php echo 0 === $i ? ' aria-current="true"' : ''; ?>
								aria-label="<?php /* translators: 1: số thứ tự ảnh, 2: tổng số ảnh, 3: tên dự án. */ echo esc_attr( sprintf( __( 'Ảnh %1$d / %2$d: %3$s', 'vgarco' ), $i + 1, count( $slides ), $slide['title'] ) ); ?>"></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php foreach ( $slides as $i => $slide ) : ?>
					<?php if ( '' !== $slide['title'] ) : ?>
						<div class="showcase-caption<?php echo 0 === $i ? ' is-active' : ''; ?>">
							<?php if ( '' !== $slide['category'] ) : ?>
								<span class="eyebrow"><?php echo esc_html( $slide['category'] ); ?></span>
							<?php endif; ?>
							<a class="hero-project-link" href="<?php echo esc_url( $slide['url'] ); ?>">
								<span><?php echo esc_html( $slide['title'] ); ?></span>
								<?php echo vgarco_icon( 'arrow-up-right' ); ?>
							</a>
							<small><?php echo esc_html( $slide['kind_label'] ); ?></small>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</section>

<div class="marquee" data-marquee>
	<div class="marquee-track">
		<?php foreach ( array( false, true ) as $duplicate ) : ?>
			<div class="marquee-group"<?php echo $duplicate ? ' aria-hidden="true"' : ''; ?>>
				<?php foreach ( $band as $phrase ) : ?>
					<span><?php echo esc_html( $phrase ); ?></span><?php echo vgarco_icon( 'asterisk' ); ?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<button type="button" class="motion-toggle" aria-pressed="false"
		aria-label="<?php esc_attr_e( 'Tạm dừng chuyển động', 'vgarco' ); ?>"
		data-label-pause="<?php esc_attr_e( 'Tạm dừng chuyển động', 'vgarco' ); ?>"
		data-label-play="<?php esc_attr_e( 'Tiếp tục chuyển động', 'vgarco' ); ?>">
		<?php echo vgarco_icon( 'pause', 'icon-pause' ); ?>
		<?php echo vgarco_icon( 'play', 'icon-play' ); ?>
	</button>
</div>

<section class="section wrap home-about" id="gioi-thieu">
	<p class="section-label"><?php echo esc_html( $t( 'gt_nhan', __( 'Về VGARCO', 'vgarco' ) ) ); ?></p>
	<div class="home-about-copy">
		<h2><?php echo vgarco_field_heading( 'gt_tieu_de', __( "Thiết kế tốt bắt đầu\ntừ việc <em>thấu hiểu.</em>", 'vgarco' ), $id ); ?></h2>
		<p><?php echo esc_html( $t( 'gt_doan_1', __( 'Từ năm 2013, VGARCO kết nối kiến trúc và kỹ thuật trong các dự án tại Việt Nam, Lào và CHLB Đức.', 'vgarco' ) ) ); ?></p>
		<p><?php echo esc_html( $t( 'gt_doan_2', __( 'Chúng tôi theo đuổi những giải pháp rõ ràng, khả thi và có giá trị dài lâu — từ ý tưởng đầu tiên đến khi công trình được đưa vào sử dụng.', 'vgarco' ) ) ); ?></p>
		<a class="text-link" href="<?php echo esc_url( vgarco_page_url( 'about' ) ); ?>"><?php echo esc_html( $t( 'gt_lien_ket', __( 'Về công ty', 'vgarco' ) ) ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?></a>
	</div>
	<?php if ( $facts ) : ?>
		<div class="home-facts">
			<?php foreach ( $facts as $fact ) : ?>
				<p>
					<strong><?php echo esc_html( $fact['so'] ?? '' ); ?><?php echo ! empty( $fact['hau_to'] ) ? '<span>' . esc_html( $fact['hau_to'] ) . '</span>' : ''; ?></strong>
					<span><?php echo esc_html( $fact['nhan'] ?? '' ); ?></span>
				</p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<div class="home-methods">
		<?php foreach ( $methods as $n => $method ) : ?>
			<article>
				<span><?php echo esc_html( sprintf( '%02d', $n + 1 ) ); ?></span>
				<h3><?php echo esc_html( $method[0] ); ?></h3>
				<p><?php echo esc_html( $method[1] ); ?></p>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php if ( $showcase ) : ?>
	<section class="section wrap projects-section" id="du-an">
		<div class="section-heading">
			<div>
				<p class="section-label"><?php echo esc_html( $t( 'da_nhan', __( 'Dự án tiêu biểu', 'vgarco' ) ) ); ?></p>
				<h2><?php echo vgarco_field_heading( 'da_tieu_de', __( "Những công trình\nmang <em>dấu ấn riêng.</em>", 'vgarco' ), $id ); ?></h2>
			</div>
			<p class="section-description"><?php echo vgarco_field_lines( 'da_mo_ta', __( "Mỗi dự án là một lời giải riêng\ncho bối cảnh, công năng và con người.", 'vgarco' ), $id ); ?></p>
		</div>
		<div class="project-grid">
			<?php foreach ( $showcase as $project ) : ?>
				<?php get_template_part( 'template-parts/cards/project', null, array( 'project' => $project ) ); ?>
			<?php endforeach; ?>
		</div>
		<div class="projects-archive">
			<a class="text-link" href="<?php echo esc_url( $projects ); ?>"><?php echo esc_html( $t( 'da_lien_ket', __( 'Xem toàn bộ dự án', 'vgarco' ) ) ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?></a>
		</div>
	</section>
<?php endif; ?>

<?php if ( $services ) : ?>
	<section class="home-services">
		<div class="wrap">
			<div>
				<p class="section-label"><?php echo esc_html( $t( 'cm_nhan', __( 'Chuyên môn', 'vgarco' ) ) ); ?></p>
				<h2><?php echo vgarco_field_heading( 'cm_tieu_de', __( "Tư duy tổng thể.\n<em>Chăm chút từng chi tiết.</em>", 'vgarco' ), $id ); ?></h2>
				<p class="home-services-copy"><?php echo esc_html( $t( 'cm_mo_ta', __( 'Từ khảo sát ban đầu đến hồ sơ kỹ thuật và công trường, mỗi bộ môn được điều phối trong cùng một logic thiết kế.', 'vgarco' ) ) ); ?></p>
				<a class="text-link" href="<?php echo esc_url( vgarco_page_url( 'services' ) ); ?>"><?php echo esc_html( $t( 'cm_lien_ket', __( 'Khám phá các lĩnh vực', 'vgarco' ) ) ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?></a>
			</div>
			<div class="service-preview-list">
				<?php foreach ( $services as $n => $service ) : ?>
					<a href="<?php echo esc_url( $service['url'] ); ?>">
						<span class="service-num"><?php echo esc_html( sprintf( '%02d', $n + 1 ) ); ?></span>
						<?php echo esc_html( $service['title'] ); ?>
						<?php if ( '' !== $service['note'] ) : ?>
							<span class="service-note"><?php echo esc_html( $service['note'] ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( $partners ) : ?>
	<section class="partners-strip" aria-labelledby="partners-title" data-marquee>
		<div class="wrap">
			<div class="partners-head">
				<p class="section-label" id="partners-title"><?php echo esc_html( $t( 'dt_nhan', __( 'Khách hàng & đối tác', 'vgarco' ) ) ); ?></p>
				<p class="partners-note"><?php echo esc_html( $t( 'dt_mo_ta', __( 'Những đơn vị đã cùng VGARCO phối hợp trong quá trình tư vấn, thiết kế và triển khai dự án.', 'vgarco' ) ) ); ?></p>
			</div>
		</div>
		<div class="partner-marquee" role="group" aria-label="<?php esc_attr_e( 'Danh sách khách hàng và đối tác tiêu biểu', 'vgarco' ); ?>">
			<div class="partner-track">
				<?php foreach ( array( false, true ) as $duplicate ) : ?>
					<div class="partner-group" style="--n: <?php echo (int) count( $partners ); ?>"<?php echo $duplicate ? ' aria-hidden="true"' : ''; ?>>
						<?php foreach ( $partners as $partner ) : ?>
							<div class="partner-logo">
								<span class="partner-monogram" aria-hidden="true"><?php echo esc_html( $partner['viet_tat'] ?? '' ); ?></span>
								<strong><?php echo esc_html( $partner['ten'] ); ?></strong>
								<?php if ( ! empty( $partner['mo_ta'] ) ) : ?>
									<small><?php echo esc_html( $partner['mo_ta'] ); ?></small>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( $news ) : ?>
	<section class="section wrap home-news" id="tin-tuc-noi-bat">
		<div class="home-news-head">
			<div>
				<p class="section-label"><?php echo esc_html( $t( 'tt_nhan', __( 'Tin tức & hoạt động', 'vgarco' ) ) ); ?></p>
				<h2><?php echo vgarco_field_heading( 'tt_tieu_de', __( 'Góc nhìn từ <em>VGARCO.</em>', 'vgarco' ), $id ); ?></h2>
			</div>
			<a class="text-link" href="<?php echo esc_url( $news_url ); ?>"><?php echo esc_html( $t( 'tt_lien_ket', __( 'Đến trang tin tức', 'vgarco' ) ) ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?></a>
		</div>
		<div class="home-news-grid">
			<?php foreach ( $news as $item ) : ?>
				<a class="home-news-card" href="<?php echo esc_url( $item['url'] ); ?>">
					<?php
					echo vgarco_image(
						$item['image_id'],
						array(
							'size'  => 'vg-800',
							'sizes' => '(min-width: 701px) 30vw, 100vw',
							'alt'   => '',
						)
					);
					?>
					<?php if ( '' !== $item['label'] ) : ?>
						<span class="eyebrow"><?php echo esc_html( $item['label'] ); ?></span>
					<?php endif; ?>
					<h3><?php echo esc_html( $item['title'] ); ?></h3>
					<p><?php echo esc_html( $item['excerpt'] ); ?></p>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<section class="page-cta on-dark">
	<div class="wrap">
		<div class="page-cta-copy">
			<p class="section-label"><?php echo esc_html( $t( 'cta_nhan', __( 'Bắt đầu một dự án', 'vgarco' ) ) ); ?></p>
			<h2><?php echo vgarco_field_heading( 'cta_tieu_de', __( "Cùng đặt nền móng\n<em>cho điều tiếp theo.</em>", 'vgarco' ), $id ); ?></h2>
			<p class="cta-support"><?php echo esc_html( $t( 'cta_mo_ta', __( 'Chia sẻ bối cảnh, nhu cầu và mục tiêu của bạn. VGARCO sẽ cùng trao đổi để xác định bước khởi đầu phù hợp.', 'vgarco' ) ) ); ?></p>
			<a class="text-link" href="<?php echo esc_url( vgarco_page_url( 'contact' ) ); ?>"><?php echo esc_html( $t( 'cta_lien_ket', __( 'Trao đổi với chúng tôi', 'vgarco' ) ) ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?></a>
		</div>
		<a class="cta-circle" href="<?php echo esc_url( vgarco_page_url( 'contact' ) ); ?>" aria-label="<?php esc_attr_e( 'Liên hệ VGARCO', 'vgarco' ); ?>">
			<?php echo vgarco_icon( 'arrow-up-right' ); ?>
		</a>
	</div>
</section>

<?php
get_footer();

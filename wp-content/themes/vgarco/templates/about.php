<?php
/**
 * Template Name: Về công ty
 *
 * Trang Về công ty (UC-02). Chữ mặc định lấy từ hồ sơ năng lực (chuỗi gettext); biên tập viên ghi đè bằng trường ACF của trang.
 * Ảnh bìa, khối "Sự ghi nhận" không có mặc định: chưa nhập thì ẩn (UC-02 2c).
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();

$id = (int) get_queried_object_id();
$t  = static fn( string $name, string $default ): string => vgarco_field_text( $name, $default, $id );

$cover        = vgarco_field_image( 'bia_anh', $id );
$facts        = (array) vgarco_option( 'chi_so_gioi_thieu', array() );
$milestones   = vgarco_field_rows(
	'hanh_trinh_moc',
	array( 'nam', 'dong_phu', 'tieu_de', 'mo_ta' ),
	array(
		array(
			'nam'      => '2013',
			'dong_phu' => '19.08.2013',
			'tieu_de'  => __( 'Khởi đầu với 3SEVEN', 'vgarco' ),
			'mo_ta'    => __( 'Thành lập Công ty TNHH Tư vấn Xây dựng 3SEVEN, đặt nền tảng cho hành trình hôm nay.', 'vgarco' ),
		),
		array(
			'nam'      => '2019–20',
			'dong_phu' => __( 'Viêng Chăn, Lào', 'vgarco' ),
			'tieu_de'  => __( 'Dấu ấn tại Lào', 'vgarco' ),
			'mo_ta'    => __( 'Tham gia thiết kế cải tạo Bảo tàng Lịch sử Quân đội nhân dân Lào, giai đoạn 1 và 2.', 'vgarco' ),
		),
		array(
			'nam'      => '2023',
			'dong_phu' => '05.12.2023',
			'tieu_de'  => __( 'Mang tên VGARCO', 'vgarco' ),
			'mo_ta'    => __( 'Công ty đổi tên thành Công ty TNHH Tư vấn Xây dựng VGARCO.', 'vgarco' ),
		),
		array(
			'nam'      => '2025–26',
			'dong_phu' => __( 'Hòa Lạc, Hà Nội', 'vgarco' ),
			'tieu_de'  => __( 'Tiếp nối hành trình', 'vgarco' ),
			'mo_ta'    => __( 'Tham gia thiết kế Học viện Kỹ thuật và Công nghệ An ninh tại Hòa Lạc.', 'vgarco' ),
		),
	),
	$id
);
$principles   = vgarco_field_rows(
	'cach_lam_muc',
	array( 'tieu_de', 'mo_ta' ),
	array(
		array(
			'tieu_de' => __( 'Thấu hiểu bối cảnh', 'vgarco' ),
			'mo_ta'   => __( 'Bắt đầu từ khu đất, nhu cầu sử dụng và kỳ vọng của chủ đầu tư để định hình hướng thiết kế.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Kết nối chuyên môn', 'vgarco' ),
			'mo_ta'   => __( 'Phối hợp kiến trúc, kết cấu và các bộ môn kỹ thuật trong một giải pháp thống nhất.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Theo sát triển khai', 'vgarco' ),
			'mo_ta'   => __( 'Kết nối thiết kế với thực tiễn xây dựng thông qua năng lực tư vấn, giám sát và quản lý dự án.', 'vgarco' ),
		),
	),
	$id
);
$countries    = vgarco_split_lines( $t( 'pham_vi_quoc_gia', implode( "\n", array( __( 'Việt Nam', 'vgarco' ), __( 'Lào', 'vgarco' ), __( 'CHLB Đức', 'vgarco' ) ) ) ) );
$approach_img = vgarco_field_image( 'cach_lam_anh', $id );

// Sự ghi nhận: hiện khi có ảnh hoặc tiêu đề. Liên kết chỉ hiện khi bài viết đã xuất bản.
$award_image = vgarco_field_image( 'ghi_nhan_anh', $id );
$award_title = vgarco_field_text( 'ghi_nhan_tieu_de', '', $id );
$award_post  = function_exists( 'get_field' ) ? (int) get_field( 'ghi_nhan_bai_viet', $id ) : 0;
$award_url   = ( $award_post > 0 && 'publish' === get_post_status( $award_post ) ) ? (string) get_permalink( $award_post ) : '';

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Về công ty', 'vgarco' ) ),
	)
);
?>

<section class="wrap about-masthead">
	<p class="eyebrow"><?php echo esc_html( $t( 'hero_nhan', __( 'Về công ty / Est. 2013', 'vgarco' ) ) ); ?></p>
	<h1><?php echo vgarco_field_heading( 'hero_tieu_de', __( "Từ những ý tưởng.\n<em>Đến giá trị dài lâu.</em>", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã lọc trong vgarco_heading(). ?></h1>
	<div class="masthead-bottom">
		<p><?php echo esc_html( $t( 'hero_mo_ta_1', __( 'VGARCO — Tư vấn thiết kế & xây dựng', 'vgarco' ) ) ); ?></p>
		<p><?php echo esc_html( $t( 'hero_mo_ta_2', __( 'Chúng tôi kết nối tư duy kiến trúc với nền tảng kỹ thuật, để mỗi công trình tìm thấy lời giải của riêng mình.', 'vgarco' ) ) ); ?></p>
		<a href="#cau-chuyen-cong-ty" class="about-scroll" aria-label="<?php esc_attr_e( 'Khám phá câu chuyện VGARCO', 'vgarco' ); ?>"><?php echo vgarco_icon( 'arrow-down' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</div>
</section>

<?php if ( $cover > 0 ) : ?>
	<figure class="about-cover">
		<?php
		echo vgarco_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup ảnh của WordPress.
			$cover,
			array(
				'size'     => 'vg-2560',
				'sizes'    => '100vw',
				'alt'      => $t( 'bia_chu_thich_trai', '' ),
				'priority' => true,
			)
		);
		?>
		<div class="cover-year">
			<strong><?php echo esc_html( $t( 'bia_nam', '2013' ) ); ?></strong>
			<span><?php echo esc_html( $t( 'bia_nam_nhan', __( 'Năm khởi đầu', 'vgarco' ) ) ); ?></span>
		</div>
		<?php if ( '' !== $t( 'bia_chu_thich_trai', '' ) || '' !== $t( 'bia_chu_thich_phai', '' ) ) : ?>
			<figcaption>
				<span><?php echo esc_html( $t( 'bia_chu_thich_trai', '' ) ); ?></span>
				<span><?php echo esc_html( $t( 'bia_chu_thich_phai', '' ) ); ?></span>
			</figcaption>
		<?php endif; ?>
	</figure>
<?php endif; ?>

<section class="wrap about-story" id="cau-chuyen-cong-ty">
	<div>
		<p class="eyebrow"><?php esc_html_e( 'Chúng tôi là ai', 'vgarco' ); ?></p>
		<p class="margin-note"><?php echo vgarco_field_lines( 'cau_chuyen_ghi_chu', __( "Kiến trúc. Kỹ thuật.\nMột tư duy xuyên suốt.", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã thoát trong vgarco_lines(). ?></p>
	</div>
	<div>
		<h2><?php echo vgarco_field_heading( 'cau_chuyen_tieu_de', __( "Mỗi bối cảnh khác nhau.\n<em>Cùng một sự tận tâm.</em>", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<div class="story-columns">
			<p><?php echo esc_html( $t( 'cau_chuyen_doan_1', __( 'Công ty TNHH Tư vấn Xây dựng VGARCO được thành lập ngày 19/08/2013 với tên gọi 3SEVEN. Từ ngày 05/12/2023, công ty chính thức mang tên VGARCO.', 'vgarco' ) ) ); ?></p>
			<p><?php echo esc_html( $t( 'cau_chuyen_doan_2', __( 'Hoạt động trong tư vấn thiết kế, giám sát và quản lý dự án, VGARCO kết nối các chuyên môn kiến trúc, kết cấu và kỹ thuật để phát triển giải pháp phù hợp với từng công trình.', 'vgarco' ) ) ); ?></p>
		</div>
		<p class="story-closing"><?php echo esc_html( $t( 'cau_chuyen_ket', __( 'Từ công trình giáo dục, văn hóa đến hạ tầng và nhà ở, kinh nghiệm của chúng tôi được bồi đắp qua các dự án tại Việt Nam, Lào và Đức.', 'vgarco' ) ) ); ?></p>
	</div>
</section>

<?php if ( $facts ) : ?>
	<section class="wrap about-metrics" aria-label="<?php esc_attr_e( 'VGARCO qua những con số', 'vgarco' ); ?>">
		<?php foreach ( $facts as $fact ) : ?>
			<div>
				<strong><?php echo esc_html( $fact['so'] ); ?><?php echo '' !== ( $fact['hau_to'] ?? '' ) ? '<span>' . esc_html( $fact['hau_to'] ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				<p><?php echo esc_html( $fact['nhan'] ); ?></p>
			</div>
		<?php endforeach; ?>
		<p class="metrics-note"><?php echo esc_html( $t( 'chi_so_ghi_chu', __( 'Các con số phản ánh hành trình và phạm vi hoạt động trong hồ sơ VGARCO.', 'vgarco' ) ) ); ?></p>
	</section>
<?php endif; ?>

<section class="about-timeline on-dark" aria-labelledby="timeline-title">
	<div class="wrap">
		<div class="timeline-head">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Hành trình', 'vgarco' ); ?></p>
				<h2 id="timeline-title"><?php echo vgarco_field_heading( 'hanh_trinh_tieu_de', __( "Một nền tảng.\n<em>Nhiều dấu mốc.</em>", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
			</div>
			<p><?php echo esc_html( $t( 'hanh_trinh_mo_ta', __( 'Từng bước phát triển, qua những công trình và những lần cộng tác.', 'vgarco' ) ) ); ?></p>
		</div>
		<ol class="timeline">
			<?php foreach ( $milestones as $milestone ) : ?>
				<li>
					<span class="timeline-year"><?php echo esc_html( $milestone['nam'] ); ?></span>
					<div>
						<?php if ( '' !== $milestone['dong_phu'] ) : ?>
							<?php if ( preg_match( '/^(\d{2})\.(\d{2})\.(\d{4})$/', $milestone['dong_phu'], $d ) ) : ?>
								<time class="timeline-meta" datetime="<?php echo esc_attr( "{$d[3]}-{$d[2]}-{$d[1]}" ); ?>"><?php echo esc_html( $milestone['dong_phu'] ); ?></time>
							<?php else : ?>
								<span class="timeline-meta"><?php echo esc_html( $milestone['dong_phu'] ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
						<h3><?php echo esc_html( $milestone['tieu_de'] ); ?></h3>
						<p><?php echo esc_html( $milestone['mo_ta'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>

<section class="wrap about-approach">
	<div>
		<p class="eyebrow"><?php esc_html_e( 'Cách chúng tôi làm việc', 'vgarco' ); ?></p>
		<h2><?php echo vgarco_field_heading( 'cach_lam_tieu_de', __( "Nhìn tổng thể.\n<em>Làm đến chi tiết.</em>", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<?php if ( $approach_img > 0 ) : ?>
			<figure>
				<?php
				echo vgarco_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup ảnh của WordPress.
					$approach_img,
					array(
						'size'  => 'vg-1200',
						'sizes' => '(min-width: 701px) 48vw, 100vw',
						'alt'   => $t( 'cach_lam_chu_thich', '' ),
					)
				);
				?>
				<?php if ( '' !== $t( 'cach_lam_chu_thich', '' ) ) : ?>
					<figcaption><?php echo esc_html( $t( 'cach_lam_chu_thich', '' ) ); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>
	</div>
	<div class="approach-list">
		<?php foreach ( $principles as $i => $principle ) : ?>
			<article>
				<span aria-hidden="true"><?php echo esc_html( vgarco_pad( $i + 1 ) ); ?></span>
				<h3><?php echo esc_html( $principle['tieu_de'] ); ?></h3>
				<p><?php echo esc_html( $principle['mo_ta'] ); ?></p>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<section class="wrap about-reach">
	<div>
		<p class="eyebrow"><?php esc_html_e( 'Không giới hạn bởi biên giới', 'vgarco' ); ?></p>
		<h2><?php echo vgarco_field_heading( 'pham_vi_tieu_de', __( "Từ Việt Nam.\n<em>Vươn ra thế giới.</em>", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
	</div>
	<div>
		<p><?php echo esc_html( $t( 'pham_vi_mo_ta', __( 'Những bối cảnh văn hóa và kỹ thuật khác nhau mở rộng cách nhìn của chúng tôi về kiến trúc và xây dựng.', 'vgarco' ) ) ); ?></p>
		<?php if ( $countries ) : ?>
			<ul class="reach-countries">
				<?php foreach ( $countries as $country ) : ?>
					<li><?php echo esc_html( $country ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>

<?php if ( $award_image > 0 || '' !== $award_title ) : ?>
	<section class="wrap recognition" id="ghi-nhan"<?php echo '' !== $award_title ? ' aria-labelledby="recognition-title"' : ''; ?>>
		<?php if ( $award_image > 0 ) : ?>
			<div class="award-image">
				<?php
				echo vgarco_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup ảnh của WordPress.
					$award_image,
					array(
						'size'  => 'vg-1200',
						'sizes' => '(min-width: 701px) 45vw, 100vw',
						'alt'   => $t( 'ghi_nhan_anh_alt', '' ),
					)
				);
				?>
			</div>
		<?php endif; ?>
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Sự ghi nhận', 'vgarco' ); ?></p>
			<?php if ( '' !== $award_title ) : ?>
				<h2 id="recognition-title"><?php echo vgarco_heading( $award_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $t( 'ghi_nhan_mo_ta', '' ) ) : ?>
				<p><?php echo esc_html( $t( 'ghi_nhan_mo_ta', '' ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $t( 'ghi_nhan_chu_thich', '' ) ) : ?>
				<p class="muted small"><?php echo esc_html( $t( 'ghi_nhan_chu_thich', '' ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $award_url ) : ?>
				<a class="text-link" href="<?php echo esc_url( $award_url ); ?>"><?php esc_html_e( 'Xem tư liệu ghi nhận', 'vgarco' ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();

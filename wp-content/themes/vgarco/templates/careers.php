<?php
/**
 * Template Name: Tuyển dụng
 *
 * Trang Tuyển dụng (UC-07). Render sẵn MỌI vị trí đang nhận hồ sơ; JS lọc tại chỗ theo từ khóa, nơi làm việc, phòng ban
 * (không tải lại trang, ghi bộ lọc vào URL). Không có JS: biểu mẫu gửi GET và PHP lọc bằng cùng quy tắc — đường dẫn
 * ?q=&noi-lam-viec=&phong-ban= chia sẻ được ở cả hai cách. Không có vị trí nào: thông báo + khối "Chưa thấy vị trí phù hợp?" (3a).
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();

$id       = (int) get_queried_object_id();
$page_url = (string) get_permalink( $id );
$all      = vgarco_open_jobs();
$filters  = vgarco_job_filters( $all );

// Giá trị bộ lọc từ URL: chỉ nhận đúng các lựa chọn đang có (không tin dữ liệu người dùng gửi lên).
$q_raw = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- bộ lọc công khai, chỉ đọc.
$query = mb_substr( $q_raw, 0, 100 );
$loc   = isset( $_GET['noi-lam-viec'] ) ? sanitize_key( wp_unslash( (string) $_GET['noi-lam-viec'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$dept  = isset( $_GET['phong-ban'] ) ? sanitize_key( wp_unslash( (string) $_GET['phong-ban'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$loc   = isset( $filters['locations'][ $loc ] ) ? $loc : '';
$dept  = isset( $filters['departments'][ $dept ] ) ? $dept : '';

$jobs   = vgarco_filter_jobs( $all, $query, $loc, $dept );
$active = '' !== trim( $query ) || '' !== $loc || '' !== $dept;
$mail   = (string) ( vgarco_option( 'lien_he.email_tuyen_dung' ) ?: vgarco_option( 'lien_he.email_chung' ) );
$hero   = vgarco_field_image( 'hero_anh', $id );

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Tuyển dụng', 'vgarco' ) ),
	)
);
?>

<section class="career-intro on-dark">
	<?php if ( $hero > 0 ) : ?>
		<?php
		echo vgarco_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup ảnh của WordPress.
			$hero,
			array(
				'size'     => 'vg-1600',
				'sizes'    => '100vw',
				'alt'      => '',
				'class'    => 'career-intro-bg',
				'priority' => true,
			)
		);
		?>
	<?php endif; ?>
	<div class="wrap career-intro-inner">
		<p class="eyebrow"><?php echo esc_html( vgarco_field_text( 'hero_nhan', __( 'Cơ hội nghề nghiệp', 'vgarco' ), $id ) ); ?></p>
		<h1><?php echo vgarco_field_heading( 'hero_tieu_de', __( "Chọn công việc.\n<em>Cùng tạo dấu ấn.</em>", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã lọc trong vgarco_heading(). ?></h1>
		<div class="career-intro-caption">
			<span><?php echo esc_html( vgarco_field_text( 'hero_chu_thich_nho', __( 'VGARCO / People & Practice', 'vgarco' ), $id ) ); ?></span>
			<strong><?php echo vgarco_field_lines( 'hero_chu_thich', __( "Cùng làm nghề.\nCùng tạo giá trị.", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã thoát trong vgarco_lines(). ?></strong>
		</div>
	</div>
</section>

<section class="wrap jobs-section" aria-labelledby="jobs-title" data-jobs>
	<div class="jobs-heading">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Vị trí tuyển dụng', 'vgarco' ); ?></p>
			<h2 id="jobs-title"><?php echo esc_html( vgarco_field_text( 'jobs_tieu_de', __( 'Tìm vị trí phù hợp', 'vgarco' ), $id ) ); ?></h2>
		</div>
		<p><?php echo esc_html( vgarco_field_text( 'jobs_mo_ta', __( 'Tra cứu theo tên vị trí, địa điểm và phòng ban.', 'vgarco' ), $id ) ); ?></p>
	</div>

	<?php if ( $all ) : ?>
		<form class="job-search" role="search" method="get" action="<?php echo esc_url( $page_url ); ?>" aria-label="<?php esc_attr_e( 'Tìm kiếm vị trí tuyển dụng', 'vgarco' ); ?>" data-job-form>
			<div class="job-search-primary">
				<div class="field">
					<label for="job-keyword"><?php esc_html_e( 'Vị trí', 'vgarco' ); ?></label>
					<input id="job-keyword" name="q" type="search" value="<?php echo esc_attr( $query ); ?>" maxlength="100" placeholder="<?php esc_attr_e( 'Tìm kiếm vị trí, từ khóa…', 'vgarco' ); ?>">
				</div>
				<button type="submit" class="job-search-button"><?php esc_html_e( 'Tìm kiếm', 'vgarco' ); ?></button>
			</div>
			<div class="job-search-filters">
				<div class="field">
					<label for="job-location"><?php esc_html_e( 'Địa điểm', 'vgarco' ); ?></label>
					<select id="job-location" name="noi-lam-viec">
						<option value=""><?php esc_html_e( 'Tất cả địa điểm', 'vgarco' ); ?></option>
						<?php foreach ( $filters['locations'] as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( (string) $slug ); ?>"<?php selected( $loc, (string) $slug ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="field">
					<label for="job-department"><?php esc_html_e( 'Phòng ban', 'vgarco' ); ?></label>
					<select id="job-department" name="phong-ban">
						<option value=""><?php esc_html_e( 'Tất cả phòng ban', 'vgarco' ); ?></option>
						<?php foreach ( $filters['departments'] as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( (string) $slug ); ?>"<?php selected( $dept, (string) $slug ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</form>

		<div class="jobs-toolbar">
			<p class="job-count" aria-live="polite" data-job-count>
				<strong><?php echo esc_html( vgarco_pad( count( $jobs ) ) ); ?></strong>
				<span><?php echo esc_html( _n( 'vị trí', 'vị trí', count( $jobs ), 'vgarco' ) ); ?></span>
			</p>
			<a class="job-reset" href="<?php echo esc_url( $page_url ); ?>" data-job-reset<?php echo $active ? '' : ' hidden'; ?>><?php esc_html_e( 'Xóa bộ lọc', 'vgarco' ); ?> <span aria-hidden="true">×</span></a>
		</div>

		<div class="job-results" data-job-results>
			<?php $number = 0; ?>
			<?php foreach ( $all as $job ) : ?>
				<?php
				$shown   = in_array( $job['id'], array_column( $jobs, 'id' ), true );
				$number += $shown ? 1 : 0; // số thứ tự chỉ đếm các vị trí đang hiển thị
				?>
				<article class="job-card"<?php echo $shown ? '' : ' hidden'; ?>
					data-search="<?php echo esc_attr( $job['search'] ); ?>" data-location="<?php echo esc_attr( $job['location']['slug'] ); ?>" data-department="<?php echo esc_attr( $job['department']['slug'] ); ?>">
					<span class="job-number" aria-hidden="true"><?php echo esc_html( vgarco_pad( $number ) ); ?></span>
					<div class="job-info">
						<h3><a href="<?php echo esc_url( $job['url'] ); ?>"><?php echo esc_html( $job['title'] ); ?></a></h3>
						<ul class="job-meta">
							<?php foreach ( array( $job['location']['name'], $job['department']['name'], $job['type_label'] ) as $meta ) : ?>
								<?php if ( '' !== $meta ) : ?>
									<li><?php echo esc_html( $meta ); ?></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
						<?php if ( '' !== $job['summary'] ) : ?>
							<p><?php echo esc_html( $job['summary'] ); ?></p>
						<?php endif; ?>
					</div>
					<a class="job-detail-button" href="<?php echo esc_url( $job['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: tên vị trí */ __( 'Xem chi tiết: %s', 'vgarco' ), $job['title'] ) ); ?>">
						<span><?php esc_html_e( 'Xem chi tiết', 'vgarco' ); ?></span>
						<span class="job-detail-arrow" aria-hidden="true"><?php echo vgarco_icon( 'arrow-up-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</a>
				</article>
			<?php endforeach; ?>

			<div class="jobs-empty" data-job-empty<?php echo $jobs ? ' hidden' : ''; ?>>
				<h3><?php esc_html_e( 'Chưa tìm thấy vị trí phù hợp', 'vgarco' ); ?></h3>
				<p><?php esc_html_e( 'Thử từ khóa khác hoặc bỏ bớt điều kiện lọc.', 'vgarco' ); ?></p>
				<a class="text-link" href="<?php echo esc_url( $page_url ); ?>" data-job-reset><?php esc_html_e( 'Xem tất cả vị trí', 'vgarco' ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
		</div>
	<?php else : ?>
		<div class="jobs-empty">
			<h3><?php esc_html_e( 'Hiện chưa có vị trí nào đang tuyển', 'vgarco' ); ?></h3>
			<p><?php esc_html_e( 'Bạn vẫn có thể gửi hồ sơ giới thiệu để được liên hệ khi có vị trí phù hợp.', 'vgarco' ); ?></p>
		</div>
	<?php endif; ?>
</section>

<?php if ( '' !== $mail ) : ?>
	<section class="wrap career-connect">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Giữ kết nối', 'vgarco' ); ?></p>
			<h2><?php echo esc_html( vgarco_field_text( 'connect_tieu_de', __( 'Chưa thấy vị trí phù hợp?', 'vgarco' ), $id ) ); ?></h2>
			<p><?php echo esc_html( vgarco_field_text( 'connect_mo_ta', __( 'Bạn có thể gửi hồ sơ giới thiệu qua email để kết nối với đội ngũ VGARCO.', 'vgarco' ), $id ) ); ?></p>
		</div>
		<a class="text-link" href="<?php echo esc_url( 'mailto:' . $mail ); ?>"><?php echo esc_html( $mail ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</section>
<?php endif; ?>

<?php
get_footer();

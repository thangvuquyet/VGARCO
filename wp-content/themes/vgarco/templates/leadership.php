<?php
/**
 * Template Name: Ban lãnh đạo
 *
 * Trang Ban lãnh đạo (UC-02). Thành viên hiển thị theo đúng thứ tự trong trường lặp (4a). Chưa nhập thì dùng
 * thành viên mặc định trong hồ sơ. Thành viên không có ảnh hiển thị chữ cái đầu của họ tên.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();

$id      = (int) get_queried_object_id();
$company = (string) vgarco_option( 'phap_nhan.ten', 'VGARCO' );

$members = vgarco_field_rows(
	'thanh_vien',
	array( 'anh', 'chuc_danh', 'ho_ten', 'don_vi', 'vai_tro', 'trong_tam' ),
	array(
		array(
			'anh'       => 0,
			'chuc_danh' => __( 'Giám đốc', 'vgarco' ),
			'ho_ten'    => 'Vũ Duy Hùng',
			'don_vi'    => '',
			'vai_tro'   => __( 'Định hướng hoạt động tư vấn thiết kế, phối hợp chuyên môn và triển khai dự án của VGARCO.', 'vgarco' ),
			'trong_tam' => __( 'Từ ý tưởng đến triển khai, mỗi quyết định cần giữ được logic thiết kế, tính khả thi kỹ thuật và mục tiêu sử dụng của công trình.', 'vgarco' ),
		),
	),
	$id
);
$principles = vgarco_field_rows(
	'nguyen_tac',
	array( 'tieu_de', 'mo_ta' ),
	array(
		array(
			'tieu_de' => __( 'Nhìn tổng thể', 'vgarco' ),
			'mo_ta'   => __( 'Đặt từng quyết định trong mối quan hệ với toàn bộ dự án.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Quyết định rõ ràng', 'vgarco' ),
			'mo_ta'   => __( 'Ưu tiên giải pháp có logic, có cơ sở và có khả năng triển khai.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Phối hợp chuyên môn', 'vgarco' ),
			'mo_ta'   => __( 'Kết nối các bộ môn từ sớm để giảm xung đột ở giai đoạn sau.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Theo sát thực tế', 'vgarco' ),
			'mo_ta'   => __( 'Không tách thiết kế khỏi điều kiện thi công và vận hành.', 'vgarco' ),
		),
	),
	$id
);

/** Chữ cái đầu của họ và của tên ("Vũ Duy Hùng" → "VH"), dùng làm hình đại diện khi chưa có ảnh. */
$initials = static function ( string $name ): string {
	$words = preg_split( '/\s+/u', trim( $name ) ) ?: array();
	$words = count( $words ) > 1 ? array( $words[0], end( $words ) ) : $words;
	return mb_strtoupper( implode( '', array_map( static fn( $w ) => mb_substr( $w, 0, 1 ), $words ) ) );
};

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Ban lãnh đạo', 'vgarco' ) ),
	)
);
?>

<h1 class="sr-only"><?php esc_html_e( 'Ban lãnh đạo', 'vgarco' ); ?></h1>

<?php foreach ( $members as $member ) : ?>
	<?php
	$photo = (int) $member['anh'];
	$unit  = '' !== $member['don_vi'] ? $member['don_vi'] : $company;
	?>
	<section class="wrap leadership-content">
		<?php if ( $photo > 0 ) : ?>
			<figure class="leader-photo">
				<?php
				echo vgarco_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup ảnh của WordPress.
					$photo,
					array(
						'size'  => 'vg-1200',
						'sizes' => '(min-width: 701px) 40vw, 100vw',
						'alt'   => trim( $member['chuc_danh'] . ' ' . $member['ho_ten'] ),
					)
				);
				?>
			</figure>
		<?php else : ?>
			<div class="leader-monogram" aria-hidden="true"><?php echo esc_html( $initials( $member['ho_ten'] ) ); ?><span>VGARCO<?php echo '' !== $member['chuc_danh'] ? ' / ' . esc_html( mb_strtoupper( $member['chuc_danh'] ) ) : ''; ?></span></div>
		<?php endif; ?>
		<div>
			<?php if ( '' !== $member['chuc_danh'] ) : ?>
				<p class="eyebrow"><?php echo esc_html( $member['chuc_danh'] ); ?></p>
			<?php endif; ?>
			<h2><?php echo esc_html( $member['ho_ten'] ); ?></h2>
			<p class="leadership-company"><?php echo esc_html( $unit ); ?></p>
			<?php if ( '' !== $member['vai_tro'] ) : ?>
				<p class="leadership-note"><?php echo esc_html( $member['vai_tro'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $member['trong_tam'] ) : ?>
				<div class="leader-statement">
					<span><?php esc_html_e( 'Trọng tâm điều hành', 'vgarco' ); ?></span>
					<p><?php echo esc_html( $member['trong_tam'] ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endforeach; ?>

<?php if ( $principles ) : ?>
	<section class="wrap leadership-principles" aria-labelledby="principles-title">
		<p class="eyebrow" id="principles-title"><?php esc_html_e( 'Nguyên tắc làm việc', 'vgarco' ); ?></p>
		<div class="leadership-principle-grid">
			<?php foreach ( $principles as $i => $principle ) : ?>
				<article>
					<span aria-hidden="true"><?php echo esc_html( vgarco_pad( $i + 1 ) ); ?></span>
					<h3><?php echo esc_html( $principle['tieu_de'] ); ?></h3>
					<p><?php echo esc_html( $principle['mo_ta'] ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();

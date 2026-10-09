<?php
/**
 * Template Name: Phòng ban
 */

defined( 'ABSPATH' ) || exit;

get_header();

$id = (int) get_queried_object_id();

$tags = vgarco_split_lines(
	vgarco_field_text(
		'nhan',
		implode( "\n", array( __( 'Thiết kế', 'vgarco' ), __( 'Kỹ thuật', 'vgarco' ), __( 'Chi phí', 'vgarco' ), __( 'Triển khai', 'vgarco' ) ) ),
		$id
	)
);

$disciplines = vgarco_field_rows(
	'bo_mon',
	array( 'tieu_de', 'mo_ta' ),
	array(
		array(
			'tieu_de' => __( 'Kiến trúc', 'vgarco' ),
			'mo_ta'   => __( 'Ý tưởng không gian, công năng, hình thức và trải nghiệm sử dụng.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Xây dựng dân dụng', 'vgarco' ),
			'mo_ta'   => __( 'Kết nối giải pháp kiến trúc với cấu tạo, kết cấu và khả năng thi công.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Hạ tầng kỹ thuật', 'vgarco' ),
			'mo_ta'   => __( 'Tổ chức tiếp cận, mặt bằng ngoài nhà và các kết nối kỹ thuật của khu đất.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Cơ điện', 'vgarco' ),
			'mo_ta'   => __( 'Phối hợp hệ thống điện, thông gió và các yêu cầu kỹ thuật trong công trình.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Cấp thoát nước', 'vgarco' ),
			'mo_ta'   => __( 'Tích hợp hệ thống cấp nước, thoát nước và yêu cầu vận hành ngay từ thiết kế.', 'vgarco' ),
		),
		array(
			'tieu_de' => __( 'Kinh tế xây dựng', 'vgarco' ),
			'mo_ta'   => __( 'Kiểm soát khối lượng, logic chi phí và tính khả thi của phương án.', 'vgarco' ),
		),
	),
	$id
);

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Phòng ban', 'vgarco' ) ),
	)
);
?>

<section class="wrap department-editorial">
	<div class="department-intro">
		<p class="eyebrow"><?php echo esc_html( vgarco_field_text( 'hero_nhan', __( 'Hệ chuyên môn', 'vgarco' ), $id ) ); ?></p>
		<h1><?php echo vgarco_field_heading( 'hero_tieu_de', __( "Nhiều chuyên môn.\n<em>Một giải pháp thống nhất.</em>", 'vgarco' ), $id ); ?></h1>
		<p><?php echo esc_html( vgarco_field_text( 'hero_mo_ta', __( 'VGARCO tổ chức công việc theo mô hình phối hợp đa chuyên môn. Mỗi bộ môn giữ một vai trò riêng nhưng cùng tham gia vào một dòng thông tin thống nhất từ ý tưởng đến triển khai.', 'vgarco' ), $id ) ); ?></p>
		<?php if ( $tags ) : ?>
			<ul class="department-tags">
				<?php foreach ( $tags as $tag ) : ?>
					<li><?php echo esc_html( $tag ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<h2 class="sr-only"><?php esc_html_e( 'Hệ chuyên môn', 'vgarco' ); ?></h2>
	<div class="discipline-matrix">
		<?php foreach ( $disciplines as $i => $discipline ) : ?>
			<article>
				<span aria-hidden="true"><?php echo esc_html( vgarco_pad( $i + 1 ) ); ?></span>
				<h3><?php echo esc_html( $discipline['tieu_de'] ); ?></h3>
				<p><?php echo esc_html( $discipline['mo_ta'] ); ?></p>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php
get_footer();

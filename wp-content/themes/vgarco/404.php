<?php
/**
 * Trang lỗi 404 theo ngữ cảnh (yêu cầu 35, UC-05 2a): địa chỉ nằm dưới mục nào thì lời nhắn và liên kết đầu tiên dẫn về mục đó
 * (dự án → danh mục dự án; tin → trang tin; vị trí tuyển dụng → trang tuyển dụng). Đúng ngôn ngữ đang xem vì mọi địa chỉ
 * mục đều lấy qua WPML. Trang này không được lập chỉ mục (inc/seo.php).
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sections = vgarco_error_sections();
$request  = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chỉ so khớp tiền tố, không in ra.
$context  = vgarco_url_section( $request, $sections );
$current  = $context ? $sections[ $context ] : null;

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Không tìm thấy trang', 'vgarco' ) ),
	)
);
?>
<section class="wrap error-page">
	<p class="eyebrow">404</p>
	<h1><?php esc_html_e( 'Không tìm thấy trang này', 'vgarco' ); ?></h1>
	<p class="error-lead">
		<?php
		echo esc_html(
			$current
				? $current['message']
				: __( 'Địa chỉ bạn mở có thể đã thay đổi hoặc nội dung không còn. Hãy quay lại trang chủ hoặc chọn một mục bên dưới.', 'vgarco' )
		);
		?>
	</p>

	<div class="error-actions">
		<?php if ( $current ) : ?>
			<a class="button-primary" href="<?php echo esc_url( $current['url'] ); ?>"><?php echo esc_html( $current['action'] ); ?></a>
			<a class="text-link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Về trang chủ', 'vgarco' ); ?></a>
		<?php else : ?>
			<a class="button-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Về trang chủ', 'vgarco' ); ?></a>
		<?php endif; ?>
	</div>

	<nav class="error-links" aria-label="<?php esc_attr_e( 'Các mục chính', 'vgarco' ); ?>">
		<ul>
			<?php foreach ( $sections as $section ) : ?>
				<li><a href="<?php echo esc_url( $section['url'] ); ?>"><?php echo esc_html( $section['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
</section>
<?php
get_footer();

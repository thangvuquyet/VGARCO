<?php

defined( 'ABSPATH' ) || exit;

get_header();

$sections = vgarco_error_sections();
$request  = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH );
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

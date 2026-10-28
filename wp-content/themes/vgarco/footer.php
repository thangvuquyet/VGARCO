<?php

defined( 'ABSPATH' ) || exit;

$company  = (string) vgarco_option( 'phap_nhan.ten' );
$address  = (string) vgarco_option( 'phap_nhan.dia_chi_van_phong' );
$phone    = (string) vgarco_option( 'lien_he.dien_thoai' );
$tel      = (string) vgarco_option( 'lien_he.dien_thoai_tel' );
$zalo     = (string) vgarco_option( 'mang_xa_hoi.zalo' );
$facebook = (string) vgarco_option( 'mang_xa_hoi.facebook' );
?>
</main>

<footer class="footer" id="footer">
	<div class="footer-main">
		<div class="footer-brand">
			<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'VGARCO — Trang chủ', 'vgarco' ); ?>">
				<?php get_template_part( 'template-parts/layout/logo' ); ?>
				<span>VGARCO<small><?php esc_html_e( 'TƯ VẤN XÂY DỰNG', 'vgarco' ); ?></small></span>
			</a>
			<p><?php echo vgarco_lines( __( "Tư vấn thiết kế, giám sát\nvà quản lý dự án xây dựng.", 'vgarco' ) ); ?></p>
		</div>

		<div class="footer-col footer-office">
			<span class="footer-title"><?php esc_html_e( 'VĂN PHÒNG', 'vgarco' ); ?></span>
			<strong><?php echo esc_html( $company ); ?></strong>
			<p><?php echo vgarco_lines( $address ); ?></p>
			<a class="footer-inline-link" href="<?php echo esc_url( vgarco_directions_url() ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Chỉ đường', 'vgarco' ); ?>
				<span class="sr-only"><?php esc_html_e( '(mở ở thẻ mới)', 'vgarco' ); ?></span>
				<?php echo vgarco_icon( 'arrow-up-right' ); ?>
			</a>
		</div>

		<nav class="footer-col footer-nav" aria-label="<?php esc_attr_e( 'Điều hướng chân trang', 'vgarco' ); ?>">
			<span class="footer-title"><?php esc_html_e( 'KHÁM PHÁ', 'vgarco' ); ?></span>
			<?php
			foreach ( vgarco_menu_tree( 'footer' ) as $node ) {
				vgarco_render_menu_link( $node );
			}
			?>
		</nav>

		<div class="footer-col footer-connect">
			<span class="footer-title"><?php esc_html_e( 'KẾT NỐI', 'vgarco' ); ?></span>
			<div class="footer-social">
				<?php if ( '' !== $tel ) : ?>
					<a href="<?php echo esc_url( 'tel:' . $tel ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Gọi %s', 'vgarco' ), $phone ) ); ?>">
						<?php echo vgarco_icon( 'phone' ); ?>
					</a>
				<?php endif; ?>
				<?php vgarco_social_item( 'Zalo', $zalo, 'Z' ); ?>
				<?php vgarco_social_item( 'Facebook', $facebook, 'f' ); ?>
			</div>
			<?php if ( '' !== $tel ) : ?>
				<a class="footer-phone" href="<?php echo esc_url( 'tel:' . $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
			<?php endif; ?>
			<?php if ( '' === $zalo || '' === $facebook ) : ?>
				<small><?php esc_html_e( 'Zalo · Facebook đang cập nhật', 'vgarco' ); ?></small>
			<?php endif; ?>
		</div>
	</div>

	<div class="footer-bottom">
		<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> VGARCO</span>
		<span><?php esc_html_e( 'VIỆT NAM · LÀO · CHLB ĐỨC', 'vgarco' ); ?></span>
		<a href="#top">
			<?php esc_html_e( 'Về đầu trang', 'vgarco' ); ?>
			<?php echo vgarco_icon( 'arrow-up' ); ?>
		</a>
	</div>
</footer>

<?php get_template_part( 'template-parts/layout/search-dialog' ); ?>

<?php wp_footer(); ?>
</body>
</html>

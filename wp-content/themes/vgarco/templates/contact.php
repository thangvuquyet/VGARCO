<?php
/**
 * Template Name: Liên hệ
 *
 * Trang Liên hệ (UC-11). Biểu mẫu KHÔNG gửi dữ liệu về máy chủ: JS soạn sẵn một email (mailto:) để người dùng xem lại và gửi
 * từ ứng dụng email của họ (yêu cầu 28–30). Không có JS: biểu mẫu vẫn mở được ứng dụng email qua action="mailto:".
 * Kênh liên hệ lấy từ Cấu hình VGARCO; kênh chưa có địa chỉ hiển thị "Đang cập nhật" (yêu cầu 29).
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();

$id       = (int) get_queried_object_id();
$phone    = (string) vgarco_option( 'lien_he.dien_thoai' );
$tel      = (string) vgarco_option( 'lien_he.dien_thoai_tel' );
$email    = (string) vgarco_option( 'lien_he.email_chung' );
$channels = array(
	array( 'Zalo', (string) vgarco_option( 'mang_xa_hoi.zalo' ), 'Z' ),
	array( 'Facebook', (string) vgarco_option( 'mang_xa_hoi.facebook' ), 'f' ),
);

vgarco_breadcrumb(
	array(
		array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
		array( __( 'Liên hệ', 'vgarco' ) ),
	)
);
?>

<section class="contact on-dark" aria-labelledby="contact-title">
	<div class="wrap contact-layout">
		<div class="contact-intro">
			<p class="eyebrow"><?php echo esc_html( vgarco_field_text( 'hero_nhan', __( 'Bắt đầu một dự án', 'vgarco' ), $id ) ); ?></p>
			<h1 id="contact-title"><?php echo vgarco_field_heading( 'hero_tieu_de', __( "Một cuộc\ntrò chuyện.\n<em>Một khởi đầu.</em>", 'vgarco' ), $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã lọc trong vgarco_heading(). ?></h1>
			<?php if ( '' !== $tel ) : ?>
				<a class="contact-phone" href="<?php echo esc_url( 'tel:' . $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $email ) : ?>
				<a class="contact-email" href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
			<?php endif; ?>

			<div class="contact-channels" role="group" aria-label="<?php esc_attr_e( 'Kênh liên hệ VGARCO', 'vgarco' ); ?>">
				<?php if ( '' !== $tel ) : ?>
					<a class="contact-channel" href="<?php echo esc_url( 'tel:' . $tel ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: số điện thoại */ __( 'Gọi VGARCO qua số %s', 'vgarco' ), $phone ) ); ?>">
						<span class="channel-icon" aria-hidden="true"><?php echo vgarco_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><strong><?php esc_html_e( 'Điện thoại', 'vgarco' ); ?></strong><small><?php esc_html_e( 'Gọi trực tiếp', 'vgarco' ); ?></small></span>
					</a>
				<?php endif; ?>
				<?php foreach ( $channels as $channel ) : ?>
					<?php if ( '' !== $channel[1] ) : ?>
						<a class="contact-channel" href="<?php echo esc_url( $channel[1] ); ?>" target="_blank" rel="noopener">
							<span class="channel-icon" aria-hidden="true"><?php echo esc_html( $channel[2] ); ?></span>
							<span><strong><?php echo esc_html( $channel[0] ); ?></strong><small><?php esc_html_e( 'Mở ở thẻ mới', 'vgarco' ); ?></small></span>
						</a>
					<?php else : ?>
						<span class="contact-channel is-pending">
							<span class="channel-icon" aria-hidden="true"><?php echo esc_html( $channel[2] ); ?></span>
							<span><strong><?php echo esc_html( $channel[0] ); ?></strong><small><?php esc_html_e( 'Đang cập nhật', 'vgarco' ); ?></small></span>
						</span>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( '' !== $email ) : ?>
			<form id="contact-form" class="contact-form" action="<?php echo esc_url( 'mailto:' . $email ); ?>" method="post" enctype="text/plain" novalidate
				data-to="<?php echo esc_attr( $email ); ?>"
				data-subject="<?php echo esc_attr( __( 'Yêu cầu tư vấn – %s', 'vgarco' ) ); ?>"
				data-body="<?php echo esc_attr( __( "Họ và tên: %name%\nSố điện thoại: %phone%\nEmail: %email%\n\nNội dung:\n%message%\n\n— Soạn từ website VGARCO", 'vgarco' ) ); ?>"
				data-msg-required="<?php esc_attr_e( 'Vui lòng nhập trường này.', 'vgarco' ); ?>"
				data-msg-email="<?php esc_attr_e( 'Địa chỉ email chưa đúng. Ví dụ: ten@congty.vn', 'vgarco' ); ?>"
				data-msg-consent="<?php esc_attr_e( 'Vui lòng đồng ý để chúng tôi liên hệ tư vấn.', 'vgarco' ); ?>"
				data-msg-copied="<?php esc_attr_e( 'Đã sao chép nội dung. Dán vào email của bạn.', 'vgarco' ); ?>"
				data-msg-copy-failed="<?php esc_attr_e( 'Không sao chép được. Hãy chọn và sao chép nội dung ở khung bên dưới.', 'vgarco' ); ?>">
				<h2><?php echo esc_html( vgarco_field_text( 'form_tieu_de', __( 'Chia sẻ nhu cầu của bạn', 'vgarco' ), $id ) ); ?></h2>

				<div class="form-grid">
					<div class="field">
						<label for="cf-name"><?php esc_html_e( 'Họ và tên', 'vgarco' ); ?> <span aria-hidden="true">*</span></label>
						<input id="cf-name" name="name" autocomplete="name" required aria-required="true" maxlength="100" placeholder="<?php esc_attr_e( 'Tên của bạn', 'vgarco' ); ?>" aria-describedby="cf-name-error">
						<span class="field-error" id="cf-name-error"></span>
					</div>
					<div class="field">
						<label for="cf-phone"><?php esc_html_e( 'Số điện thoại', 'vgarco' ); ?></label>
						<input id="cf-phone" name="phone" type="tel" autocomplete="tel" maxlength="30" placeholder="<?php esc_attr_e( 'Số điện thoại liên hệ', 'vgarco' ); ?>">
					</div>
				</div>
				<div class="field">
					<label for="cf-email"><?php esc_html_e( 'Email', 'vgarco' ); ?> <span aria-hidden="true">*</span></label>
					<input id="cf-email" name="email" type="email" autocomplete="email" required aria-required="true" maxlength="200" placeholder="email@congty.vn" aria-describedby="cf-email-error">
					<span class="field-error" id="cf-email-error"></span>
				</div>
				<div class="field">
					<label for="cf-message"><?php esc_html_e( 'Nội dung trao đổi', 'vgarco' ); ?> <span aria-hidden="true">*</span></label>
					<textarea id="cf-message" name="message" rows="3" required aria-required="true" maxlength="1500" placeholder="<?php esc_attr_e( 'Loại công trình, địa điểm, nhu cầu tư vấn…', 'vgarco' ); ?>" aria-describedby="cf-message-error"></textarea>
					<span class="field-error" id="cf-message-error"></span>
				</div>

				<div class="field consent">
					<label class="consent-label">
						<input id="cf-consent" type="checkbox" name="consent" required aria-required="true" aria-describedby="cf-consent-error">
						<span><?php esc_html_e( 'Tôi đồng ý sử dụng thông tin trên để VGARCO liên hệ tư vấn.', 'vgarco' ); ?></span>
					</label>
					<span class="field-error" id="cf-consent-error"></span>
				</div>

				<button class="button-light" type="submit">
					<span><?php esc_html_e( 'Soạn email tư vấn', 'vgarco' ); ?></span>
					<?php echo vgarco_icon( 'arrow-up-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
				<p class="form-hint"><?php esc_html_e( 'Mở ứng dụng email với nội dung bạn đã nhập. Bạn xem lại và gửi email tại đó.', 'vgarco' ); ?></p>

				<div class="form-fallback" data-fallback hidden>
					<p>
						<?php
						/* translators: %s: địa chỉ email của VGARCO. */
						echo wp_kses( sprintf( __( 'Nếu ứng dụng email không mở, hãy gửi nội dung dưới đây tới <a href="mailto:%1$s">%1$s</a>.', 'vgarco' ), esc_html( $email ) ), array( 'a' => array( 'href' => array() ) ) );
						?>
					</p>
					<label class="sr-only" for="cf-draft"><?php esc_html_e( 'Nội dung email soạn sẵn', 'vgarco' ); ?></label>
					<textarea id="cf-draft" readonly rows="6" data-draft></textarea>
					<button type="button" class="button-ghost" data-copy><?php esc_html_e( 'Sao chép nội dung', 'vgarco' ); ?></button>
				</div>
				<p class="form-status" role="status" data-status></p>
			</form>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();

<?php
/**
 * Template Name: Ứng tuyển
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_robots', 'wp_robots_no_robots' );

get_header();

$job_id = isset( $_GET['id'] ) ? absint( wp_unslash( (string) $_GET['id'] ) ) : 0;
$job    = ( $job_id > 0 && 'vi_tri' === get_post_type( $job_id ) && 'publish' === get_post_status( $job_id ) ) ? vgarco_job( $job_id ) : null;
$careers = vgarco_page_url( 'careers' );
$lang    = function_exists( 'apply_filters' ) ? (string) apply_filters( 'wpml_current_language', 'vi' ) : 'vi';

$trail = array(
	array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
	array( __( 'Tuyển dụng', 'vgarco' ), $careers ),
);
if ( $job ) {
	$trail[] = array( $job['title'], $job['url'] );
}
$trail[] = array( __( 'Ứng tuyển', 'vgarco' ) );
vgarco_breadcrumb( $trail );
?>

<?php if ( ! $job || ! $job['open'] ) : ?>
	<section class="wrap job-apply-head">
		<p class="eyebrow"><?php esc_html_e( 'Ứng tuyển', 'vgarco' ); ?></p>
		<h1><?php echo esc_html( $job ? __( 'Vị trí này đã ngừng nhận hồ sơ', 'vgarco' ) : __( 'Không tìm thấy vị trí', 'vgarco' ) ); ?></h1>
		<p><?php echo esc_html( $job ? $job['title'] : __( 'Đường dẫn ứng tuyển chưa đúng hoặc vị trí không còn tồn tại.', 'vgarco' ) ); ?></p>
		<a class="text-link" href="<?php echo esc_url( $careers ); ?>"><?php esc_html_e( 'Xem các vị trí đang tuyển', 'vgarco' ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?></a>
	</section>
<?php else : ?>
	<section class="wrap job-apply-head">
		<p class="eyebrow"><?php esc_html_e( 'Ứng tuyển vị trí', 'vgarco' ); ?></p>
		<h1><?php echo esc_html( $job['title'] ); ?></h1>
		<p><?php echo esc_html( implode( ' · ', array_filter( array( $job['location']['name'], $job['department']['name'], $job['type_label'] ) ) ) ); ?></p>
	</section>

	<section class="wrap job-apply-layout">
		<form class="job-apply-form" id="job-apply-form" method="post" enctype="multipart/form-data" novalidate hidden
			data-endpoint="<?php echo esc_url( rest_url( 'vgarco/v1/apply' ) ); ?>"
			data-token-endpoint="<?php echo esc_url( rest_url( 'vgarco/v1/apply/token' ) ); ?>"
			data-max-size="<?php echo esc_attr( (string) ( 10 * 1024 * 1024 ) ); ?>"
			data-msg-required="<?php esc_attr_e( 'Vui lòng nhập trường này.', 'vgarco' ); ?>"
			data-msg-email="<?php esc_attr_e( 'Địa chỉ email chưa đúng. Ví dụ: ten@example.com', 'vgarco' ); ?>"
			data-msg-phone="<?php esc_attr_e( 'Số điện thoại chưa đúng (8–15 chữ số).', 'vgarco' ); ?>"
			data-msg-file-required="<?php esc_attr_e( 'Vui lòng chọn tệp CV.', 'vgarco' ); ?>"
			data-msg-file-type="<?php esc_attr_e( 'Chỉ nhận CV dạng PDF, DOC hoặc DOCX.', 'vgarco' ); ?>"
			data-msg-file-size="<?php esc_attr_e( 'CV vượt quá 10 MB. Hãy nén hoặc chọn tệp nhỏ hơn.', 'vgarco' ); ?>"
			data-msg-file-invalid="<?php esc_attr_e( 'Tệp CV không hợp lệ hoặc bị hỏng. Hãy xuất lại thành PDF rồi thử lại.', 'vgarco' ); ?>"
			data-msg-captcha="<?php esc_attr_e( 'Mã bảo mật chưa đúng. Chúng tôi đã tạo mã mới, dữ liệu của bạn được giữ nguyên.', 'vgarco' ); ?>"
			data-msg-consent="<?php esc_attr_e( 'Vui lòng đồng ý để chúng tôi xử lý hồ sơ.', 'vgarco' ); ?>"
			data-msg-rate="<?php esc_attr_e( 'Bạn đã gửi quá nhiều hồ sơ trong thời gian ngắn. Vui lòng thử lại sau.', 'vgarco' ); ?>"
			data-msg-token-rate="<?php esc_attr_e( 'Bạn đã yêu cầu mã bảo mật quá nhiều lần. Vui lòng đợi ít phút rồi tải lại trang.', 'vgarco' ); ?>"
			data-msg-closed="<?php esc_attr_e( 'Vị trí này đã ngừng nhận hồ sơ.', 'vgarco' ); ?>"
			data-msg-rejected="<?php esc_attr_e( 'Không gửi được hồ sơ. Hãy tải lại trang rồi thử lại.', 'vgarco' ); ?>"
			data-msg-server="<?php esc_attr_e( 'Có lỗi xảy ra phía máy chủ. Hồ sơ chưa được gửi, dữ liệu của bạn vẫn còn — vui lòng thử lại sau ít phút.', 'vgarco' ); ?>"
			data-msg-network="<?php esc_attr_e( 'Không kết nối được. Kiểm tra mạng rồi thử lại, dữ liệu của bạn vẫn còn.', 'vgarco' ); ?>"
			data-msg-success="<?php esc_attr_e( 'Đã nhận hồ sơ của bạn. Chúng tôi đã gửi email xác nhận và sẽ liên hệ khi có bước tiếp theo.', 'vgarco' ); ?>"
			data-file-empty="<?php esc_attr_e( 'Nhấn để chọn và tải lên CV của bạn', 'vgarco' ); ?>"
			data-sending="<?php esc_attr_e( 'Đang gửi…', 'vgarco' ); ?>">
			<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $job['id'] ); ?>">
			<input type="hidden" name="lang" value="<?php echo esc_attr( $lang ); ?>">
			<input type="hidden" name="token" value="" data-token>

			<?php ?>
			<div class="field-trap" aria-hidden="true">
				<label for="website-url">Website</label>
				<input id="website-url" type="text" name="website" tabindex="-1" autocomplete="off">
			</div>

			<div class="field">
				<label for="apply-name"><?php esc_html_e( 'Họ và tên', 'vgarco' ); ?> <span class="req" aria-hidden="true">*</span></label>
				<input id="apply-name" name="name" autocomplete="name" required aria-required="true" maxlength="100" aria-describedby="apply-name-error" placeholder="<?php esc_attr_e( 'Họ và tên của bạn', 'vgarco' ); ?>">
				<span class="field-error" id="apply-name-error"></span>
			</div>
			<div class="field">
				<label for="apply-email"><?php esc_html_e( 'Địa chỉ email', 'vgarco' ); ?> <span class="req" aria-hidden="true">*</span></label>
				<input id="apply-email" name="email" type="email" autocomplete="email" required aria-required="true" maxlength="200" aria-describedby="apply-email-error" placeholder="<?php esc_attr_e( 'Địa chỉ email', 'vgarco' ); ?>">
				<span class="field-error" id="apply-email-error"></span>
			</div>
			<div class="field">
				<label for="apply-phone"><?php esc_html_e( 'Số điện thoại', 'vgarco' ); ?> <span class="req" aria-hidden="true">*</span></label>
				<input id="apply-phone" name="phone" type="tel" autocomplete="tel" required aria-required="true" maxlength="30" aria-describedby="apply-phone-error" placeholder="<?php esc_attr_e( 'Số điện thoại', 'vgarco' ); ?>">
				<span class="field-error" id="apply-phone-error"></span>
			</div>

			<div class="field cv-upload">
				<label for="apply-cv"><?php esc_html_e( 'CV của bạn', 'vgarco' ); ?> <span class="req" aria-hidden="true">*</span></label>
				<input id="apply-cv" name="cv" type="file" accept=".pdf,.doc,.docx" required aria-required="true" aria-describedby="apply-cv-hint apply-cv-error">
				<span class="cv-upload-box">
					<em data-file-name><?php esc_html_e( 'Nhấn để chọn và tải lên CV của bạn', 'vgarco' ); ?></em>
					<small id="apply-cv-hint"><?php esc_html_e( 'PDF, DOC, DOCX · tối đa 10 MB', 'vgarco' ); ?></small>
				</span>
				<span class="field-error" id="apply-cv-error"></span>
			</div>

			<div class="field">
				<label for="apply-captcha"><?php esc_html_e( 'Mã bảo mật', 'vgarco' ); ?> <span class="req" aria-hidden="true">*</span></label>
				<input id="apply-captcha" name="captcha" autocomplete="off" autocapitalize="characters" spellcheck="false" required aria-required="true" maxlength="8" aria-describedby="apply-captcha-hint apply-captcha-error" placeholder="<?php esc_attr_e( 'Nhập mã trong ảnh bên dưới', 'vgarco' ); ?>">
				<span class="field-error" id="apply-captcha-error"></span>
			</div>
			<div class="captcha-row">
				<img data-captcha-image alt="<?php esc_attr_e( 'Mã bảo mật gồm 5 ký tự', 'vgarco' ); ?>" width="180" height="60">
				<button type="button" data-captcha-refresh aria-label="<?php esc_attr_e( 'Tạo mã bảo mật mới', 'vgarco' ); ?>">↻</button>
				<span id="apply-captcha-hint" class="sr-only"><?php esc_html_e( 'Không phân biệt chữ hoa và chữ thường.', 'vgarco' ); ?></span>
			</div>

			<div class="field consent">
				<label class="consent-label">
					<input id="apply-consent" type="checkbox" name="consent" value="1" required aria-required="true" aria-describedby="apply-consent-error">
					<span>
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: liên kết tới chính sách bảo vệ dữ liệu */
								__( 'Tôi đồng ý để VGARCO lưu và xử lý hồ sơ này cho mục đích tuyển dụng trong 12 tháng, theo %s.', 'vgarco' ),
								'<a href="' . esc_url( vgarco_page_url( 'privacy' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'chính sách bảo vệ dữ liệu', 'vgarco' ) . '</a>'
							),
							array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
						);
						?>
					</span>
				</label>
				<span class="field-error" id="apply-consent-error"></span>
			</div>

			<p class="job-apply-feedback" role="alert" data-feedback></p>
			<button class="job-submit" type="submit"><?php esc_html_e( 'Nộp đơn ứng tuyển', 'vgarco' ); ?></button>
		</form>

		<?php ?>
		<noscript>
			<div class="job-apply-noscript">
				<p><?php esc_html_e( 'Để nộp hồ sơ trực tuyến, bạn cần bật JavaScript. Hoặc gửi CV tới địa chỉ email tuyển dụng dưới đây.', 'vgarco' ); ?></p>
				<?php $mail = (string) ( vgarco_option( 'lien_he.email_tuyen_dung' ) ?: vgarco_option( 'lien_he.email_chung' ) ); ?>
				<?php if ( '' !== $mail ) : ?>
					<a class="text-link" href="<?php echo esc_url( 'mailto:' . $mail ); ?>"><?php echo esc_html( $mail ); ?></a>
				<?php endif; ?>
			</div>
		</noscript>

		<aside class="job-apply-summary" aria-label="<?php esc_attr_e( 'Vị trí đang ứng tuyển', 'vgarco' ); ?>">
			<span><?php esc_html_e( 'Vị trí đang ứng tuyển', 'vgarco' ); ?></span>
			<h2><?php echo esc_html( $job['title'] ); ?></h2>
			<?php if ( '' !== $job['summary'] ) : ?>
				<p><?php echo esc_html( $job['summary'] ); ?></p>
			<?php endif; ?>
			<dl>
				<?php if ( '' !== $job['location']['name'] ) : ?>
					<div><dt><?php esc_html_e( 'Địa điểm', 'vgarco' ); ?></dt><dd><?php echo esc_html( $job['location']['name'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( '' !== $job['department']['name'] ) : ?>
					<div><dt><?php esc_html_e( 'Phòng ban', 'vgarco' ); ?></dt><dd><?php echo esc_html( $job['department']['name'] ); ?></dd></div>
				<?php endif; ?>
			</dl>
			<a href="<?php echo esc_url( $job['url'] ); ?>"><?php esc_html_e( 'Xem lại mô tả công việc', 'vgarco' ); ?> <?php echo vgarco_icon( 'arrow-up-right' ); ?></a>
		</aside>
	</section>
<?php endif; ?>

<?php
get_footer();

<?php

defined( 'ABSPATH' ) || exit;

$lang = function_exists( 'apply_filters' ) ? (string) apply_filters( 'wpml_current_language', 'vi' ) : 'vi';
$lang = in_array( $lang, array( 'vi', 'de' ), true ) ? $lang : 'vi';
?>
<dialog id="search-dialog" class="search-dialog" aria-labelledby="search-title" data-search-dialog
	data-endpoint="<?php echo esc_url( rest_url( 'vgarco/v1/search' ) ); ?>"
	data-lang="<?php echo esc_attr( $lang ); ?>"
	data-min="3"
	data-msg-hint="<?php esc_attr_e( 'Nhập ít nhất 3 ký tự để tìm trong các dự án đang trưng bày.', 'vgarco' ); ?>"
	data-msg-empty="<?php esc_attr_e( 'Chưa có dự án phù hợp. Thử tên địa điểm hoặc loại công trình khác.', 'vgarco' ); ?>"
	data-msg-error="<?php esc_attr_e( 'Chưa tìm kiếm được lúc này. Vui lòng thử lại sau.', 'vgarco' ); ?>"
	data-msg-loading="<?php esc_attr_e( 'Đang tìm…', 'vgarco' ); ?>"
	data-msg-one="<?php esc_attr_e( 'Có 1 dự án phù hợp.', 'vgarco' ); ?>"
	data-msg-many="<?php esc_attr_e( 'Có %d dự án phù hợp.', 'vgarco' ); ?>">
	<div class="search-panel">
		<button type="button" class="search-close icon-button" data-search-close aria-label="<?php esc_attr_e( 'Đóng tìm kiếm', 'vgarco' ); ?>">
			<?php echo vgarco_icon( 'close' ); ?>
		</button>
		<h2 id="search-title"><?php esc_html_e( 'Tìm kiếm dự án', 'vgarco' ); ?></h2>
		<label for="search-input"><?php esc_html_e( 'Nhập tên, địa điểm hoặc lĩnh vực', 'vgarco' ); ?></label>
		<input id="search-input" type="search" name="q" autocomplete="off" spellcheck="false" maxlength="100"
			placeholder="<?php esc_attr_e( 'Ví dụ: bảo tàng, trường học…', 'vgarco' ); ?>" aria-describedby="search-status">
		<p id="search-status" class="search-status" role="status" aria-live="polite"><?php esc_html_e( 'Nhập ít nhất 3 ký tự để tìm trong các dự án đang trưng bày.', 'vgarco' ); ?></p>
		<ul class="search-results" id="search-results" hidden></ul>
	</div>
</dialog>

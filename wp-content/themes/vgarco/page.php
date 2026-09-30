<?php
/**
 * Trang nội dung chung: dùng cho trang pháp lý (chính sách bảo vệ dữ liệu, Impressum, Datenschutzerklärung) và mọi trang biên tập
 * viên tạo thêm mà chưa có mẫu riêng. Nội dung do biên tập viên soạn trong trình soạn thảo khối; chỉ các khối được phép
 * (inc/editor.php) mới hiển thị đúng.
 *
 * Các trang có mẫu riêng (Về công ty, Liên hệ...) dùng mẫu trong templates/, không đi qua tệp này.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	vgarco_breadcrumb(
		array(
			array( __( 'Trang chủ', 'vgarco' ), home_url( '/' ) ),
			array( get_the_title() ),
		)
	);
	?>
	<article <?php post_class( 'page-basic wrap' ); ?>>
		<header class="page-basic-head">
			<h1><?php the_title(); ?></h1>
		</header>
		<div class="entry-content"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();

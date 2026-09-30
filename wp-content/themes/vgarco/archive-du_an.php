<?php
/**
 * Danh mục dự án: tất cả dự án (UC-04). Nội dung ở template-parts/catalogue.php, dùng chung với trang một loại công trình.
 *
 * @package vgarco
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/catalogue', null, array( 'term' => null ) );
get_footer();
